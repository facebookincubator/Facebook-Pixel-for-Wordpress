<?php
/**
 * Facebook Pixel Plugin FacebookWordpressWPFormsTest class.
 *
 * This file contains the main logic for FacebookWordpressWPFormsTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressWPFormsTest class.
 *
 * @return void
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
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;

/**
 * FacebookWordpressWPFormsTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressWPFormsTest extends FacebookWordpressTestBase {
	/**
	 * Tests that the inject_pixel_code method adds the correct WordPress hook.
	 *
	 * This test verifies that the 'wpforms_process_before' action hook is added
	 * to trigger the 'trackEvent' method of the FacebookWordpressWPForms class.
	 *
	 * @return void
	 */
	public function testInjectPixelCode() {
		\WP_Mock::expectActionAdded(
			'wpforms_process_before',
			array( FacebookWordpressWPForms::class, 'trackEvent' ),
			20,
			2
		);

		FacebookWordpressWPForms::inject_pixel_code();
		$this->assertHooksAdded();
	}

	/**
	 * Tests the injectLeadEvent method when the user is not an internal user.
	 *
	 * This test verifies that the Pixel code is correctly appended to the HTML output
	 * when the user is not an internal user. It ensures that the output matches
	 * the expected pattern for the "wpforms-lite" event.
	 *
	 * @return void
	 */
	public function testInjectLeadEventWithoutInternalUser() {
		parent::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

        \WP_Mock::userFunction('sanitize_text_field', [
            'args' => [\Mockery::any()],
            'return' => function ($input) {
                return $input;
            }
        ]);

		$event = ServerEventFactory::new_event( 'Lead' );
		FacebookServerSideEvent::get_instance()->track( $event );

        \WP_Mock::userFunction('wp_json_encode', [
            'args' => [\Mockery::type('array'), \Mockery::type('int')],
            'return' => function($data, $options) {
                return json_encode($data);
            }
        ]);

		FacebookWordpressWPForms::injectLeadEvent();
		$this->expectOutputRegex(
			'/wpforms-lite[\s\S]+End Meta Pixel Event Code/'
		);
	}

	/**
	 * Tests the injectLeadEvent method when the user is an internal user.
	 *
	 * This test verifies that no Pixel code is appended to the HTML output
	 * when the user is an internal user. It asserts that the output HTML remains
	 * unchanged and that no events are tracked.
	 *
	 * @return void
	 */
	public function testInjectLeadEventWithInternalUser() {
		parent::mockIsInternalUser( true );
		self::mockFacebookWordpressOptions();

		FacebookWordpressWPForms::injectLeadEvent( 'mock_form_data' );
		$this->expectOutputString( '' );
	}

	/**
	 * Tests the trackEvent method for a non-internal user.
	 *
	 * This test verifies that the Pixel code is correctly injected into the HTML
	 * output and that the server-side event is tracked with the correct parameters
	 * when the user is not an internal user. It asserts that the output HTML matches
	 * the expected pattern for the "wpforms-lite" event.
	 *
	 * @return void
	 */
	public function testTrackEventWithoutInternalUser() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$mock_entry     = $this->createMockEntry();
		$mock_form_data = $this->createMockFormData();

        \WP_Mock::userFunction('sanitize_text_field', [
            'args' => [\Mockery::any()],
            'return' => function ($input) {
                return $input;
            }
        ]);

		\WP_Mock::expectActionAdded(
			'wp_footer',
			array(
				FacebookWordpressWPForms::class,
				'injectLeadEvent',
			),
			20
		);

		FacebookWordpressWPForms::trackEvent(
			$mock_entry,
			$mock_form_data
		);

		$tracked_events =
		FacebookServerSideEvent::get_instance()->get_tracked_events();

		$this->assertCount( 1, $tracked_events );

		$event = $tracked_events[0];
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
	}

	/**
	 * Tests the trackEvent method when the user is not an internal user,
	 * and the form data is provided in the simple format.
	 *
	 * This test verifies that the server-side event is tracked with the correct
	 * parameters when the user is not an internal user and the form data is
	 * provided in the simple format. It ensures that the output HTML matches the
	 * expected pattern for the "wpforms-lite" event.
	 *
	 * @return void
	 */
	public function testTrackEventWithoutInternalUserSimpleFormat() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$mock_entry              = $this->createMockEntry( true );
		$mock_form_data          = $this->createMockFormData( true );
		$_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

		\WP_Mock::expectActionAdded(
			'wp_footer',
			array(
				FacebookWordpressWPForms::class,
				'injectLeadEvent',
			),
			20
		);

        \WP_Mock::userFunction('sanitize_text_field', [
            'args' => [\Mockery::any()],
            'return' => function ($input) {
                return $input;
            }
        ]);

		FacebookWordpressWPForms::trackEvent(
			$mock_entry,
			$mock_form_data
		);

		$tracked_events =
		FacebookServerSideEvent::get_instance()->get_tracked_events();

		$this->assertCount( 1, $tracked_events );

		$event = $tracked_events[0];
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
		$this->assertEquals( 'TEST_REFERER', $event->getEventSourceUrl() );
	}

	/**
	 * Creates a mock entry with predefined field values.
	 *
	 * This method creates a mock entry with sample data including email,
	 * first name, last name, phone, and address fields. It utilizes the
	 * simple format or the first-last format for the name field, depending on
	 * the value of the $simple_format parameter.
	 *
	 * @param bool $simple_format Whether to use the simple format for the name field.
	 *
	 * @return array The mock entry with predefined field values.
	 */
	private function createMockEntry( $simple_format = false ) {
		return array(
			'fields' => array(
				'0' => $simple_format
					? 'Pika Chu'
					: array(
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
	 * Creates a mock form data object with predefined field values.
	 *
	 * This method creates a mock form data object with sample data including email,
	 * first name, last name, phone, and address fields. It utilizes the
	 * simple format or the first-last format for the name field, depending on
	 * the value of the $simple_format parameter.
	 *
	 * @param bool $simple_format Whether to use the simple format for the name field.
	 *
	 * @return array The mock form data object with predefined field values.
	 */
	private function createMockFormData( $simple_format = false ) {
		return array(
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
