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

use FacebookPixelPlugin\Integration\FacebookWordpressWPForms;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in seperate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressWPFormsTest extends FacebookWordpressTestBase {
  public function testInjectPixelCode() {
    $mocked_base = \Mockery::mock('alias:FacebookPixelPlugin\Integration\FacebookWordpressIntegrationBase');
    $mocked_base->shouldReceive('addPixelFireForHook')
      ->with(array(
        'hook_name' => 'wpforms_frontend_output',
        'classname' => FacebookWordpressWPForms::class,
        'inject_function' => 'injectLeadEvent'));

    FacebookWordpressWPForms::injectPixelCode();
  }

  public function testInjectLeadEventWithoutAdmin() {
    parent::mockIsAdmin(false);

    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelLeadCode')
      ->with(array(), FacebookWordpressWPForms::TRACKING_NAME, false)
      ->andReturn('wpforms-form');
    FacebookWordpressWPForms::injectLeadEvent('mock_form_data');
    $this->expectOutputRegex('/wpforms-form[\s\S]+End Facebook Pixel Event Code/');
  }

  public function testInjectLeadEventWithAdmin() {
    parent::mockIsAdmin(true);

    FacebookWordpressWPForms::injectLeadEvent('mock_form_data');
    $this->expectOutputString("");
  }
}
