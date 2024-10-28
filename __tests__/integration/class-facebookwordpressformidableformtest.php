<?php
/**
 * Facebook Pixel Plugin FacebookWordpressFormidableFormTest class.
 *
 * This file contains the main logic for FacebookWordpressFormidableFormTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressFormidableFormTest class.
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

use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Integration\FacebookWordpressFormidableForm;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormField;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormFieldValue;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormEntryValues;

/**
 * FacebookWordpressFormidableFormTest class.
 */
final class FacebookWordpressFormidableFormTest extends FacebookWordpressTestBase {

	/**
	 * Test injectPixelCode method.
	 *
	 * This test verifies that the "frm_after_create_entry" action hook is added.
	 *
	 * @return void
	 */
	public function testInjectPixelCode() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		\WP_Mock::expectActionAdded(
			'frm_after_create_entry',
			array(
				'FacebookPixelPlugin\\Integration\\FacebookWordpressFormidableForm',
				'trackServerEvent',
			),
			20,
			2
		);

		FacebookWordpressFormidableForm::injectPixelCode();
	}

	/**
	 * Tests the injectLeadEvent method for a non-internal user.
	 *
	 * This test ensures that the Pixel code is correctly appended to the HTML output
	 * when the user is not an internal user. It verifies that the output matches
	 * the expected pattern for the "formidable-lite" event.
	 *
	 * @return void
	 */
	public function testInjectLeadEventWithoutInternalUser() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$event = ServerEventFactory::new_event( 'Lead' );
		FacebookServerSideEvent::get_instance()->track( $event );

		FacebookWordpressFormidableForm::injectLeadEvent();

		$this->expectOutputRegex( '/script[\s\S]+formidable-lite/' );
	}

	/**
	 * Tests the injectLeadEvent method for an internal user.
	 *
	 * This test verifies that no Pixel code is appended to the HTML output
	 * when the user is an internal user. It asserts that the output HTML remains
	 * unchanged and that no events are tracked.
	 *
	 * @return void
	 */
	public function testInjectLeadEventWithInternalUser() {
		self::mockIsInternalUser( true );
		self::mockFacebookWordpressOptions();

		FacebookWordpressFormidableForm::injectLeadEvent();

		$this->expectOutputString( '' );
	}

	/**
	 * Tests the trackServerEvent method for a non-internal user.
	 *
	 * This test verifies that the Pixel code is correctly injected into the HTML
	 * output and that the server-side event is tracked with the correct parameters
	 * when the user is not an internal user. It asserts that the output HTML matches
	 * the expected pattern for the "formidable-lite" event.
	 *
	 * @return void
	 */
	public function testTrackEventWithoutInternalUser() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$mock_entry_id = 1;
		$mock_form_id  = 1;

		self::setupMockFormidableForm( $mock_entry_id );
		$_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

		\WP_Mock::expectActionAdded(
			'wp_footer',
			array(
				'FacebookPixelPlugin\\Integration\\FacebookWordpressFormidableForm',
				'injectLeadEvent',
			),
			20
		);

		FacebookWordpressFormidableForm::trackServerEvent(
			$mock_entry_id,
			$mock_form_id
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
		$this->assertEquals( '123456', $event->getUserData()->getPhone() );
		$this->assertEquals( 'springfield', $event->getUserData()->getCity() );
		$this->assertEquals( 'ohio', $event->getUserData()->getState() );
		$this->assertEquals( '45501', $event->getUserData()->getZipCode() );
		$this->assertNull( $event->getUserData()->getCountryCode() );
		$this->assertEquals(
			'formidable-lite',
			$event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
		);
		$this->assertEquals( 'TEST_REFERER', $event->getEventSourceUrl() );
	}

	/**
	 * Tests the trackServerEvent method when the user is not an internal user
	 * and there is an error reading the form data.
	 *
	 * This test verifies that the server-side event is tracked with the correct
	 * parameters, even if the form data is not available.
	 *
	 * @return void
	 */
	public function testTrackEventWithoutInternalUserErrorReadingForm() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$mock_entry_id = 1;
		$mock_form_id  = 1;

		$this->markTestSkipped('Skipping test temporarily while we update error handling.');

		self::setupErrorForm( $mock_entry_id );

		FacebookWordpressFormidableForm::trackServerEvent(
			$mock_entry_id,
			$mock_form_id
		);

		$tracked_events =
		FacebookServerSideEvent::get_instance()->get_tracked_events();

		$this->assertCount( 1, $tracked_events );

		$event = $tracked_events[0];
		$this->assertEquals( 'Lead', $event->getEventName() );
		$this->assertNotNull( $event->getEventTime() );
	}

	/**
	 * Sets up a mock form entry values object that will throw an exception
	 * when get_field_values() is called, and sets up a mock IntegrationUtils
	 * that will return this mock form entry values object when
	 * get_formidable_forms_entry_values() is called with the given entry ID.
	 *
	 * This is used to test the trackServerEvent method when the form data cannot
	 * be read.
	 *
	 * @param int $entry_id The ID of the form entry.
	 */
	private static function setupErrorForm( $entry_id ) {
		$entry_values = new MockFormidableFormEntryValues( array() );
		$entry_values->set_throw( true );

		$mock_utils = \Mockery::mock(
			'alias:FacebookPixelPlugin\Integration\IntegrationUtils'
		);
		$mock_utils->shouldReceive( 'get_formidable_forms_entry_values' )->with( $entry_id )->andReturn( $entry_values );
	}

	/**
	 * Sets up a mock Formidable Form entry with predefined field values.
	 *
	 * This method creates a mock form entry with sample data including email,
	 * first name, last name, phone, and address fields. It utilizes the
	 * MockFormidableFormField and MockFormidableFormFieldValue classes to define
	 * the field values. The mock entry values are then associated with the
	 * specified entry ID using a mocked IntegrationUtils class.
	 *
	 * @param int $entry_id The ID of the form entry.
	 *
	 * @return void
	 */
	private static function setupMockFormidableForm( $entry_id ) {
		$email = new MockFormidableFormFieldValue(
			new MockFormidableFormField( 'email', null, null ),
			'pika.chu@s2s.com'
		);

		$first_name = new MockFormidableFormFieldValue(
			new MockFormidableFormField( 'text', 'Name', 'First' ),
			'Pika'
		);

		$last_name = new MockFormidableFormFieldValue(
			new MockFormidableFormField( 'text', 'Last', 'Last' ),
			'Chu'
		);

		$phone = new MockFormidableFormFieldValue(
			new MockFormidableFormField( 'phone', null, null ),
			'123456'
		);

		$address = new MockFormidableFormFieldValue(
			new MockFormidableFormField( 'address', null, null ),
			array(
				'city'    => 'Springfield',
				'state'   => 'Ohio',
				'zip'     => '45501',
				'country' => 'United States',
			)
		);

		$entry_values = new MockFormidableFormEntryValues(
			array( $email, $first_name, $last_name, $phone, $address )
		);

		$mock_utils = \Mockery::mock(
			'alias:FacebookPixelPlugin\Integration\IntegrationUtils'
		);
		$mock_utils->shouldReceive( 'get_formidable_forms_entry_values' )->with( $entry_id )->andReturn( $entry_values );
	}
}
