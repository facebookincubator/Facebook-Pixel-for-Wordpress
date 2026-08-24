<?php
/**
 * Facebook Pixel Plugin FacebookWordpressMailchimpForWpTest class.
 *
 * This file contains the main logic for FacebookWordpressMailchimpForWpTest.
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

use FacebookPixelPlugin\Integration\FacebookWordpressMailchimpForWp;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressMailchimpForWpTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressMailchimpForWpTest extends FacebookWordpressTestBase {

    /**
     * Builds the integration wired to the shared signals double.
     *
     * @return FacebookWordpressMailchimpForWp
     */
    private function make_integration() {
        return new FacebookWordpressMailchimpForWp( $this->make_signals() );
    }

    /**
     * Invokes the protected set_up_tracking() (the new equivalent of the legacy
     * static inject_pixel_code()).
     *
     * @param FacebookWordpressMailchimpForWp $obj The integration.
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
     * Builds a mock MC4WP form whose data carries the submitted fields.
     *
     * @return object The mock form.
     */
    private function make_form() {
        $form       = new \stdClass();
        $form->data = array(
            'EMAIL'   => 'pika.chu@s2s.com',
            'FNAME'   => 'Pika',
            'LNAME'   => 'Chu',
            'PHONE'   => '123456',
            'ADDRESS' => array(
                'city'    => 'Springfield',
                'state'   => 'Ohio',
                'zip'     => '54321',
                'country' => 'US',
            ),
        );
        return $form;
    }

    /**
     * Tests set_up_tracking wires the subscribe hook for a non-internal user.
     *
     * @return void
     */
    public function testSetUpTrackingAddsHooks() {
        self::mockIsInternalUser( false );

        $obj = $this->make_integration();
        \WP_Mock::expectActionAdded(
            'mc4wp_form_subscribed',
            array( $obj, 'capture_submitted_form' ),
            11,
            1
        );

        $this->set_up_tracking( $obj );

        $this->assertHooksAdded();
    }

    /**
     * Tests that no hooks are wired for an internal user.
     *
     * @return void
     */
    public function testSetUpTrackingSkipsForInternalUser() {
        self::mockIsInternalUser( true );

        $obj = $this->make_integration();
        \WP_Mock::expectActionNotAdded(
            'mc4wp_form_subscribed',
            array( $obj, 'capture_submitted_form' )
        );

        $this->set_up_tracking( $obj );

        $this->assertConditionsMet();
    }

    /**
     * Tests that a subscribe tracks a Lead with the extracted + normalized
     * PII/attribution, and queues the browser event for the footer render.
     *
     * @return void
     */
    public function testTrackEventWithoutInternalUser() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
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
                'return' => function ( $data, $options = 0 ) {
                    return json_encode( $data );
                },
            )
        );
        $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

        $this->make_integration()->capture_submitted_form( $this->make_form() );

        $this->assertCount( 1, $this->captured_events );
        $event = $this->captured_events[0];

        $this->assertEquals( 'Lead', $event->getEventName() );
        $this->assertNotNull( $event->getEventTime() );
        $this->assertEquals( 'pika.chu@s2s.com', $event->getUserData()->getEmail() );
        $this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
        $this->assertEquals( 'chu', $event->getUserData()->getLastName() );
        $this->assertEquals( '123456', $event->getUserData()->getPhone() );
        $this->assertEquals( 'springfield', $event->getUserData()->getCity() );
        $this->assertEquals( 'ohio', $event->getUserData()->getState() );
        $this->assertEquals( '54321', $event->getUserData()->getZipCode() );
        $this->assertEquals( 'us', $event->getUserData()->getCountryCode() );
        $this->assertEquals(
            'mailchimp-for-wp',
            $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
        );
        $this->assertEquals( 'TEST_REFERER', $event->getEventSourceUrl() );

        // The browser event is queued for the footer render.
        $this->assertCount( 1, $this->enqueued_events );
        $this->assertEquals( 'Lead', $this->enqueued_events[0]->getEventName() );
    }
}
