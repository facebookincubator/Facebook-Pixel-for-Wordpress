<?php
/**
 * Facebook Pixel Plugin FacebookWordpressContactForm7Test class.
 *
 * This file contains the main logic for FacebookWordpressContactForm7Test.
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

use FacebookPixelPlugin\Integration\FacebookWordpressContactForm7;
use FacebookPixelPlugin\Tests\Mocks\MockContactForm7;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressContactForm7Test class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressContactForm7Test extends FacebookWordpressTestBase {

    /**
     * Builds the integration wired to the shared signals double.
     *
     * @return FacebookWordpressContactForm7
     */
    private function make_integration() {
        return new FacebookWordpressContactForm7( $this->make_signals() );
    }

    /**
     * Invokes the protected set_up_tracking() (the new equivalent of the legacy
     * static inject_pixel_code()).
     *
     * @param FacebookWordpressContactForm7 $obj The integration.
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
            'wpcf7_submit',
            array( $obj, 'capture_submitted_form' ),
            10,
            2
        );

        $this->set_up_tracking( $obj );

        $this->assertHooksAdded();
        $this->assertCount( 1, $this->registered_ajax_dom );
        $this->assertStringContainsString(
            'wpcf7mailsent',
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
            'wpcf7_submit',
            array( $obj, 'capture_submitted_form' )
        );

        $this->set_up_tracking( $obj );

        $this->assertEmpty( $this->registered_ajax_dom );
    }

    /**
     * Tests a successful (mail_sent) submission tracks a Lead with the extracted
     * PII + attribution, and registers the AJAX response filter.
     *
     * @return void
     */
    public function testTrackServerEventWithoutInternalUser() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_wp_functions();
        \WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => true ) );
        $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

        \WP_Mock::expectFilterAdded(
            'wpcf7_feedback_response',
            \WP_Mock\Functions::type( 'callable' ),
            20
        );

        $this->make_integration()->capture_submitted_form(
            $this->create_mock_form(),
            array(
                'status'  => 'mail_sent',
                'message' => 'ok',
            )
        );

        $this->assertHooksAdded();
        $this->assertCount( 1, $this->captured_events );
        $event = $this->captured_events[0];

        $this->assertEquals( 'Lead', $event->getEventName() );
        $this->assertNotNull( $event->getEventTime() );
        $this->assertEquals( 'pika.chu@s2s.com', $event->getUserData()->getEmail() );
        $this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
        $this->assertEquals( 'chu', $event->getUserData()->getLastName() );
        $this->assertEquals( '12223334444', $event->getUserData()->getPhone() );
        $this->assertEquals(
            'contact-form-7',
            $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
        );
        $this->assertEquals( 'TEST_REFERER', $event->getEventSourceUrl() );
    }

    /**
     * Tests that a submission with no form fields still tracks a Lead (legacy
     * "without form data" behavior).
     *
     * @return void
     */
    public function testTrackServerEventWithoutFormData() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_wp_functions();
        \WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => true ) );

        $this->make_integration()->capture_submitted_form(
            new MockContactForm7(),
            array(
                'status'  => 'mail_sent',
                'message' => 'ok',
            )
        );

        $this->assertCount( 1, $this->captured_events );
        $this->assertEquals( 'Lead', $this->captured_events[0]->getEventName() );
    }

    /**
     * Tests that failed-submission statuses do not track any event (legacy
     * mail-fails behavior).
     *
     * @return void
     */
    public function testMailFailedStatusesDoNotTrack() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();

        $bad_statuses = array(
            'validation_failed',
            'acceptance_missing',
            'spam',
            'aborted',
            'mail_failed',
        );

        $integration = $this->make_integration();
        foreach ( $bad_statuses as $status ) {
            $integration->capture_submitted_form(
                $this->create_mock_form(),
                array(
                    'status'  => $status,
                    'message' => 'Error bad status',
                )
            );
        }

        $this->assertCount( 0, $this->captured_events );
        $this->assertCount( 0, $this->enqueued_events );
    }

    /**
     * Tests that a mail_sent submission still tracks a Lead when reading the
     * form fields fails (legacy "error reading data" behavior).
     *
     * @return void
     */
    public function testTrackServerEventErrorReadingData() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_wp_functions();
        \WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => true ) );

        $form = new MockContactForm7();
        $form->set_throw( true );

        $this->make_integration()->capture_submitted_form(
            $form,
            array(
                'status'  => 'mail_sent',
                'message' => 'ok',
            )
        );

        $this->assertCount( 1, $this->captured_events );
        $this->assertEquals( 'Lead', $this->captured_events[0]->getEventName() );
    }

    /**
     * Tests that the AJAX feedback response is enriched with the tracking code
     * under the container key (new equivalent of the legacy injectLeadEvent).
     *
     * @return void
     */
    public function testAddTrackingCodeToAjaxResponseAddsCode() {
        $response = $this->make_integration()->add_tracking_code_to_ajax_response(
            array(),
            "fbq('track', 'Lead', {});",
            'contact_form_7_pxl_container'
        );

        $this->assertArrayHasKey( 'contact_form_7_pxl_container', $response );
        $this->assertStringContainsString(
            "fbq('track'",
            $response['contact_form_7_pxl_container']
        );
    }

    /**
     * Tests that an already-populated response key is left untouched.
     *
     * @return void
     */
    public function testInjectPixelCodeIntoResponseLeavesExistingUntouched() {
        $response = $this->make_integration()->add_tracking_code_to_ajax_response(
            array( 'contact_form_7_pxl_container' => 'EXISTING' ),
            "fbq('track', 'Lead', {});",
            'contact_form_7_pxl_container'
        );

        $this->assertEquals( 'EXISTING', $response['contact_form_7_pxl_container'] );
    }

    /**
     * Extracts lead data from a submitted Contact Form 7 form and asserts each
     * field maps to the correct Lead parameter (email by type, name by the
     * 'name'-labelled text field split into first/last, phone by 'tel' type).
     *
     * @return void
     */
    public function testExtractLeadData() {
        \WP_Mock::userFunction(
            'sanitize_text_field',
            array(
                'args'   => array( \Mockery::any() ),
                'return' => function ( $input ) {
                    return $input;
                },
            )
        );

        $lead = $this->make_integration()->extract_lead_data( $this->create_mock_form() );

        $this->assertEquals( 'pika.chu@s2s.com', $lead['email'] );
        $this->assertEquals( 'Pika', $lead['first_name'] );
        $this->assertEquals( 'Chu', $lead['last_name'] );
        $this->assertEquals( '12223334444', $lead['phone'] );
    }

    /**
     * Creates a mock Contact Form 7 form with email/text(name)/tel tags. Each
     * add_tag() also populates $_POST[name], mirroring a real submission that
     * the extractor reads via WordPressUtils::get_from_post().
     *
     * @return MockContactForm7 A mock form with sample tags.
     */
    private function create_mock_form() {
        $mock_form = new MockContactForm7();

        $mock_form->add_tag( 'email', 'your-email', 'pika.chu@s2s.com' );
        $mock_form->add_tag( 'text', 'your-name', 'Pika Chu' );
        $mock_form->add_tag( 'tel', 'your-phone-number', '12223334444' );

        return $mock_form;
    }
}
