<?php
/**
 * Facebook Pixel Plugin AdminTestCapiEventTest class.
 *
 * @package FacebookPixelPlugin
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

use FacebookPixelPlugin\Core\AdminTestCapiEvent;
use FacebookPixelPlugin\Core\AdminEventSender;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * AdminTestCapiEventTest class.
 *
 * Characterizes the admin "Test Events" endpoint (wp_ajax_send_capi_event). The
 * event structure is offloaded to EventFactory + the SDK objects. The contract
 * that must not regress:
 *  - the posted payload is mapped to Event(s) and passed to
 *    AdminEventSender::send() (one event per data entry, right event name), and
 *  - the AdminEventSender::send() result is captured and returned to the admin
 *    panel — events_received on success, the error message on a non-success
 *    result.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AdminTestCapiEventTest extends FacebookWordpressTestBase {
  /**
   * A successful Conversions API send result.
   */
  private function successResult() {
    return array(
      'success'         => true,
      'events_received' => 1,
    );
  }

  /**
   * A non-success Conversions API send result (API rejected the event).
   */
  private function errorResult( $message ) {
    return array(
      'success' => false,
      'error'   => array(
        'message' => $message,
        'code'    => 100,
      ),
    );
  }

  /**
   * Builds a $_POST for the advanced-payload mode with one event of the given
   * name. Fields are shaped to satisfy the current endpoint's validation so the
   * test is green before the refactor and stays green after it.
   *
   * @param string $event_name  The event name.
   * @param array  $custom_data Optional valid custom_data.
   *
   * @return array
   */
  private function payloadPost( $event_name, $custom_data = array() ) {
    $event = array(
      'event_name'       => $event_name,
      'event_time'       => 1700000000,
      'user_data'        => array( 'em' => str_repeat( 'a', 64 ) ),
      'action_source'    => 'website',
      'event_source_url' => 'https://example.com',
    );
    if ( ! empty( $custom_data ) ) {
      $event['custom_data'] = $custom_data;
    }

    return array(
      'nonce'      => 'valid-nonce',
      'event_name' => $event_name,
      'payload'    => array( 'data' => array( $event ) ),
    );
  }

  /**
   * Invokes send_capi_event() with the given $_POST and a stubbed Signals send
   * result, capturing the JSON response and the events handed to send().
   *
   * @param array $post          The $_POST payload.
   * @param mixed $send_result   What AdminEventSender::send() should return.
   * @param bool  $nonce_valid   Whether wp_verify_nonce passes.
   * @param bool  $expect_send   Whether send() is expected to be called.
   *
   * @return array{fn:string,data:array,sent:mixed}
   */
  private function invoke(
    $post,
    $send_result,
    $nonce_valid = true,
    $expect_send = true
  ) {
    \WP_Mock::userFunction(
      'wp_verify_nonce',
      array( 'return' => $nonce_valid )
    );
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );
    \WP_Mock::userFunction(
      'wp_json_encode',
      array(
        'return' => function ( $data ) {
          return json_encode( $data );
        },
      )
    );

    $sent   = null;
    $sender = \Mockery::mock( AdminEventSender::class );
    if ( $expect_send ) {
      $sender->shouldReceive( 'send' )
        ->andReturnUsing(
          function ( $events, $code = null ) use ( &$sent, $send_result ) {
            $sent = $events;
            return $send_result;
          }
        );
    } else {
      $sender->shouldReceive( 'send' )->never();
    }

    $captured = null;
    $capture  = function ( $fn ) use ( &$captured ) {
      return function ( $data ) use ( &$captured, $fn ) {
        $captured = array(
          'fn'   => $fn,
          'data' => json_decode( $data, true ),
        );
        throw new \RuntimeException( 'halt' );
      };
    };
    \WP_Mock::userFunction(
      'wp_send_json_success',
      array( 'return' => $capture( 'success' ) )
    );
    \WP_Mock::userFunction(
      'wp_send_json_error',
      array( 'return' => $capture( 'error' ) )
    );
    \WP_Mock::userFunction( 'wp_die', array( 'return' => null ) );

    $_POST = $post;

    $obj = new AdminTestCapiEvent( $sender );
    try {
      $obj->send_capi_event();
    } catch ( \RuntimeException $e ) {
      // wp_send_json_* halts execution in real WP; the mock throws to emulate.
      unset( $e );
    }

    return array(
      'fn'   => $captured['fn'],
      'data' => $captured['data'],
      'sent' => $sent,
    );
  }

  /**
   * Asserts a valid payload for the given event is mapped to a single Event of
   * that name and reported back as events_received.
   *
   * @param string $event_name  The event name.
   * @param array  $custom_data Optional valid custom_data.
   *
   * @return void
   */
  private function assertValidPayloadReportsSuccess(
    $event_name,
    $custom_data = array()
  ) {
    $result = $this->invoke(
      $this->payloadPost( $event_name, $custom_data ),
      $this->successResult()
    );

    $this->assertEquals( 'success', $result['fn'] );
    $this->assertArrayHasKey( 'events_received', $result['data'] );
    $this->assertEquals( 1, $result['data']['events_received'] );

    $this->assertIsArray( $result['sent'] );
    $this->assertCount( 1, $result['sent'] );
    $this->assertEquals( $event_name, $result['sent'][0]->getEventName() );
  }

  /**
   * Asserts a non-success send result is surfaced to the admin panel.
   *
   * @param string $event_name The event name.
   *
   * @return void
   */
  private function assertSendErrorReturnedToAdmin( $event_name ) {
    $result = $this->invoke(
      $this->payloadPost( $event_name ),
      $this->errorResult( 'Event rejected: bad ' . $event_name )
    );

    $this->assertArrayHasKey( 'error', $result['data'] );
    $this->assertEquals(
      'Event rejected: bad ' . $event_name,
      $result['data']['error']['message']
    );
  }

  public function testLeadValidPayloadReportsEventsReceived() {
    $this->assertValidPayloadReportsSuccess( 'Lead' );
  }

  public function testLeadSendErrorReturnedToAdmin() {
    $this->assertSendErrorReturnedToAdmin( 'Lead' );
  }

  public function testAddToCartValidPayloadReportsEventsReceived() {
    $this->assertValidPayloadReportsSuccess(
      'AddToCart',
      array(
        'content_ids'  => array( 'SKU1' ),
        'content_type' => 'product',
        'value'        => 9.99,
        'currency'     => 'USD',
      )
    );
  }

  public function testAddToCartSendErrorReturnedToAdmin() {
    $this->assertSendErrorReturnedToAdmin( 'AddToCart' );
  }

  public function testPageViewValidPayloadReportsEventsReceived() {
    $this->assertValidPayloadReportsSuccess( 'PageView' );
  }

  public function testPageViewSendErrorReturnedToAdmin() {
    $this->assertSendErrorReturnedToAdmin( 'PageView' );
  }

  public function testSubscribeValidPayloadReportsEventsReceived() {
    $this->assertValidPayloadReportsSuccess(
      'Subscribe',
      array(
        'value'         => 10,
        'currency'      => 'USD',
        'predicted_ltv' => 100,
      )
    );
  }

  public function testSubscribeSendErrorReturnedToAdmin() {
    $this->assertSendErrorReturnedToAdmin( 'Subscribe' );
  }

  /**
   * Simple mode (no payload, just event_name + custom_data) maps the custom
   * data onto the event for a commerce event.
   *
   * @return void
   */
  public function testSimpleModeCommerceEventMapsCustomData() {
    $result = $this->invoke(
      array(
        'nonce'       => 'valid-nonce',
        'event_name'  => 'AddToCart',
        'custom_data' => array(
          'value'        => 123.321,
          'currency'     => 'USD',
          'content_type' => 'product',
        ),
      ),
      $this->successResult()
    );

    $this->assertEquals( 'success', $result['fn'] );
    $this->assertCount( 1, $result['sent'] );
    $event       = $result['sent'][0];
    $custom_data = $event->getCustomData();
    $this->assertEquals( 'AddToCart', $event->getEventName() );
    $this->assertEquals( 123.321, $custom_data->getValue() );
    $this->assertNotEmpty( $custom_data->getCurrency() );
    $this->assertEquals( 'product', $custom_data->getContentType() );
  }

  /**
   * Simple mode for a non-commerce event: the JS's setCustomData() returns null,
   * which jQuery serializes to an empty string in the POST. That non-array value
   * must be treated as "no custom data" and still map to a single event without
   * error (guards against passing a non-array into EventFactory::create()).
   *
   * @return void
   */
  public function testSimpleModeNonCommerceEventWithoutCustomData() {
    $result = $this->invoke(
      array(
        'nonce'       => 'valid-nonce',
        'event_name'  => 'Lead',
        'custom_data' => '',
      ),
      $this->successResult()
    );

    $this->assertEquals( 'success', $result['fn'] );
    $this->assertCount( 1, $result['sent'] );
    $this->assertEquals( 'Lead', $result['sent'][0]->getEventName() );
  }

  /**
   * A multi-event payload maps each entry to its own Event and sends them
   * together.
   *
   * @return void
   */
  public function testMultipleEventsAreAllMappedAndSent() {
    $post = array(
      'nonce'   => 'valid-nonce',
      'payload' => array(
        'data' => array(
          array(
            'event_name'       => 'PageView',
            'event_time'       => 1700000000,
            'user_data'        => array( 'em' => str_repeat( 'a', 64 ) ),
            'action_source'    => 'website',
            'event_source_url' => 'https://example.com',
          ),
          array(
            'event_name'       => 'Lead',
            'event_time'       => 1700000000,
            'user_data'        => array( 'em' => str_repeat( 'b', 64 ) ),
            'action_source'    => 'website',
            'event_source_url' => 'https://example.com',
          ),
        ),
      ),
    );

    $result = $this->invoke( $post, $this->successResult() );

    $this->assertCount( 2, $result['sent'] );
    $this->assertEquals( 'PageView', $result['sent'][0]->getEventName() );
    $this->assertEquals( 'Lead', $result['sent'][1]->getEventName() );
  }

  /**
   * An invalid nonce is rejected and no event is sent.
   *
   * @return void
   */
  public function testInvalidNonceIsRejectedAndNothingSent() {
    $result = $this->invoke(
      $this->payloadPost( 'Lead' ),
      $this->successResult(),
      false,
      false
    );

    $this->assertEquals( 'error', $result['fn'] );
  }
}
