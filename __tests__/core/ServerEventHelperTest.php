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

use FacebookPixelPlugin\Core\ServerEventHelper;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class ServerEventHelperTest extends FacebookWordpressTestBase {
  public function testNewEventHasEventId() {
    $event = ServerEventHelper::newEvent('Lead');

    $this->assertNotNull($event->getEventId());
    $this->assertEquals(36, strlen($event->getEventId()));
  }

  public function testNewEventHasEventTime() {
    $event = ServerEventHelper::newEvent('Lead');

    $this->assertNotNull($event->getEventTime());
    $this->assertLessThan(1, time() - $event->getEventTime());
  }

  public function testNewEventHasEventName() {
    $event = ServerEventHelper::newEvent('Lead');

    $this->assertEquals('Lead', $event->getEventName());
  }

  public function testNewEventTakesIpAddressFromHttpClientIP() {
    $_SERVER['HTTP_CLIENT_IP'] = 'HTTP_CLIENT_IP_VALUE';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = 'HTTP_X_FORWARDED_FOR_VALUE';
    $_SERVER['REMOTE_ADDR'] = 'REMOTE_ADDR';

    $event = ServerEventHelper::newEvent('Lead');
    $this->assertEquals('HTTP_CLIENT_IP_VALUE',
      $event->getUserData()->getClientIpAddress());
  }

  public function testNewEventTakesIpAddressFromHttpXForwardedFor() {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = 'HTTP_X_FORWARDED_FOR_VALUE';
    $_SERVER['REMOTE_ADDR'] = 'REMOTE_ADDR';

    $event = ServerEventHelper::newEvent('Lead');
    $this->assertEquals('HTTP_X_FORWARDED_FOR_VALUE',
      $event->getUserData()->getClientIpAddress());
  }

  public function testNewEventTakesIpAddressFromRemoteAddr() {
    $_SERVER['REMOTE_ADDR'] = 'REMOTE_ADDR_VALUE';

    $event = ServerEventHelper::newEvent('Lead');
    $this->assertEquals('REMOTE_ADDR_VALUE',
      $event->getUserData()->getClientIpAddress());
  }

  public function testNewEventHasUserAgent() {
    $_SERVER['HTTP_USER_AGENT'] = 'HTTP_USER_AGENT_VALUE';
    $event = ServerEventHelper::newEvent('Lead');

    $this->assertEquals('HTTP_USER_AGENT_VALUE',
      $event->getUserData()->getClientUserAgent());
  }

  public function testNewEventHasEventSourceUrl() {
    $_SERVER['REQUEST_URI'] = 'REQUEST_URI_VALUE';
    $event = ServerEventHelper::newEvent('Lead');

    $this->assertEquals('REQUEST_URI_VALUE', $event->getEventSourceUrl());
  }
}
