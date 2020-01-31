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
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressContactForm7Test
  extends FacebookWordpressTestBase {
  public function testInjectLeadEventWithoutAdmin() {
    self::mockIsAdmin(false);
    self::mockUseS2S(false);

    $mock_result = array(
      'status' => 'mail_sent',
      'message' => 'Thank you for your message');

    $result =
      FacebookWordpressContactForm7::injectLeadEvent(null, $mock_result);
    $this->assertRegexp(
      '/contact-form-7[\s\S]+End Facebook Pixel Event Code/',
      $result['message']);
  }

  public function testInjectLeadEventWithAdmin() {
    self::mockIsAdmin(true);

    $mock_result = array(
      'status' => 'mail_sent',
      'message' => 'Thank you for your message');

    $result =
      FacebookWordpressContactForm7::injectLeadEvent(null, $mock_result);
    $this->assertEquals('Thank you for your message', $result['message']);
  }
}
