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
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class ServerEventFactoryTest extends FacebookWordpressTestBase {
  public function testNewEventHasEventId() {
    $event = ServerEventFactory::newEvent('Lead');

    $this->assertNotNull($event->getEventId());
    $this->assertEquals(36, strlen($event->getEventId()));
  }

  public function testNewEventHasEventTime() {
    $event = ServerEventFactory::newEvent('Lead');

    $this->assertNotNull($event->getEventTime());
    $this->assertLessThan(1, time() - $event->getEventTime());
  }

  public function testNewEventHasEventName() {
    $event = ServerEventFactory::newEvent('Lead');

    $this->assertEquals('Lead', $event->getEventName());
  }

  public function testNewEventTakesIpAddressFromHttpClientIP() {
    $_SERVER['HTTP_CLIENT_IP'] = 'HTTP_CLIENT_IP_VALUE';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = 'HTTP_X_FORWARDED_FOR_VALUE';
    $_SERVER['REMOTE_ADDR'] = 'REMOTE_ADDR';

    $event = ServerEventFactory::newEvent('Lead');
    $this->assertEquals('HTTP_CLIENT_IP_VALUE',
      $event->getUserData()->getClientIpAddress());
  }

  public function testNewEventTakesIpAddressFromHttpXForwardedFor() {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = 'HTTP_X_FORWARDED_FOR_VALUE';
    $_SERVER['REMOTE_ADDR'] = 'REMOTE_ADDR';

    $event = ServerEventFactory::newEvent('Lead');
    $this->assertEquals('HTTP_X_FORWARDED_FOR_VALUE',
      $event->getUserData()->getClientIpAddress());
  }

  public function testNewEventTakesIpAddressFromRemoteAddr() {
    $_SERVER['REMOTE_ADDR'] = 'REMOTE_ADDR_VALUE';

    $event = ServerEventFactory::newEvent('Lead');
    $this->assertEquals('REMOTE_ADDR_VALUE',
      $event->getUserData()->getClientIpAddress());
  }

  public function testNewEventHasUserAgent() {
    $this->mockUsePII(true);
    $_SERVER['HTTP_USER_AGENT'] = 'HTTP_USER_AGENT_VALUE';
    $event = ServerEventFactory::newEvent('Lead');

    $this->assertEquals('HTTP_USER_AGENT_VALUE',
      $event->getUserData()->getClientUserAgent());
  }

  public function testNewEventHasEventSourceUrl() {
    $this->mockUsePII(true);
    $_SERVER['REQUEST_URI'] = 'REQUEST_URI_VALUE';
    $event = ServerEventFactory::newEvent('Lead');

    $this->assertEquals('REQUEST_URI_VALUE', $event->getEventSourceUrl());
  }

  public function testNewEventHasFbc() {
    $this->mockUsePII(true);
    $_COOKIE['_fbc'] = '_fbc_value';
    $event = ServerEventFactory::newEvent('Lead');

    $this->assertEquals('_fbc_value', $event->getUserData()->getFbc());
  }

  public function testNewEventHasFbp() {
    $this->mockUsePII(true);
    $_COOKIE['_fbp'] = '_fbp_value';
    $event = ServerEventFactory::newEvent('Lead');

    $this->assertEquals('_fbp_value', $event->getUserData()->getFbp());
  }

  public function testSafeCreateEventWithPII() {
    $this->mockUsePII(true);

    $server_event = ServerEventFactory::safeCreateEvent(
      'Lead',
      array($this, 'getEventData'),
      array()
    );

    $this->assertEquals('pika.chu@s2s.com',
      $server_event->getUserData()->getEmail());
    $this->assertEquals('Pika', $server_event->getUserData()->getFirstName());
    $this->assertEquals('Chu', $server_event->getUserData()->getLastName());
  }

  public function testSafeCreateEventWithPIIDisabled() {
    $this->mockUsePII(false);

    $server_event = ServerEventFactory::safeCreateEvent(
      'Lead',
      array($this, 'getEventData'),
      array()
    );

    $this->assertNull($server_event->getUserData()->getEmail());
    $this->assertNull($server_event->getUserData()->getFirstName());
    $this->assertNull($server_event->getUserData()->getLastName());
  }

  public function getEventData() {
    return array(
      'email' => 'pika.chu@s2s.com',
      'first_name' => 'Pika',
      'last_name' => 'Chu'
    );
  }

  private function mockUsePII($use_pii = true) {
    $this->mocked_options = \Mockery::mock(
      'alias:FacebookPixelPlugin\Core\FacebookWordpressOptions');
    $this->mocked_options->shouldReceive('getUsePii')->andReturn($use_pii);
  }
}
