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

namespace FacebookPixelPlugin\Tests\Integration;

use FacebookPixelPlugin\Integration\FacebookWordpressGravityForms;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in seperate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressGravityFormsTest extends FacebookWordpressTestBase {
  public function testInjectPixelCode() {
    $mocked_base = \Mockery::mock('alias:FacebookPixelPlugin\Integration\FacebookWordpressIntegrationBase');
    $mocked_base->shouldReceive('addPixelFireForHook')
      ->with(array(
        'hook_name' => 'gform_after_submission',
        'classname' => FacebookWordpressGravityForms::class,
        'inject_function' => 'injectLeadEvent',
        'priority' => 30))
      ->once();

    FacebookWordpressGravityForms::injectPixelCode();
  }

  public function testInjectLeadEventWithoutAdmin() {
    self::mockIsAdmin(false);

    $mock_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mock_fbpixel->shouldReceive('getPixelLeadCode')
      ->with(array(), FacebookWordpressGravityForms::TRACKING_NAME, false)
      ->andReturn('gravityforms');
    FacebookWordpressGravityForms::injectLeadEvent('mock_entry', 'mock_form');
    $this->expectOutputRegex('/script[\s\S]+gravityforms/');
  }

  public function testInjectLeadEventWithAdmin() {
    self::mockIsAdmin(true);

    FacebookWordpressGravityForms::injectLeadEvent('mock_entry', 'mock_form');
    $this->expectOutputString("");
  }
}
