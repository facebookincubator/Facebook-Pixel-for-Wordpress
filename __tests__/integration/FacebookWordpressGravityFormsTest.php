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
    \WP_Mock::expectActionAdded('gform_confirmation', array(FacebookWordpressGravityForms::class, 'injectLeadEvent'),
      10, 4);
    FacebookWordpressGravityForms::injectPixelCode();
    $this->assertHooksAdded();
  }

  public function testInjectLeadEventWithoutAdmin() {
    self::mockIsAdmin(false);

    $mock_confirm = 'mock_msg';
    $mock_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mock_fbpixel->shouldReceive('getPixelLeadCode')
      ->with(array(), FacebookWordpressGravityForms::TRACKING_NAME, false)
      ->andReturn('gravityforms');

    $mock_confirm = FacebookWordpressGravityForms::injectLeadEvent($mock_confirm, 'mock_form', 'mock_entry', true);
    $this->assertRegexp('/script[\s\S]+gravityforms/', $mock_confirm);
  }

  public function testInjectLeadEventWithAdmin() {
    self::mockIsAdmin(true);

    $mock_confirm = 'mock_msg';
    $mock_confirm = FacebookWordpressGravityForms::injectLeadEvent($mock_confirm, 'mock_form', 'mock_entry', true);
    $this->assertEquals('mock_msg', $mock_confirm);
  }
}
