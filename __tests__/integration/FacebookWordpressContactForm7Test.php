<?php
/**
 * Facebook Pixel Plugin FacebookWordpressContactForm7Test class.
 *
 * This file contains the main logic for FacebookWordpressContactForm7Test.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressContactForm7Test class.
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

use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Integration\FacebookWordpressContactForm7;
use FacebookPixelPlugin\Tests\Mocks\MockContactForm7;
use FacebookPixelPlugin\Tests\Mocks\MockContactForm7Tag;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\ServerEventFactory;

/**
 * FacebookWordpressContactForm7Test class.
 */
final class FacebookWordpressContactForm7Test extends FacebookWordpressTestBase {

	/**
	 * Tests the injectLeadEvent method when the user is not an internal user.
	 *
	 * This test verifies that the Pixel code is appended to the HTML output
	 * and that the server-side event is tracked with the correct parameters.
	 *
	 * @return void
	 */
	public function testInjectLeadEventWithoutInternalUser() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$mock_response = array(
			'status'  => 'mail_sent',
			'message' => 'Thank you for your message',
		);

		$event = ServerEventFactory::new_event( 'Lead' );
		FacebookServerSideEvent::get_instance()->track( $event );

		$response =
		FacebookWordpressContactForm7::injectLeadEvent( $mock_response, null );
		$this->assertMatchesRegularExpression(
			'/Lead[\s\S]+contact-form-7/',
			$response['fb_pxl_code']
		);
	}

	/**
	 * Tests the trackServerEvent method when the user is not an internal user.
	 *
	 * This test verifies that the Pixel code is appended to the HTML output
	 * and that the server-side event is tracked with the correct parameters.
	 *
	 * @return void
	 */
	public function testTrackServerEventWithoutInternalUser() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$mock_result = array(
			'status'  => 'mail_sent',
			'message' => 'Thank you for your message',
		);

		$mock_form               = $this->createMockForm();
		$_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

		\WP_Mock::expectActionAdded(
			'wpcf7_feedback_response',
			array(
				'FacebookPixelPlugin\\Integration\\FacebookWordpressContactForm7',
				'injectLeadEvent',
			),
			20,
			2
		);

		$result =
		FacebookWordpressContactForm7::trackServerEvent( $mock_form, $mock_result );

		$tracked_events =
		FacebookServerSideEvent::get_instance()->get_tracked_events();

		$this->assertCount( 1, $tracked_events );

		$event = $tracked_events[0];
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
	 * Tests the trackServerEvent method when the user is not an internal user and
	 * the form data is not available. This test verifies that the server-side event
	 * is tracked with the correct parameters.
	 *
	 * @return void
	 */
	public function testTrackServerEventWithoutFormData() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$mock_result = array(
			'status'  => 'mail_sent',
			'message' => 'Thank you for your message',
		);

		$mock_form = $this->createMockForm();

		\WP_Mock::expectActionAdded(
			'wpcf7_feedback_response',
			array(
				'FacebookPixelPlugin\\Integration\\FacebookWordpressContactForm7',
				'injectLeadEvent',
			),
			20,
			2
		);

		$result = FacebookWordpressContactForm7::trackServerEvent(
			$mock_form,
			$mock_result
		);

		$tracked_events =
		FacebookServerSideEvent::get_instance()->get_tracked_events();

		$this->assertCount( 1, $tracked_events );

		$event = $tracked_events[0];
		$this->assertEquals( 'Lead', $event->getEventName() );
		$this->assertNotNull( $event->getEventTime() );
	}

	/**
	 * Tests the trackServerEvent method when an error occurs while reading
	 * the form data.
	 *
	 * This test verifies that the Pixel code is appended to the HTML output
	 * and that the server-side event is tracked with the correct parameters.
	 *
	 * @return void
	 */
	public function testTrackServerEventErrorReadingData() {
		$this->markTestSkipped('Skipping test temporarily while we update error handling.');
		
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$mock_result = array(
			'status'  => 'mail_sent',
			'message' => 'Thank you for your message',
		);

		$mock_form = $this->createMockForm();
		$mock_form->set_throw( true );

		\WP_Mock::expectActionAdded(
			'wpcf7_feedback_response',
			array(
				'FacebookPixelPlugin\\Integration\\FacebookWordpressContactForm7',
				'injectLeadEvent',
			),
			20,
			2
		);

		$result =
		FacebookWordpressContactForm7::trackServerEvent( $mock_form, $mock_result );

		$tracked_events =
		FacebookServerSideEvent::get_instance()->get_tracked_events();

		$this->assertCount( 1, $tracked_events );

		$event = $tracked_events[0];
		$this->assertEquals( 'Lead', $event->getEventName() );
		$this->assertNotNull( $event->getEventTime() );
	}

	/**
	 * Tests the injectLeadEvent method when the user is an internal user.
	 *
	 * This test verifies that the output HTML does not contain the Pixel code
	 * when the user is an internal user.
	 *
	 * @return void
	 */
	public function testInjectLeadEventWithInternalUser() {
		self::mockIsInternalUser( true );

		$mock_response = array(
			'status'  => 'mail_sent',
			'message' => 'Thank you for your message',
		);

		$response =
		FacebookWordpressContactForm7::injectLeadEvent( $mock_response, null );
		$this->assertArrayNotHasKey( 'fb_pxl_code', $response );
	}

	/**
	 * Tests the injectLeadEvent method when the mail fails.
	 *
	 * This test verifies that no server-side events are tracked when the mail
	 * status indicates failure (e.g., validation failed, spam, mail failed, etc.).
	 * It asserts that the tracked events list is empty when such statuses occur.
	 *
	 * @return void
	 */
	public function testInjectLeadEventWhenMailFails() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$bad_statuses = array(
			'validation_failed',
			'acceptance_missing',
			'spam',
			'aborted',
			'mail_failed',
		);

		$mock_form = new MockContactForm7();
		$mock_form->set_throw( true );

		foreach ( $bad_statuses as $status ) {
			$mock_result = array(
				'status'  => $status,
				'message' => 'Error bad status',
			);
			FacebookWordpressContactForm7::trackServerEvent( $mock_form, $mock_result );
		}

		$tracked_events =
		FacebookServerSideEvent::get_instance()->get_tracked_events();

		$this->assertCount( 0, $tracked_events );
	}

	/**
	 * Creates a mock form object with some sample form tags.
	 *
	 * The sample form tags include email, text, and tel fields, with some
	 * fictional sample data. This mock form object is used in the tests to
	 * simulate a form submission.
	 *
	 * @return MockContactForm7 A mock form object with sample form tags.
	 */
	private function createMockForm() {
		$mock_form = new MockContactForm7();

		$mock_form->add_tag( 'email', 'your-email', 'pika.chu@s2s.com' );
		$mock_form->add_tag( 'text', 'your-name', 'Pika Chu' );
		$mock_form->add_tag( 'tel', 'your-phone-number', '12223334444' );

		return $mock_form;
	}
}
