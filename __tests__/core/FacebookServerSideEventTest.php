<?php
/**
 * Facebook Pixel Plugin FacebookServerSideEventTest class.
 *
 * This file contains the main logic for FacebookServerSideEventTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookServerSideEventTest class.
 *
 * @return void
 */

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
*/

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\FacebookSignalState;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\FacebookAds\Http\Exception\RequestException;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * Stub auth-failure exception that skips the parent constructor
 * (which would otherwise require a full ResponseInterface).
 */
class StubAuthRequestException extends RequestException {
    private $stub_status;
    public function __construct( $status_code ) {
        $this->stub_status = $status_code;
    }
    public function getHttpStatusCode() {
        return $this->stub_status;
    }
}

/**
 * FacebookServerSideEventTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookServerSideEventTest extends FacebookWordpressTestBase {
  /**
   * Tests that the track method of FacebookServerSideEvent fires an action.
   *
   * This test ensures that when a 'Lead' event
   * is tracked using the track method,
   * the number of tracked events increases by one. It verifies that the
   * get_num_tracked_events method returns the
   * expected count of 1 after tracking
   * the event.
   *
   * @return void
   */
  public function testTrackEventFiresAction() {
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

    $event = ServerEventFactory::new_event( 'Lead' );

    FacebookServerSideEvent::get_instance()->track( $event );

    $this->assertEquals(
      1,
      FacebookServerSideEvent::get_instance()->get_num_tracked_events()
    );
  }

  /**
   * Tests that the 'before_conversions_api_event_sent' filter is invoked.
   *
   * This test verifies that the 'before_conversions_api_event_sent' filter is
   * applied to the events array before sending the
   * events using the send method
   * of FacebookServerSideEvent. It ensures that
   * the filter modifies the events
   * as expected.
   *
   * @return void
   */
  public function testSendInvokesFilter() {
    $events = array();
    \WP_Mock::expectFilter( 'before_conversions_api_event_sent', $events );

      $events = FacebookServerSideEvent::send( $events );
  }

  /**
   * Tests that the track method of FacebookServerSideEvent stores the events
   * correctly.
   *
   * This test verifies that when tracking an event using the track method,
   * the events are stored in the correct order
   * and the correct number of events
   * are stored. It also verifies that the events can be retrieved using the
   * get_pending_events method.
   */
  public function testStoresPendingEvents() {
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

    $event1 = ServerEventFactory::new_event( 'Lead' );
    $event2 = ServerEventFactory::new_event( 'AddToCart' );

    FacebookServerSideEvent::get_instance()->track( $event1, false );
    FacebookServerSideEvent::get_instance()->track( $event2 );

    $pending_events =
    FacebookServerSideEvent::get_instance()->get_pending_events();

    $this->assertEquals(
      2,
      FacebookServerSideEvent::get_instance()->get_num_tracked_events()
    );
    $this->assertEquals(
      1,
      count( $pending_events )
    );
    $this->assertEquals(
      'Lead',
      $pending_events[0]->getEventName()
    );
  }

  /**
   * Tests that the set_pending_pixel_event and
   * get_pending_pixel_event methods of FacebookServerSideEvent
   * store the events correctly.
   *
   * This test verifies that when setting a pending pixel
   * event using the set_pending_pixel_event method,
   * the events are stored in the correct order.
   * It also verifies that the events can be retrieved using the
   * get_pending_pixel_event method using the correct callback name.
   */
  public function testStoresPendingPixelEvents() {
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

    $event = ServerEventFactory::new_event( 'Lead' );

    FacebookServerSideEvent::get_instance()
      ->set_pending_pixel_event( 'test_callback', $event );

    $pending_pixel_event = FacebookServerSideEvent::get_instance()
      ->get_pending_pixel_event( 'test_callback' );

    $this->assertEquals(
      'Lead',
      $pending_pixel_event->getEventName()
    );
  }

  /**
   * Tests that frontend sends are suppressed while signals are held.
   *
   * @return void
   */
  public function testSendSuppressedWhenHeldOnFrontend() {
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

    \WP_Mock::userFunction( 'is_admin', array( 'return' => false ) );
    \WP_Mock::userFunction( 'wp_doing_cron', array( 'return' => false ) );

    FacebookSignalState::hold();

    $api = \Mockery::mock( 'alias:FacebookPixelPlugin\FacebookAds\Api' );
    $api->shouldReceive( 'init' )->never();

    $event = ServerEventFactory::new_event( 'Lead' );
    FacebookServerSideEvent::send( array( $event ) );

    $this->assertTrue( true );
  }

  /**
   * Verifies that a 401 from CAPI triggers a POST to the fallback endpoint.
   */
  public function testSendFallsBackOnAuthFailure() {
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
    \WP_Mock::userFunction( 'is_admin', array( 'return' => false ) );
    \WP_Mock::userFunction( 'wp_doing_cron', array( 'return' => false ) );
    \WP_Mock::userFunction(
      'wp_json_encode',
      array(
        'return' => function ( $data ) {
          return json_encode( $data );
        },
      )
    );
    \WP_Mock::onFilter( 'fbwp_capi_fallback_endpoint' )
      ->with( FacebookServerSideEvent::FALLBACK_ENDPOINT )
      ->reply( FacebookServerSideEvent::FALLBACK_ENDPOINT );

    $api = \Mockery::mock( 'alias:FacebookPixelPlugin\FacebookAds\Api' );
    $api->shouldReceive( 'init' )->once();

    $req = \Mockery::mock(
      'overload:FacebookPixelPlugin\FacebookAds\Object\ServerSide\EventRequest'
    );
    $req->shouldReceive( 'setEvents' )->andReturnSelf();
    $req->shouldReceive( 'setPartnerAgent' )->andReturnSelf();
    $req->shouldReceive( 'normalize' )->andReturn( array( 'data' => array() ) );
    $req->shouldReceive( 'execute' )
      ->andThrow( new StubAuthRequestException( 401 ) );

    \WP_Mock::userFunction(
      'wp_remote_post',
      array(
        'times'  => 1,
        'args'   => array(
          FacebookServerSideEvent::FALLBACK_ENDPOINT,
          \Mockery::any(),
        ),
        'return' => array( 'body' => '{}' ),
      )
    );
    \WP_Mock::userFunction( 'is_wp_error', array( 'return' => false ) );

    $event = ServerEventFactory::new_event( 'Lead' );
    FacebookServerSideEvent::send( array( $event ) );

    $this->assertConditionsMet();
  }
}
