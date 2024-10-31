<?php
/**
 * Facebook Pixel Plugin FacebookWordpressNinjaFormsTest class.
 *
 * This file contains the main logic for FacebookWordpressNinjaFormsTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressNinjaFormsTest class.
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

use FacebookPixelPlugin\Integration\FacebookWordpressNinjaForms;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;

/**
 * FacebookWordpressNinjaFormsTest class.
 */
final class FacebookWordpressNinjaFormsTest extends FacebookWordpressTestBase {
	/**
	 * Tests that the inject_pixel_code method adds the correct hooks to WordPress.
	 *
	 * @return void
	 */
	public function testInjectPixelCode() {
		\WP_Mock::expectActionAdded(
			'ninja_forms_submission_actions',
			array( FacebookWordpressNinjaForms::class, 'injectLeadEvent' ),
			10,
			3
		);

		FacebookWordpressNinjaForms::inject_pixel_code();
		$this->assertHooksAdded();
	}

	/**
	 * Tests the injectLeadEvent method when the user is not an internal user.
	 *
	 * This test verifies that the Pixel code is appended to the HTML output
	 * and that the server-side event is tracked with the correct parameters.
	 * It ensures that the 'Lead' event is recorded with the expected user data,
	 * including email, first name, last name, phone number, city, state, country,
	 * zip code, and gender when the Ninja Forms integration is triggered.
	 * It also checks that the event source URL is correctly set.
	 *
	 * @return void
	 */
	public function testInjectLeadEventWithoutInternalUser() {
		parent::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$mock_actions = array(
			array(
				'id'       => 1,
				'settings' => array(
					'type'        => 'successmessage',
					'success_msg' => 'successful',
				),
			),
		);

		$mock_form_data          = $this->getMockFormData();
		$_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

		$result = FacebookWordpressNinjaForms::injectLeadEvent(
			$mock_actions,
			null,
			$mock_form_data
		);

		$this->assertNotEmpty( $result );
		$this->assertArrayHasKey( 'settings', $result[0] );
		$this->assertArrayHasKey( 'success_msg', $result[0]['settings'] );
		$msg = $result[0]['settings']['success_msg'];
		$this->assertMatchesRegularExpression(
			'/ninja-forms[\s\S]+End Meta Pixel Event Code/',
			$msg
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
	 * Tests the injectLeadEvent method when the user is an internal user.
	 *
	 * This test verifies that the output HTML remains unchanged and that no events are tracked.
	 *
	 * @return void
	 */
	public function testInjectLeadEventWithInternalUser() {
		parent::mockIsInternalUser( true );

		$result = FacebookWordpressNinjaForms::injectLeadEvent(
			'mock_actions',
			'mock_form_cache',
			'mock_form_data'
		);

		$this->assertEquals( 'mock_actions', $result );
	}

	/**
	 * Creates a mock form data object with some sample form tags.
	 *
	 * @return array
	 */
	private function getMockFormData() {
		$email   = array(
			'key'   => 'email',
			'value' => 'pika.chu@s2s.com',
		);
		$name    = array(
			'key'   => 'name',
			'value' => 'Pika Chu',
		);
		$phone   = array(
			'key'   => 'phone',
			'value' => '12345',
		);
		$city    = array(
			'key'   => 'city',
			'value' => 'Springfield',
		);
		$state   = array(
			'key'   => 'liststate',
			'value' => 'OH',
		);
		$country = array(
			'key'   => 'listcountry',
			'value' => 'US',
		);
		$zip     = array(
			'key'   => 'zip',
			'value' => '4321',
		);
		$gender  = array(
			'key'   => 'gender',
			'value' => 'M',
		);
		$fields  = array(
			$email,
			$name,
			$phone,
			$city,
			$state,
			$country,
			$zip,
			$gender,
		);
		return array( 'fields' => $fields );
	}
}
