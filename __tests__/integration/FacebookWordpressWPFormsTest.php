<?php
/**
 * Facebook Pixel Plugin FacebookWordpressWPFormsTest class.
 *
 * This file contains the main logic for FacebookWordpressWPFormsTest.
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

use FacebookPixelPlugin\Integration\FacebookWordpressWPForms;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressWPFormsTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressWPFormsTest extends FacebookWordpressTestBase {

    /**
     * Builds the integration wired to the shared signals double.
     *
     * @return FacebookWordpressWPForms
     */
    private function make_integration() {
        return new FacebookWordpressWPForms( $this->make_signals() );
    }

    /**
     * Invokes the protected set_up_tracking() (the new equivalent of the legacy
     * static inject_pixel_code()).
     *
     * @param FacebookWordpressWPForms $obj The integration.
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
            'wpforms_process_before',
            array( $obj, 'capture_submitted_form' ),
            20,
            2
        );

        $this->set_up_tracking( $obj );

        $this->assertHooksAdded();
        $this->assertCount( 1, $this->registered_ajax_dom );
        $this->assertStringContainsString(
            'wpformsAjaxSubmitSuccess',
            $this->registered_ajax_dom[0]
        );
        // The listener must read the same container key the AJAX response is
        // injected under (see testInjectPixelCodeIntoResponseAddsCode).
        $this->assertStringContainsString(
            FacebookWordpressWPForms::AJAX_PIXEL_CONTAINER,
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
            'wpforms_process_before',
            array( $obj, 'capture_submitted_form' )
        );

        $this->set_up_tracking( $obj );

        $this->assertEmpty( $this->registered_ajax_dom );
    }

    /**
     * Tests a first-last name submission tracks a Lead with the correct user
     * data + attribution (server), and queues the browser event (footer render).
     *
     * @return void
     */
    public function testTrackEventWithoutInternalUser() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_wp_functions();
        \WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => false ) );

        $this->make_integration()->capture_submitted_form(
            $this->create_mock_entry(),
            $this->create_mock_form_data()
        );

        $this->assertCount( 1, $this->captured_events );
        $event = $this->captured_events[0];

        $this->assertEquals( 'Lead', $event->getEventName() );
        $this->assertNotNull( $event->getEventTime() );
        $this->assertEquals( 'pika.chu@s2s.com', $event->getUserData()->getEmail() );
        $this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
        $this->assertEquals( 'chu', $event->getUserData()->getLastName() );
        $this->assertEquals( '1234567', $event->getUserData()->getPhone() );
        $this->assertEquals( 'us', $event->getUserData()->getCountryCode() );
        $this->assertEquals( 'springfield', $event->getUserData()->getCity() );
        $this->assertEquals( 'ohio', $event->getUserData()->getState() );
        $this->assertEquals( '45401', $event->getUserData()->getZipCode() );
        $this->assertEquals(
            'wpforms-lite',
            $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
        );

        // Non-AJAX submit: the browser event is queued for the footer render.
        $this->assertCount( 1, $this->enqueued_events );
        $this->assertEquals( 'Lead', $this->enqueued_events[0]->getEventName() );
    }

    /**
     * Tests a simple (single-string) name submission splits the name and uses
     * the referer as the event source URL.
     *
     * @return void
     */
    public function testTrackEventWithoutInternalUserSimpleFormat() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_wp_functions();
        \WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => false ) );
        $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

        $this->make_integration()->capture_submitted_form(
            $this->create_mock_entry( true ),
            $this->create_mock_form_data( true )
        );

        $event = $this->captured_events[0];
        $this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
        $this->assertEquals( 'chu', $event->getUserData()->getLastName() );
        $this->assertEquals( 'TEST_REFERER', $event->getEventSourceUrl() );
    }

    /**
     * Tests that an AJAX submission registers the pixel-code injection on the
     * WPForms AJAX response hooks (new equivalent of the legacy inject_pixel_code
     * filter-wiring test).
     *
     * @return void
     */
    public function testAjaxSubmitRegistersResponseFilters() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_wp_functions();
        \WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => true ) );

        \WP_Mock::expectFilterAdded(
            'wpforms_ajax_submit_success_response',
            \WP_Mock\Functions::type( 'callable' ),
            20
        );
        \WP_Mock::expectFilterAdded(
            'wpforms_ajax_submit_redirect',
            \WP_Mock\Functions::type( 'callable' ),
            20
        );

        $this->make_integration()->capture_submitted_form(
            $this->create_mock_entry(),
            $this->create_mock_form_data()
        );

        $this->assertHooksAdded();
    }

    /**
     * Tests that the AJAX response is enriched with the pixel code under the
     * container key (new equivalent of the legacy injectLeadEventAjax test).
     *
     * @return void
     */
    public function testInjectPixelCodeIntoResponseAddsCode() {
        $key      = FacebookWordpressWPForms::AJAX_PIXEL_CONTAINER;
        $response = $this->make_integration()->add_tracking_code_to_ajax_response(
            array(),
            "fbq('track', 'Lead', {});",
            $key
        );

        $this->assertArrayHasKey( $key, $response );
        $this->assertStringContainsString( "fbq('track'", $response[ $key ] );
    }

    /**
     * Tests that an already-populated response key is left untouched.
     *
     * @return void
     */
    public function testInjectPixelCodeIntoResponseLeavesExistingUntouched() {
        $key      = FacebookWordpressWPForms::AJAX_PIXEL_CONTAINER;
        $response = $this->make_integration()->add_tracking_code_to_ajax_response(
            array( $key => 'EXISTING' ),
            "fbq('track', 'Lead', {});",
            $key
        );

        $this->assertEquals( 'EXISTING', $response[ $key ] );
    }

    /**
     * Creates a mock WPForms entry (submitted values keyed by field id).
     *
     * @param bool $simple_format Whether the name field is a single string.
     * @return array The mock entry.
     */
    private function create_mock_entry( $simple_format = false ) {
        return array(
            'fields' => array(
                '0' => $simple_format ? 'Pika Chu' : array(
                    'first' => 'Pika',
                    'last'  => 'Chu',
                ),
                '1' => 'pika.chu@s2s.com',
                '2' => '1234567',
                '3' => array(
                    'country' => 'US',
                    'postal'  => '45401',
                    'state'   => 'Ohio',
                    'city'    => 'Springfield',
                ),
            ),
        );
    }

    /**
     * Creates a mock WPForms form-data schema (fields matched by 'type', as in
     * legacy). Only the top-level form 'id' is added, which real form data
     * carries and the extractor reads for form lookup.
     *
     * @param bool $simple_format Whether the name field uses the simple format.
     * @return array The mock form-data schema.
     */
    private function create_mock_form_data( $simple_format = false ) {
        return array(
            'id'     => '123',
            'fields' => array(
                array(
                    'type'   => 'name',
                    'id'     => '0',
                    'format' => $simple_format ? 'simple' : 'first-last',
                ),
                array(
                    'type' => 'email',
                    'id'   => '1',
                ),
                array(
                    'type' => 'phone',
                    'id'   => '2',
                ),
                array(
                    'type' => 'address',
                    'id'   => '3',
                ),
            ),
        );
    }
}
