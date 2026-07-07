<?php
/**
 * Facebook Pixel Plugin KeyedEventBufferTest class.
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

use FacebookPixelPlugin\Core\KeyedEventBuffer;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * KeyedEventBufferTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class KeyedEventBufferTest extends FacebookWordpressTestBase {
  public function testSetAndGetByKey() {
    $buffer = new KeyedEventBuffer();
    $event  = ( new Event() )->setEventName( 'AddToCart' );

    $buffer->set( 'addPixelCodeToAddToCartFragment', $event );

    $this->assertSame(
      $event,
      $buffer->get( 'addPixelCodeToAddToCartFragment' )
    );
  }

  public function testGetReturnsNullForUnknownKey() {
    $buffer = new KeyedEventBuffer();

    $this->assertNull( $buffer->get( 'missing_key' ) );
  }

  public function testEventsAreStoredIndependentlyPerKey() {
    $buffer = new KeyedEventBuffer();
    $event1 = ( new Event() )->setEventName( 'AddToCart' );
    $event2 = ( new Event() )->setEventName( 'ViewContent' );

    $buffer->set( 'key_a', $event1 );
    $buffer->set( 'key_b', $event2 );

    $this->assertSame( $event1, $buffer->get( 'key_a' ) );
    $this->assertSame( $event2, $buffer->get( 'key_b' ) );
  }
}
