<?php
/**
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

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Core\ReleaseSignalsAjax;
use FacebookPixelPlugin\Core\Capi;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * ReleaseSignalsAjaxTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ReleaseSignalsAjaxTest extends FacebookWordpressTestBase {
  /**
   * Test queued event validation rules.
   *
   * @return void
   */
  public function testValidateEventRejectsUnknownAndStaleEvents() {
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );
    \WP_Mock::userFunction(
      'absint',
      array(
        'return' => function ( $value ) {
          return (int) $value;
        },
      )
    );

    $handler = new ReleaseSignalsAjax( \Mockery::mock( Capi::class ) );
    $method  = new \ReflectionMethod( ReleaseSignalsAjax::class, 'validate_event' );
    if ( PHP_VERSION_ID < 80100 ) {
      $method->setAccessible( true );
    }

    $this->assertFalse(
      $method->invoke(
        $handler,
        array(
          'event_name' => 'Bad Name!',
          'event_time' => time(),
        ),
        time()
      )
    );

    $this->assertFalse(
      $method->invoke(
        $handler,
        array(
          'event_name' => 'Purchase',
          'event_time' => time() - 3600,
        ),
        time()
      )
    );

    $this->assertTrue(
      $method->invoke(
        $handler,
        array(
          'event_name' => 'Purchase',
          'event_time' => time(),
        ),
        time()
      )
    );
  }

  /**
   * Test released event construction keeps source URL and event metadata.
   *
   * @return void
   */
  public function testBuildEventUsesQueuedPayload() {
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );
    \WP_Mock::userFunction(
      'esc_url_raw',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );
    \WP_Mock::userFunction(
      'absint',
      array(
        'return' => function ( $value ) {
          return (int) $value;
        },
      )
    );
    \WP_Mock::userFunction(
      'wp_unslash',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );

    $handler = new ReleaseSignalsAjax( \Mockery::mock( Capi::class ) );
    $method  = new \ReflectionMethod( ReleaseSignalsAjax::class, 'build_event' );
    if ( PHP_VERSION_ID < 80100 ) {
      $method->setAccessible( true );
    }

    $event = $method->invoke(
      $handler,
      array(
        'event_name'       => 'Purchase',
        'event_id'         => 'evt-1',
        'event_time'       => 1234,
        'event_source_url' => 'https://www.pikachu.com/product/1',
        'custom_data'      => array(
          'currency' => 'USD',
          'value'    => 10,
        ),
      )
    );

    $this->assertEquals( 'Purchase', $event->getEventName() );
    $this->assertEquals( 'evt-1', $event->getEventId() );
    $this->assertEquals( 1234, $event->getEventTime() );
    $this->assertEquals(
      'https://www.pikachu.com/product/1',
      $event->getEventSourceUrl()
    );
    $this->assertEquals(
      'usd',
      $event->getCustomData()->normalize()['currency']
    );
  }

  /**
   * Mocks the sanitizers the release handler touches and returns a private
   * method of the handler.
   *
   * @param string $name The method name.
   * @return \ReflectionMethod
   */
  private function get_handler_method( $name ) {
    foreach ( array( 'sanitize_text_field', 'esc_url_raw', 'wp_unslash' ) as $passthrough ) {
      \WP_Mock::userFunction(
        $passthrough,
        array(
          'return' => function ( $value ) {
            return $value;
          },
        )
      );
    }
    \WP_Mock::userFunction(
      'absint',
      array(
        'return' => function ( $value ) {
          return abs( (int) $value );
        },
      )
    );

    $method = new \ReflectionMethod( ReleaseSignalsAjax::class, $name );
    if ( PHP_VERSION_ID < 80100 ) {
      $method->setAccessible( true );
    }
    return $method;
  }

  /**
   * The browser reports how long ago it queued an event, and the server places
   * the event on its own clock from that, so the browser's absolute time, which
   * may run ahead or behind, never decides whether the event is kept.
   *
   * @return void
   */
  public function testResolveEventTimePlacesTheEventOnTheServerClockFromItsAge() {
    $handler = new ReleaseSignalsAjax( \Mockery::mock( Capi::class ) );
    $method  = $this->get_handler_method( 'resolve_event_time' );
    $now     = 1800000000;

    $this->assertSame(
      $now - 90,
      $method->invoke(
        $handler,
        array(
          'event_name' => 'Purchase',
          'event_age'  => 90,
          'event_time' => $now + 7200,
        ),
        $now
      )
    );
    $this->assertSame(
      $now,
      $method->invoke( $handler, array( 'event_name' => 'Purchase', 'event_age' => 0 ), $now )
    );
    $this->assertSame(
      $now,
      $method->invoke( $handler, array( 'event_name' => 'Purchase', 'event_age' => '-5' ), $now )
    );
    $this->assertNull(
      $method->invoke( $handler, array( 'event_name' => 'Purchase', 'event_age' => 1801 ), $now )
    );
  }

  /**
   * An older copy of the browser script sends only an absolute time. A time
   * later than the server's can only be clock skew, so a browser two minutes
   * ahead keeps its event, placed at the time of the release.
   *
   * @return void
   */
  public function testResolveEventTimeTreatsAFutureAbsoluteTimeAsNow() {
    $handler = new ReleaseSignalsAjax( \Mockery::mock( Capi::class ) );
    $method  = $this->get_handler_method( 'resolve_event_time' );
    $now     = 1800000000;

    $this->assertSame(
      $now,
      $method->invoke( $handler, array( 'event_name' => 'Purchase', 'event_time' => $now + 120 ), $now )
    );
    $this->assertSame(
      $now - 120,
      $method->invoke( $handler, array( 'event_name' => 'Purchase', 'event_time' => $now - 120 ), $now )
    );
    $this->assertNull(
      $method->invoke( $handler, array( 'event_name' => 'Purchase', 'event_time' => $now - 1801 ), $now )
    );
    $this->assertSame(
      $now,
      $method->invoke( $handler, array( 'event_name' => 'Purchase' ), $now )
    );
  }

  /**
   * Validation follows the same rules: a future absolute time passes, an age
   * beyond the limit does not.
   *
   * @return void
   */
  public function testValidateEventAcceptsAFutureTimeAndRejectsAnOldAge() {
    $handler = new ReleaseSignalsAjax( \Mockery::mock( Capi::class ) );
    $method  = $this->get_handler_method( 'validate_event' );
    $now     = time();

    $this->assertTrue(
      $method->invoke( $handler, array( 'event_name' => 'Purchase', 'event_time' => $now + 120 ), $now )
    );
    $this->assertTrue(
      $method->invoke( $handler, array( 'event_name' => 'Purchase', 'event_age' => 60 ), $now )
    );
    $this->assertFalse(
      $method->invoke( $handler, array( 'event_name' => 'Purchase', 'event_age' => 1801 ), $now )
    );
  }

  /**
   * The resolved time, not the payload's, ends up on the released event.
   *
   * @return void
   */
  public function testBuildEventUsesTheResolvedTime() {
    $handler = new ReleaseSignalsAjax( \Mockery::mock( Capi::class ) );
    $method  = $this->get_handler_method( 'build_event' );

    $event = $method->invoke(
      $handler,
      array(
        'event_name' => 'Purchase',
        'event_time' => 1234,
      ),
      1800000000
    );

    $this->assertEquals( 1800000000, $event->getEventTime() );
  }
}
