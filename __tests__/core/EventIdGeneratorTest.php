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

use FacebookPixelPlugin\Core\EventIdGenerator;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class EventIdGeneratorTest extends FacebookWordpressTestBase {
  public function testGuidv4GeneratesUniqueValues() {
    $event_ids = array();
    for ($i = 0; $i < 100; $i++) {
      $event_ids[] = EventIdGenerator::guidv4();
    }

    $event_ids = array_unique($event_ids);
    $this->assertEquals(100, count($event_ids));
  }
}
