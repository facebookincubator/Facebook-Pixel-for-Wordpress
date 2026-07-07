<?php
/**
 * Facebook Pixel Plugin ServerEventBufferTest class.
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

use FacebookPixelPlugin\Core\ServerEventBuffer;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * ServerEventBufferTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class ServerEventBufferTest extends FacebookWordpressTestBase {
  /**
   * A fresh buffer for each test.
   *
   * @var ServerEventBuffer
   */
  private $buffer;

  public function setUp(): void {
    parent::setUp();
    $this->buffer = new ServerEventBuffer();
  }

  public function testRecordAddsToTrackedEvents() {
    $event = ( new Event() )->setEventName( 'Lead' );

    $this->buffer->record( $event );

    $this->assertEquals( 1, $this->buffer->get_num_tracked_events() );
    $tracked = $this->buffer->get_tracked_events();
    $this->assertSame( $event, $tracked[0] );
  }

  public function testQueueForSendAccumulatesInOrder() {
    $event1 = ( new Event() )->setEventName( 'Lead' );
    $event2 = ( new Event() )->setEventName( 'AddToCart' );

    $this->buffer->queue_for_send( $event1 );
    $this->buffer->queue_for_send( $event2 );

    $pending = $this->buffer->get_pending_events();
    $this->assertCount( 2, $pending );
    $this->assertEquals( 'Lead', $pending[0]->getEventName() );
    $this->assertEquals( 'AddToCart', $pending[1]->getEventName() );
  }

  public function testRecordAndQueueAreIndependent() {
    $event = ( new Event() )->setEventName( 'Lead' );

    $this->buffer->record( $event );

    // Recording does not queue for send.
    $this->assertCount( 0, $this->buffer->get_pending_events() );
    $this->assertEquals( 1, $this->buffer->get_num_tracked_events() );
  }
}
