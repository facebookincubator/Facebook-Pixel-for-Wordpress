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
    $event = ServerEventHelper::newEvent();

    $this->assertNotNull($event->getEventId());
    $this->assertEquals(36, strlen($event->getEventId()));
  }

  public function testNewEventHasEventTime() {
    $event = ServerEventHelper::newEvent();

    $this->assertNotNull($event->getEventTime());
    $this->assertLessThan(1, time() - $event->getEventTime());
  }
}