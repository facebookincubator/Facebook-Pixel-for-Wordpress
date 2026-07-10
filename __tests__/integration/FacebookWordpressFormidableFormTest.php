<?php
/**
 * Copyright (C) 2017-present, Meta, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the License.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Tests\Integration;

use FacebookPixelPlugin\Integration\FacebookWordpressFormidableForm;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormEntryValues;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormField;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormFieldValue;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressFormidableFormTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressFormidableFormTest extends FacebookWordpressTestBase {

    /**
     * Builds the integration wired to the shared signals double.
     *
     * @return FacebookWordpressFormidableForm
     */
    private function make_integration() {
        return new FacebookWordpressFormidableForm( $this->make_signals() );
    }

    /**
     * Invokes the protected set_up_tracking() (the new equivalent of the legacy
     * static inject_pixel_code()).
     *
     * @param FacebookWordpressFormidableForm $obj The integration.
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
     * Overloads \FrmEntryValues so `new \FrmEntryValues($entry_id)` yields a
     * double whose get_field_values() returns the given field values.
     *
     * @param array $field_values The mock field values.
     * @return void
     */
    private function mock_frm_entry_values( $field_values ) {
        $entry_values = \Mockery::mock( 'overload:FrmEntryValues' );
        $entry_values->shouldReceive( 'get_field_values' )->andReturn( $field_values );
    }

    /**
     * Builds the mock submitted field values (email/name/last/phone/address).
     *
     * @return MockFormidableFormFieldValue[] The mock field values.
     */
    private function make_field_values() {
        return array(
            new MockFormidableFormFieldValue(
                new MockFormidableFormField( 'email', null, null ),
                'pika.chu@s2s.com'
            ),
            new MockFormidableFormFieldValue(
                new MockFormidableFormField( 'text', 'Name', 'First' ),
                'Pika'
            ),
            new MockFormidableFormFieldValue(
                new MockFormidableFormField( 'text', 'Last', 'Last' ),
                'Chu'
            ),
            new MockFormidableFormFieldValue(
                new MockFormidableFormField( 'phone', null, null ),
                '123456'
            ),
            new MockFormidableFormFieldValue(
                new MockFormidableFormField( 'address', null, null ),
                array(
                    'city'    => 'Springfield',
                    'state'   => 'Ohio',
                    'zip'     => '45501',
                    'country' => 'United States',
                )
            ),
        );
    }

    /**
     * Tests set_up_tracking wires the entry-creation hook for a non-internal
     * user.
     *
     * @return void
     */
    public function testSetUpTrackingAddsHooks() {
        self::mockIsInternalUser( false );

        $obj = $this->make_integration();
        \WP_Mock::expectActionAdded(
            'frm_after_create_entry',
            array( $obj, 'capture_submitted_form' ),
            20,
            2
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
            'frm_after_create_entry',
            array( $obj, 'capture_submitted_form' )
        );

        $this->set_up_tracking( $obj );

        $this->assertConditionsMet();
    }

    /**
     * Tests that a created entry tracks a Lead with the extracted + normalized
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

        $this->mock_frm_entry_values( $this->make_field_values() );

        $this->make_integration()->capture_submitted_form( 1, 1 );

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
        $this->assertEquals( '45501', $event->getUserData()->getZipCode() );
        // 'United States' is not a 2-letter ISO code, so it is dropped.
        $this->assertNull( $event->getUserData()->getCountryCode() );
        $this->assertEquals(
            'formidable-lite',
            $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
        );
        $this->assertEquals( 'TEST_REFERER', $event->getEventSourceUrl() );

        // The browser event is queued for the footer render.
        $this->assertCount( 1, $this->enqueued_events );
        $this->assertEquals( 'Lead', $this->enqueued_events[0]->getEventName() );
    }
}
