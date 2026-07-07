<?php
/**
 * Facebook Pixel Plugin PendingPixelEventsTest class.
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

use FacebookPixelPlugin\Core\PendingPixelEvents;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * PendingPixelEventsTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class PendingPixelEventsTest extends FacebookWordpressTestBase {
  public function testSetAndGetByCallbackName() {
    $queue = new PendingPixelEvents();
    $event = ( new Event() )->setEventName( 'AddToCart' );

    $queue->set( 'addPixelCodeToAddToCartFragment', $event );

    $this->assertSame(
      $event,
      $queue->get( 'addPixelCodeToAddToCartFragment' )
    );
  }

  public function testGetReturnsNullForUnknownCallback() {
    $queue = new PendingPixelEvents();

    $this->assertNull( $queue->get( 'missing_callback' ) );
  }

  public function testEventsAreKeyedIndependentlyPerCallback() {
    $queue  = new PendingPixelEvents();
    $event1 = ( new Event() )->setEventName( 'AddToCart' );
    $event2 = ( new Event() )->setEventName( 'ViewContent' );

    $queue->set( 'callback_a', $event1 );
    $queue->set( 'callback_b', $event2 );

    $this->assertSame( $event1, $queue->get( 'callback_a' ) );
    $this->assertSame( $event2, $queue->get( 'callback_b' ) );
  }
}
