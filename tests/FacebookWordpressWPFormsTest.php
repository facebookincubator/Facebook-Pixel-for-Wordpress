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

namespace FacebookPixelPlugin\Tests;

use FacebookPixelPlugin\Integration\FacebookWordpressWPForms;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Core\FacebookPixel;

final class FacebookWordpressWPFormsTest extends FacebookWordpressTestBase {

  public function testInjectLeadEventWithoutAdmin() {
    self::$mockUsePII = '1';

    FacebookWordpressOptions::initialize();
    FacebookPixel::initialize(1234);

    FacebookWordpressWPForms::injectLeadEvent();
    $this->expectOutputRegex('/wpforms-form/');
  }

  public function testInjectLeadEventWithAdmin() {
    self::$isAdmin = true;

    FacebookWordpressWPForms::injectLeadEvent();
    $this->expectOutputString("");
  }

  public function testInjectLeadEventHook() {
    $this->assertEquals(self::$addActionCallCount, 0);
    FacebookWordpressWPForms::injectLeadEventHook(array());
    $this->assertEquals(self::$addActionCallCount, 1);
  }

  public function testInjectPixelCode() {
    $this->assertEquals(self::$addActionCallCount, 0);
    FacebookWordpressWPForms::injectPixelCode();
    $this->assertEquals(self::$addActionCallCount, 1);
  }
}
