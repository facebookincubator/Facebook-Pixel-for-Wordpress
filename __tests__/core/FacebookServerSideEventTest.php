<?php
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

use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookServerSideEventTest extends FacebookWordpressTestBase {
  public function testTrackEventFiresAction() {
    self::mockFacebookWordpressOptions();
    $event = ServerEventFactory::newEvent('Lead');

    FacebookServerSideEvent::getInstance()->track($event);

    $this->assertEquals(1,
      FacebookServerSideEvent::getInstance()->getNumTrackedEvents());
  }

  public function testSendInvokesFilter() {
    $events = array();
    \WP_Mock::expectFilter('before_conversions_api_event_sent', $events);

    $events = FacebookServerSideEvent::send($events);
  }

  public function testStoresPendingEvents(){
    self::mockFacebookWordpressOptions();

    $event1 = ServerEventFactory::newEvent('Lead');
    $event2 = ServerEventFactory::newEvent('AddToCart');

    FacebookServerSideEvent::getInstance()->track($event1, false);
    FacebookServerSideEvent::getInstance()->track($event2);

    $pending_events =
      FacebookServerSideEvent::getInstance()->getPendingEvents();

    $this->assertEquals(2,
      FacebookServerSideEvent::getInstance()->getNumTrackedEvents());
    $this->assertEquals(1,
      count($pending_events));
    $this->assertEquals('Lead',
      $pending_events[0]->getEventName());
  }

  public function testStoresPendingPixelEvents(){
    self::mockFacebookWordpressOptions();

    $event = ServerEventFactory::newEvent('Lead');

    FacebookServerSideEvent::getInstance()
      ->setPendingPixelEvent('test_callback', $event);

    $pending_pixel_event =
      FacebookServerSideEvent::getInstance()
        ->getPendingPixelEvent('test_callback');

    $this->assertEquals('Lead',
      $pending_pixel_event->getEventName());
  }
 }
