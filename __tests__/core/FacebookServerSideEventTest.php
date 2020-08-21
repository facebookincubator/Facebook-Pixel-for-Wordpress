<?php
/*
 * Copyright (C) 2017-present, Facebook, Inc.
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
    self::mockFacebookWordpressOptions(
      array(
        'use_s2s' => true
      )
    );
    $event = ServerEventFactory::newEvent('Lead');
    \WP_Mock::expectAction('send_server_event', $event);

    FacebookServerSideEvent::getInstance()->track($event);
  }

  public function testSendInvokesFilter() {
    $events = array();
    \WP_Mock::expectFilter('before_conversions_api_event_sent', $events);

    $events = FacebookServerSideEvent::send($events);
  }
 }
