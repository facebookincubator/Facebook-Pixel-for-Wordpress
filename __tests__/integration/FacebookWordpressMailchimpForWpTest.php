<?php
/**
 * Facebook Pixel Plugin FacebookWordpressMailchimpForWpTest class.
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
use FacebookPixelPlugin\Core\FooterDelivery;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Integration\FacebookWordpressMailchimpForWp;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressMailchimpForWpTest class.
 *
 * These tests assert the same observable behavior as before the Signals
 * refactor: a Mailchimp subscribe tracks a Lead server-side event carrying the
 * correct user data. The only change is the entry point — read_form_data plus
 * the real Signals dispatch path instead of the old static injectLeadEvent
 * method.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressMailchimpForWpTest
  extends FacebookWordpressTestBase {

  /**
   * Builds a Mailchimp integration wired to a real Signals dispatcher, so a
   * dispatched event actually tracks through FacebookServerSideEvent.
   *
   * @return array [ FacebookWordpressMailchimpForWp, Signals ]
   */
  private function makeIntegration() {
    $signals     = new Signals();
    $integration = new FacebookWordpressMailchimpForWp(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * Stubs the sanitize/JSON helpers the read/dispatch path relies on.
   *
   * @return void
   */
  private function mockRenderHelpers() {
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
      'sanitize_email',
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
   * set_up_tracking registers the Lead event with a footer delivery.
   *
   * @return void
   */
  public function testInjectPixelCodeRegistersEvent() {
    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressMailchimpForWp(
      $signals,
      new EventDataBuilder()
    );

    $signals->expects( $this->once() )
      ->method( 'on' )
      ->with(
        'mc4wp_form_subscribed',
        'Lead',
        $this->isType( 'callable' ),
        $this->isInstanceOf( FooterDelivery::class ),
        'mailchimp-for-wp',
        10,
        1
      );

    $integration->set_up_tracking();

    $this->assertConditionsMet();
  }

  /**
   * A dispatched Lead tracks a Lead server event with the subscribe user data.
   *
   * @return void
   */
  public function testLeadEventWithoutInternalUser() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();
    $this->mockRenderHelpers();

    $_POST['EMAIL']          = 'pika.chu@s2s.com';
    $_POST['FNAME']          = 'Pika';
    $_POST['LNAME']          = 'Chu';
    $_POST['PHONE']          = '123456';
    $_POST['ADDRESS']        = array(
      'city'    => 'Springfield',
      'state'   => 'Ohio',
      'zip'     => '54321',
      'country' => 'US',
    );
    $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

    list( $integration, $signals ) = $this->makeIntegration();

    $data = $integration->read_form_data();
    $signals->dispatch(
      'Lead',
      $data,
      FacebookWordpressMailchimpForWp::TRACKING_NAME
    );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 1, $tracked_events );

    $event = $tracked_events[0];
    $this->assertEquals( 'Lead', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );

    $user_data = $event->getUserData();
    $this->assertEquals( 'pika.chu@s2s.com', $user_data->getEmail() );
    $this->assertEquals( 'pika', $user_data->getFirstName() );
    $this->assertEquals( 'chu', $user_data->getLastName() );
    $this->assertEquals( '123456', $user_data->getPhone() );
    $this->assertEquals( 'springfield', $user_data->getCity() );
    $this->assertEquals( 'ohio', $user_data->getState() );
    $this->assertEquals( '54321', $user_data->getZipCode() );
    $this->assertEquals( 'us', $user_data->getCountryCode() );

    $this->assertEquals(
      'mailchimp-for-wp',
      $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
    );
  }
}
