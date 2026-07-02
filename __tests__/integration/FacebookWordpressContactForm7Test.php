<?php
/**
 * Facebook Pixel Plugin FacebookWordpressContactForm7Test class.
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

use FacebookPixelPlugin\Core\Signals;
use FacebookPixelPlugin\Core\EventDataBuilder;
use FacebookPixelPlugin\Core\AjaxFilterDelivery;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Integration\FacebookWordpressContactForm7;
use FacebookPixelPlugin\Tests\Mocks\MockContactForm7;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressContactForm7Test class.
 *
 * These tests assert the same observable behavior as before the Signals
 * refactor: a successful Contact Form 7 submission ends up tracking a Lead
 * server-side event carrying the correct user/custom data. The only change is
 * the entry point — read_form_data plus the real Signals dispatch path instead
 * of the old trackServerEvent static method.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressContactForm7Test extends FacebookWordpressTestBase {

  /**
   * Builds a CF7 integration wired to a real Signals dispatcher, so a
   * dispatched event actually tracks through FacebookServerSideEvent.
   *
   * @return array [ FacebookWordpressContactForm7, Signals ]
   */
  private function makeIntegration() {
    $signals     = new Signals();
    $integration = new FacebookWordpressContactForm7(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * Stubs the JSON/sanitize helpers the dispatch/render path relies on.
   *
   * @return void
   */
  private function mockRenderHelpers() {
    \WP_Mock::userFunction(
      'wp_json_encode',
      array(
        'args'   => array( \Mockery::type( 'array' ), \Mockery::type( 'int' ) ),
        'return' => function ( $data, $options ) {
          return json_encode( $data );
        },
      )
    );
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );
  }

  /**
   * inject_pixel_code registers the Lead event on Signals (with the AJAX
   * delivery) and the front-end mail-sent listener.
   *
   * @return void
   */
  public function testInjectPixelCodeRegistersEventAndListener() {
    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressContactForm7(
      $signals,
      new EventDataBuilder()
    );

    $signals->expects( $this->once() )
      ->method( 'on' )
      ->with(
        'wpcf7_submit',
        'Lead',
        $this->isType( 'callable' ),
        $this->isInstanceOf( AjaxFilterDelivery::class ),
        'contact-form-7',
        10,
        2
      );

    \WP_Mock::expectActionAdded(
      'wp_footer',
      array( $integration, 'inject_mail_sent_listener' ),
      10,
      2
    );

    $integration->inject_pixel_code();

    $this->assertConditionsMet();
  }

  /**
   * A dispatched Lead tracks a Lead server event carrying the submitter's
   * user data and the contact-form-7 tracking property — the same outcome the
   * old trackServerEvent path asserted.
   *
   * @return void
   */
  public function testTrackServerEventWithoutInternalUser() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();
    $this->mockRenderHelpers();

    $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

    list( $integration, $signals ) = $this->makeIntegration();

    $form = $this->createMockForm();
    $data = $integration->read_form_data(
      $form,
      array( 'status' => 'mail_sent' )
    );
    $signals->dispatch(
      'Lead',
      $data,
      FacebookWordpressContactForm7::TRACKING_NAME
    );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 1, $tracked_events );

    $event = $tracked_events[0];
    $this->assertEquals( 'Lead', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
    $this->assertEquals(
      'pika.chu@s2s.com',
      $event->getUserData()->getEmail()
    );
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
   * A dispatched Lead still tracks a Lead server event when the form carries
   * no recognizable name/email/phone tags — mirrors the old
   * "without form data" case.
   *
   * @return void
   */
  public function testTrackServerEventWithoutFormData() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();
    $this->mockRenderHelpers();

    list( $integration, $signals ) = $this->makeIntegration();

    $form = new MockContactForm7();
    $data = $integration->read_form_data(
      $form,
      array( 'status' => 'mail_sent' )
    );
    $signals->dispatch(
      'Lead',
      $data,
      FacebookWordpressContactForm7::TRACKING_NAME
    );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 1, $tracked_events );

    $event = $tracked_events[0];
    $this->assertEquals( 'Lead', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
  }

  /**
   * read_form_data returns null (nothing dispatched) when the mail did not
   * send, for every non-success CF7 status.
   *
   * @return void
   */
  public function testReadFormDataSkipsWhenMailFails() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();

    $bad_statuses = array(
      'validation_failed',
      'acceptance_missing',
      'spam',
      'aborted',
      'mail_failed',
    );

    list( $integration ) = $this->makeIntegration();
    $form                = $this->createMockForm();

    foreach ( $bad_statuses as $status ) {
      $this->assertNull(
        $integration->read_form_data( $form, array( 'status' => $status ) )
      );
    }

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 0, $tracked_events );
  }

  /**
   * read_form_data returns null (nothing dispatched) when the form is empty.
   *
   * @return void
   */
  public function testReadFormDataSkipsWhenFormEmpty() {
    list( $integration ) = $this->makeIntegration();

    $this->assertNull(
      $integration->read_form_data( null, array( 'status' => 'mail_sent' ) )
    );
  }

  /**
   * Creates a mock CF7 form with sample email/name/phone tags (which also
   * populate $_POST).
   *
   * @return MockContactForm7
   */
  private function createMockForm() {
    $mock_form = new MockContactForm7();
    $mock_form->add_tag( 'email', 'your-email', 'pika.chu@s2s.com' );
    $mock_form->add_tag( 'text', 'your-name', 'Pika Chu' );
    $mock_form->add_tag( 'tel', 'your-phone-number', '12223334444' );
    return $mock_form;
  }
}
