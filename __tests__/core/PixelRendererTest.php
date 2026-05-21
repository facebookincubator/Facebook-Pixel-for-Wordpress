<?php
/**
 * Facebook Pixel Plugin PixelRendererTest class.
 *
 * This file contains the main logic for PixelRendererTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define PixelRendererTest class.
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

use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\FacebookSignalState;
use FacebookPixelPlugin\Core\PixelRenderer;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\CustomData;


/**
 * PixelRendererTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class PixelRendererTest extends FacebookWordpressTestBase {
  /**
   * Test that the PixelRenderer renders
   * the expected code for a standard event.
   *
   * @covers \FacebookPixelPlugin\Core\PixelRenderer::render
   */
  public function testPixelRenderForStandardEvent() {
    \WP_Mock::userFunction(
      'get_option',
      array( 'return' => array() )
    );

    FacebookWordpressOptions::set_version_info();
    $agent_string = FacebookWordpressOptions::get_agent_string();

    $event = ( new Event() )
      ->setEventName( 'Lead' )
      ->setEventId( 'TestEventId' );

    \WP_Mock::userFunction(
      'wp_unslash',
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
        'args'   => array(
          \Mockery::type( 'array' ),
          \Mockery::type( 'int' ),
        ),
        'return' => function ( $data, $options ) {
          return json_encode( $data );
        },
      )
    );

    $code = PixelRenderer::render( array( $event ), 'Test' );

    $expected = sprintf(
        "<script type='text/javascript'>fbq('set', 'agent', '%s', '');fbq('track', 'Lead', {\"fb_integration_tracking\":\"Test\"}, {\"eventID\":\"TestEventId\"});</script>",
        $agent_string
    );

    $this->assertEquals( $expected, $code );
  }

  /**
   * Test that the PixelRenderer renders the expected code for a custom event.
   *
   * This test ensures that the render method
   * correctly generates the Pixel code
   * for a custom event. It verifies that the
   * output includes the 'trackCustom'
   * keyword and that the event data and custom data are correctly formatted
   * and included in the output.
   *
   * @covers \FacebookPixelPlugin\Core\PixelRenderer::render
   */
  public function testPixelRenderForCustomEvent() {
    \WP_Mock::userFunction(
      'get_option',
      array( 'return' => array() )
    );

    \WP_Mock::userFunction(
      'wp_json_encode',
      array(
        'args'   => array(
          \Mockery::type( 'array' ),
          \Mockery::type( 'int' ),
        ),
        'return' => function ( $data, $options ) {
          return json_encode( $data );
        },
      )
    );

    FacebookWordpressOptions::set_version_info();
    $agent_string = FacebookWordpressOptions::get_agent_string();

    $event = ( new Event() )
      ->setEventName( 'Custom' )
      ->setEventId( 'TestEventId' );

    $code = PixelRenderer::render( array( $event ), 'Test' );

    $expected = sprintf(
      "<script type='text/javascript'>fbq('set', 'agent', '%s', '');fbq('trackCustom', 'Custom', {\"fb_integration_tracking\":\"Test\"}, {\"eventID\":\"TestEventId\"});</script>",
      $agent_string
    );

    $this->assertEquals( $expected, $code );
  }

  /**
   * Test that the PixelRenderer renders the expected code for a custom event
   * with custom data.
   *
   * This test ensures that the render method correctly generates the Pixel
   * code for a custom event with custom data. It verifies that the output
   * includes the 'track' keyword and that the custom data is correctly
   * formatted and included in the output.
   *
   * @covers \FacebookPixelPlugin\Core\PixelRenderer::render
   */
  public function testPixelRenderForCustomData() {
    \WP_Mock::userFunction(
      'get_option',
      array( 'return' => array() )
    );

    \WP_Mock::userFunction(
      'wp_json_encode',
      array(
        'args'   => array(
          \Mockery::type( 'array' ),
          \Mockery::type( 'int' ),
        ),
        'return' => function ( $data, $options ) {
          return json_encode( $data );
        },
      )
    );

    FacebookWordpressOptions::set_version_info();
    $agent_string = FacebookWordpressOptions::get_agent_string();

    $custom_data = ( new CustomData() )
      ->setCurrency( 'USD' )
      ->setValue( '30.00' );

    $event = ( new Event() )
      ->setEventName( 'Purchase' )
      ->setEventId( 'TestEventId' )
      ->setCustomData( $custom_data );

    $code = PixelRenderer::render( array( $event ), 'Test' );

    $expected = sprintf(
      "<script type='text/javascript'>fbq('set', 'agent', '%s', '');fbq('track', 'Purchase', {\"value\":\"30.00\",\"currency\":\"usd\",\"fb_integration_tracking\":\"Test\"}, {\"eventID\":\"TestEventId\"});</script>",
      $agent_string
    );

    $this->assertEquals( $expected, $code );
  }

  /**
   * Test that the PixelRenderer renders the
   * expected code for multiple events.
   *
   * This test verifies that the render method correctly generates the Pixel
   * code when provided with multiple events. It ensures that each event is
   * tracked separately and that the output includes the correct event data
   * and event IDs for each event.
   *
   * @covers \FacebookPixelPlugin\Core\PixelRenderer::render
   */
  public function testPixelRenderForMultipleEvents() {
    \WP_Mock::userFunction(
      'get_option',
      array( 'return' => array() )
    );

    \WP_Mock::userFunction(
      'wp_json_encode',
      array(
        'args'   => array(
          \Mockery::type( 'array' ),
          \Mockery::type( 'int' ),
        ),
        'return' => function ( $data, $options ) {
          return json_encode( $data );
        },
      )
    );

    FacebookWordpressOptions::set_version_info();
    $agent_string = FacebookWordpressOptions::get_agent_string();

    $event1 = ( new Event() )
      ->setEventName( 'Lead' )
      ->setEventId( 'TestEventId1' );
    $event2 = ( new Event() )
      ->setEventName( 'Lead' )
      ->setEventId( 'TestEventId2' );

    $code = PixelRenderer::render( array( $event1, $event2 ), 'Test' );

    $expected = sprintf(
      "<script type='text/javascript'>fbq('set', 'agent', '%s', '');fbq('track', 'Lead', {\"fb_integration_tracking\":\"Test\"}, {\"eventID\":\"TestEventId1\"});fbq('track', 'Lead', {\"fb_integration_tracking\":\"Test\"}, {\"eventID\":\"TestEventId2\"});</script>",
      $agent_string
    );

    $this->assertEquals( $expected, $code );
  }

  /**
   * Test that paused rendering queues events instead of firing fbq directly.
   *
   * @covers \FacebookPixelPlugin\Core\PixelRenderer::render
   */
  public function testPixelRenderQueuesEventsWhenPaused() {
    \WP_Mock::userFunction(
      'get_option',
      array( 'return' => array() )
    );

    \WP_Mock::userFunction(
      'wp_json_encode',
      array(
        'args'   => array(
          \Mockery::type( 'array' ),
          \Mockery::type( 'int' ),
        ),
        'return' => function ( $data, $options ) {
          return json_encode( $data );
        },
      )
    );

    FacebookSignalState::pause();
    FacebookWordpressOptions::set_version_info();

    $event = ( new Event() )
      ->setEventName( 'Lead' )
      ->setEventId( 'TestEventId' )
      ->setEventTime( 1234 );

    $code = PixelRenderer::render( array( $event ), 'Test' );

    $this->assertStringContainsString( 'FacebookSignal.queueEvent(', $code );
    $this->assertStringContainsString( '"event_name":"Lead"', $code );
    $this->assertStringContainsString( '"event_id":"TestEventId"', $code );
    $this->assertStringContainsString(
      '"fb_integration_tracking":"Test"',
      $code
    );
    $this->assertStringNotContainsString( "fbq('track'", $code );
  }

  /**
   * Test that paused raw rendering still returns queue-aware JS.
   *
   * @covers \FacebookPixelPlugin\Core\PixelRenderer::render
   */
  public function testPixelRenderQueuesEventsWhenPausedWithoutScriptTag() {
    \WP_Mock::userFunction(
      'get_option',
      array( 'return' => array() )
    );

    \WP_Mock::userFunction(
      'wp_json_encode',
      array(
        'args'   => array(
          \Mockery::type( 'array' ),
          \Mockery::type( 'int' ),
        ),
        'return' => function ( $data, $options ) {
          return json_encode( $data );
        },
      )
    );

    FacebookSignalState::pause();
    FacebookWordpressOptions::set_version_info();

    $event = ( new Event() )
      ->setEventName( 'Purchase' )
      ->setEventId( 'TestEventId' )
      ->setEventTime( 1234 )
      ->setCustomData( ( new CustomData() )->setValue( '10.00' ) );

    $code = PixelRenderer::render( array( $event ), 'Test', false );

    $this->assertStringContainsString( 'FacebookSignal.queueEvent(', $code );
    $this->assertStringContainsString( '"event_name":"Purchase"', $code );
    $this->assertStringNotContainsString( '<script', $code );
  }
}
