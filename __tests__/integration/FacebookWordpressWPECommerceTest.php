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

use FacebookPixelPlugin\Integration\FacebookWordpressWPECommerce;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

final class FacebookWordpressWPECommerceTest extends FacebookWordpressTestBase {
  public function testInjectPixelCode() {
    // AddToCart
    \WP_Mock::expectActionAdded('wpsc_product_form_fields_begin',
      array(FacebookWordpressWPECommerce::class, 'injectAddToCartEventHook'), 11);

    // InitiateCheckout
    \WP_Mock::expectActionAdded('wpsc_before_shopping_cart_page',
      array(FacebookWordpressWPECommerce::class, 'injectInitiateCheckoutEventHook'), 11);

    // Purchase
    \WP_Mock::expectActionAdded('wpsc_transaction_results_shutdown',
      array(FacebookWordpressWPECommerce::class, 'injectPurchaseEvent'), 11, 3);

    FacebookWordpressWPECommerce::injectPixelCode();

    $this->assertHooksAdded();
  }

  public function testInjectAddToCartEventHook() {
    \WP_Mock::expectActionAdded('wp_footer',
      array(FacebookWordpressWPECommerce::class, 'injectAddToCartEvent'), 11);

    FacebookWordpressWPECommerce::injectAddToCartEventHook();
    $this->assertHooksAdded();
  }

  public function testInjectInitiateCheckoutEventHook() {
    \WP_Mock::expectActionAdded('wp_footer',
      array(FacebookWordpressWPECommerce::class, 'injectInitiateCheckoutEvent'), 11);

    FacebookWordpressWPECommerce::injectInitiateCheckoutEventHook();
    $this->assertHooksAdded();
  }

  public function testInjectAddToCartEventWithoutAdmin() {
    self::mockIsAdmin(false);

    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelAddToCartCode')
      ->with('param', FacebookWordpressWPECommerce::TRACKING_NAME, false)
      ->andReturn('wp-e-commerce');
    FacebookWordpressWPECommerce::injectAddToCartEvent();
    $this->expectOutputRegex('/wp-e-commerce/');
    $this->expectOutputRegex('/End Facebook Pixel Event Code/');
  }

  public function testInjectAddToCartEventWithAdmin() {
    self::mockIsAdmin(true);

    FacebookWordpressWPECommerce::injectAddToCartEvent();
    $this->expectOutputString("");
  }

  public function testInitiateCheckoutEventWithoutAdmin() {
    self::mockIsAdmin(false);

    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelInitiateCheckoutCode')
      ->with(array(), FacebookWordpressWPECommerce::TRACKING_NAME, false)
      ->andReturn('wp-e-commerce');
    FacebookWordpressWPECommerce::injectInitiateCheckoutEvent();
    $this->expectOutputRegex('/wp-e-commerce/');
    $this->expectOutputRegex('/End Facebook Pixel Event Code/');
  }

  public function testInitiateCheckoutEventWithAdmin() {
    self::mockIsAdmin(true);

    FacebookWordpressWPECommerce::injectInitiateCheckoutEvent();
    $this->expectOutputString("");
  }
}
