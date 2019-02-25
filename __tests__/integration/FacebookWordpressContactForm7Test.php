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

use FacebookPixelPlugin\Integration\FacebookWordpressContactForm7;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in seperate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressContactForm7Test extends FacebookWordpressTestBase {
  public function testInjectPixelCode() {
    $hook_name = 'hook';
    $inject_function = 'inject_function';
    $mocked_base = \Mockery::mock('alias:FacebookPixelPlugin\Integration\FacebookWordpressIntegrationBase');
    $mocked_base->shouldReceive('addPixelFireForHook')
      ->with(array(
        'hook_name' => 'wpcf7_contact_form',
        'classname' => FacebookWordpressContactForm7::class,
        'inject_function' => 'injectLeadEvent'))
      ->once();
    FacebookWordpressContactForm7::injectPixelCode();
  }

  public function testInjectLeadEventWithoutAdmin() {
    self::mockIsAdmin(false);

    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelLeadCode')
      ->with(array(), FacebookWordpressContactForm7::TRACKING_NAME, false)
      ->andReturn('contact-form-7');
    FacebookWordpressContactForm7::injectLeadEvent();
    $this->expectOutputRegex('/wpcf7submit[\s\S]+contact-form-7/');
  }

  public function testInjectLeadEventWithAdmin() {
    self::mockIsAdmin(true);

    FacebookWordpressContactForm7::injectLeadEvent();
    $this->expectOutputString("");
  }
}
