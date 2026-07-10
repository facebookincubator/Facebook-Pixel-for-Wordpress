<?php
/**
 * Facebook Pixel Plugin FacebookWordpressNinjaFormsTest class.
 *
 * This file contains the main logic for FacebookWordpressNinjaFormsTest.
 *
 * @package FacebookPixelPlugin
 */

/*
* Copyright (C) 2017-present, Meta, Inc.
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; version 2 of the License.
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*/

namespace FacebookPixelPlugin\Tests\Integration;

use FacebookPixelPlugin\Integration\FacebookWordpressNinjaForms;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressNinjaFormsTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressNinjaFormsTest extends FacebookWordpressTestBase {

    /**
     * Builds the integration wired to the shared signals double.
     *
     * @return FacebookWordpressNinjaForms
     */
    private function make_integration() {
        return new FacebookWordpressNinjaForms( $this->make_signals() );
    }

    /**
     * Invokes the protected set_up_tracking() (the new equivalent of the legacy
     * static inject_pixel_code()).
     *
     * @param FacebookWordpressNinjaForms $obj The integration.
     * @return void
     */
    private function set_up_tracking( $obj ) {
        $method = new \ReflectionMethod( $obj, 'set_up_tracking' );
        if ( PHP_VERSION_ID < 80100 ) {
            $method->setAccessible( true );
        }
        $method->invoke( $obj );
    }

    /**
     * Mocks the WordPress functions the capture path touches.
     *
     * @return void
     */
    private function mock_wp_functions() {
        \WP_Mock::userFunction(
            'sanitize_text_field',
            array(
                'args'   => array( \Mockery::any() ),
                'return' => function ( $input ) {
                    return $input;
                },
            )
        );
        \WP_Mock::userFunction(
            'wp_json_encode',
            array(
                'args'   => array( \Mockery::type( 'array' ), \Mockery::type( 'int' ) ),
                'return' => function ( $data, $options ) {
                    return json_encode( $data );
                },
            )
        );
    }

    /**
     * Tests set_up_tracking registers the submission hook and plants the AJAX
     * listener (new equivalent of the legacy inject_pixel_code hook test).
     *
     * @return void
     */
    public function testSetUpTrackingAddsHooksAndListener() {
        self::mockIsInternalUser( false );

        $obj = $this->make_integration();
        \WP_Mock::expectActionAdded(
            'ninja_forms_submission_actions',
            array( $obj, 'capture_submitted_form' ),
            10,
            3
        );

        $this->set_up_tracking( $obj );

        $this->assertHooksAdded();
        $this->assertCount( 1, $this->registered_ajax_dom );
        $this->assertStringContainsString(
            'submit:response',
            $this->registered_ajax_dom[0]
        );
    }

    /**
     * Tests that no hooks or listener are set up for an internal user.
     *
     * @return void
     */
    public function testSetUpTrackingSkipsForInternalUser() {
        self::mockIsInternalUser( true );

        $obj = $this->make_integration();
        \WP_Mock::expectActionNotAdded(
            'ninja_forms_submission_actions',
            array( $obj, 'capture_submitted_form' )
        );

        $this->set_up_tracking( $obj );

        $this->assertEmpty( $this->registered_ajax_dom );
    }

    /**
     * Tests a successmessage submission tracks a Lead with the extracted +
     * normalized PII/attribution, registers the AJAX response filter, and
     * returns the form actions unchanged.
     *
     * @return void
     */
    public function testTrackEventWithoutInternalUser() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_wp_functions();
        \WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => true ) );
        $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

        \WP_Mock::expectFilterAdded(
            'ninja_forms_post_run_action_type_successmessage',
            \WP_Mock\Functions::type( 'callable' ),
            20
        );

        $actions = array(
            array(
                'id'       => 1,
                'settings' => array(
                    'type'        => 'successmessage',
                    'success_msg' => 'successful',
                ),
            ),
        );

        $result = $this->make_integration()->capture_submitted_form(
            $actions,
            null,
            $this->get_mock_form_data()
        );

        // Actions are passed through unchanged.
        $this->assertEquals( 'successful', $result[0]['settings']['success_msg'] );

        $this->assertHooksAdded();
        $this->assertCount( 1, $this->captured_events );
        $event = $this->captured_events[0];

        $this->assertEquals( 'Lead', $event->getEventName() );
        $this->assertNotNull( $event->getEventTime() );
        $this->assertEquals( 'pika.chu@s2s.com', $event->getUserData()->getEmail() );
        $this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
        $this->assertEquals( 'chu', $event->getUserData()->getLastName() );
        $this->assertEquals( '12345', $event->getUserData()->getPhone() );
        $this->assertEquals( 'oh', $event->getUserData()->getState() );
        $this->assertEquals( 'springfield', $event->getUserData()->getCity() );
        $this->assertEquals( 'us', $event->getUserData()->getCountryCode() );
        $this->assertEquals( '4321', $event->getUserData()->getZipCode() );
        $this->assertEquals( 'm', $event->getUserData()->getGender() );
        $this->assertEquals(
            'ninja-forms',
            $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
        );
        $this->assertEquals( 'TEST_REFERER', $event->getEventSourceUrl() );
    }

    /**
     * Tests that submissions without a successmessage action do not track.
     *
     * @return void
     */
    public function testNonSuccessActionDoesNotTrack() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();

        $actions = array(
            array(
                'id'       => 1,
                'settings' => array( 'type' => 'email' ),
            ),
        );

        $result = $this->make_integration()->capture_submitted_form(
            $actions,
            null,
            $this->get_mock_form_data()
        );

        $this->assertSame( $actions, $result );
        $this->assertCount( 0, $this->captured_events );
        $this->assertCount( 0, $this->enqueued_events );
    }

    /**
     * Tests that the AJAX response is enriched with the pixel code under the
     * container key (new equivalent of the legacy injectLeadEventResponse).
     *
     * @return void
     */
    public function testInjectPixelCodeIntoResponseAddsCode() {
        $response = $this->make_integration()->add_tracking_code_to_ajax_response(
            array(),
            "fbq('track', 'Lead', {});",
            'ninja_forms_pxl_container'
        );

        $this->assertArrayHasKey( 'ninja_forms_pxl_container', $response );
        $this->assertStringContainsString(
            "fbq('track'",
            $response['ninja_forms_pxl_container']
        );
    }

    /**
     * Tests that an already-populated response key is left untouched.
     *
     * @return void
     */
    public function testInjectPixelCodeIntoResponseLeavesExistingUntouched() {
        $response = $this->make_integration()->add_tracking_code_to_ajax_response(
            array( 'ninja_forms_pxl_container' => 'EXISTING' ),
            "fbq('track', 'Lead', {});",
            'ninja_forms_pxl_container'
        );

        $this->assertEquals( 'EXISTING', $response['ninja_forms_pxl_container'] );
    }

    /**
     * Extracts lead data from a submitted Ninja Forms form and asserts each
     * field key maps to the correct Lead parameter (including liststate/
     * listcountry and gender).
     *
     * @return void
     */
    public function testExtractLeadData() {
        $lead = $this->make_integration()->extract_lead_data(
            array(),
            array(),
            $this->get_mock_form_data()
        );

        $this->assertEquals( 'pika.chu@s2s.com', $lead['email'] );
        $this->assertEquals( 'Pika', $lead['first_name'] );
        $this->assertEquals( 'Chu', $lead['last_name'] );
        $this->assertEquals( '12345', $lead['phone'] );
        $this->assertEquals( 'Springfield', $lead['city'] );
        $this->assertEquals( 'OH', $lead['state'] );
        $this->assertEquals( 'US', $lead['country'] );
        $this->assertEquals( '4321', $lead['zip'] );
        $this->assertEquals( 'M', $lead['gender'] );
    }

    /**
     * Creates a mock Ninja Forms form-data payload (fields keyed by 'key' with a
     * submitted 'value'). A top-level 'id' is included, which real Ninja Forms
     * data carries and the extractor reads for form lookup.
     *
     * @return array The mock form-data payload.
     */
    private function get_mock_form_data() {
        return array(
            'id'     => 1,
            'fields' => array(
                array(
                    'key'   => 'email',
                    'value' => 'pika.chu@s2s.com',
                ),
                array(
                    'key'   => 'name',
                    'value' => 'Pika Chu',
                ),
                array(
                    'key'   => 'phone',
                    'value' => '12345',
                ),
                array(
                    'key'   => 'city',
                    'value' => 'Springfield',
                ),
                array(
                    'key'   => 'liststate',
                    'value' => 'OH',
                ),
                array(
                    'key'   => 'listcountry',
                    'value' => 'US',
                ),
                array(
                    'key'   => 'zip',
                    'value' => '4321',
                ),
                array(
                    'key'   => 'gender',
                    'value' => 'M',
                ),
            ),
        );
    }
}
