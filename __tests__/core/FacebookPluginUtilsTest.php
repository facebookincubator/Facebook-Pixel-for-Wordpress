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

use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in seperate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookPluginUtilsTest extends FacebookWordpressTestBase {
  public function testWhenIsPositiveInteger() {
    $this->assertTrue(FacebookPluginUtils::isPositiveInteger('1'));
    $this->assertFalse(FacebookPluginUtils::isPositiveInteger('0'));
    $this->assertFalse(FacebookPluginUtils::isPositiveInteger('-1'));
  }

  public function testIsAdmin() {
    \WP_Mock::userFunction('current_user_can', array(
      'args' => 'install_plugins',
      'return' => true,
    ));

    $isAdmin = FacebookPluginUtils::isAdmin();
    $this->assertTrue($isAdmin);
  }

  public function testIsNotAdmin() {
    \WP_Mock::userFunction('current_user_can', array(
      'args' => 'install_plugins',
      'return' => false,
    ));

    $isAdmin = FacebookPluginUtils::isAdmin();
    $this->assertFalse($isAdmin);
  }
}
