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

use FacebookPixelPlugin\Integration\FacebookWordpressWPECommerce;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Core\FacebookPixel;

final class FacebookWordpressWPECommerceTest extends FacebookWordpressTestBase {

  public function testInjectAddToCartEventWithoutAdmin() {
    self::$mockUsePII = '1';

    FacebookWordpressOptions::initialize();
    FacebookPixel::initialize(1234);

    FacebookWordpressWPECommerce::injectAddToCartEvent();
    $this->expectOutputRegex('/wp-e-commerce/');
    $this->expectOutputRegex('/AddToCart/');
  }

  public function testInjectAddToCartEventWithAdmin() {
    self::$isAdmin = true;

    FacebookWordpressWPECommerce::injectAddToCartEvent();
    $this->expectOutputString("");
  }

  public function testInjectAddToCartEventHook() {
    $this->assertEquals(self::$addActionCallCount, 0);
    FacebookWordpressWPECommerce::injectAddToCartEventHook();
    $this->assertEquals(self::$addActionCallCount, 1);
  }

  public function testInitiateCheckoutEventWithoutAdmin() {
    self::$mockUsePII = '1';

    FacebookWordpressOptions::initialize();
    FacebookPixel::initialize(1234);

    FacebookWordpressWPECommerce::injectInitiateCheckoutEvent();
    $this->expectOutputRegex('/wp-e-commerce/');
    $this->expectOutputRegex('/InitiateCheckout/');
  }

  public function testInitiateCheckoutEventWithAdmin() {
    self::$isAdmin = true;

    FacebookWordpressWPECommerce::injectInitiateCheckoutEvent();
    $this->expectOutputString("");
  }

  public function testInitiateCheckoutEventHook() {
    $this->assertEquals(self::$addActionCallCount, 0);
    FacebookWordpressWPECommerce::injectInitiateCheckoutEventHook();
    $this->assertEquals(self::$addActionCallCount, 1);
  }

  public function testInjectPixelCode() {
    $this->assertEquals(self::$addActionCallCount, 0);
    FacebookWordpressWPECommerce::injectPixelCode();
    $this->assertEquals(self::$addActionCallCount, 3);
  }
}
