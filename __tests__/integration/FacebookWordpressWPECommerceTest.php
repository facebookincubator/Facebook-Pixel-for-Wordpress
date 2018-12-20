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
    \WP_Mock::expectActionAdded('wpsc_add_to_cart_json_response',
      array(FacebookWordpressWPECommerce::class, 'injectAddToCartEventHook'), 11);

    // InitiateCheckout
    \WP_Mock::expectActionAdded('wpsc_before_shopping_cart_page',
      array(FacebookWordpressWPECommerce::class, 'injectInitiateCheckoutEventHook'), 11);

    // Purchase
    \WP_Mock::expectActionAdded('wpsc_transaction_results_shutdown',
      array(FacebookWordpressWPECommerce::class, 'injectPurchaseEventHook'), 11, 3);

    FacebookWordpressWPECommerce::injectPixelCode();

    $this->assertHooksAdded();
  }

  public function testInjectInitiateCheckoutEventHook() {
    \WP_Mock::expectActionAdded('wp_footer',
      array(FacebookWordpressWPECommerce::class, 'injectInitiateCheckoutEvent'), 11);

    FacebookWordpressWPECommerce::injectInitiateCheckoutEventHook();
    $this->assertHooksAdded();
  }

  public function testInjectAddToCartEventHookWithoutAdmin() {
    self::mockIsAdmin(false);
    $parameter = array('product_id' => 1, 'widget_output' => '');

    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelAddToCartCode')
      ->andReturn('wp-e-commerce');

    $mock_cart = \Mockery::mock();
    $mock_cart->shouldReceive('get_items')
      ->andReturn(array('1' => (object) array('product_id' => 1, 'unit_price' => 999)));

    $GLOBALS['wpsc_cart'] = $mock_cart;

    $response = FacebookWordpressWPECommerce::injectAddToCartEventHook($parameter);

    $this->assertArrayHasKey('widget_output', $response);
    $code = $response['widget_output'];
    $this->assertRegexp('/wp-e-commerce[\s\S]+End Facebook Pixel Event Code/', $code);
  }

  public function testInitiateCheckoutEventWithoutAdmin() {
    self::mockIsAdmin(false);

    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelInitiateCheckoutCode')
      ->andReturn('wp-e-commerce');
    FacebookWordpressWPECommerce::injectInitiateCheckoutEvent();
    $this->expectOutputRegex('/wp-e-commerce[\s\S]+End Facebook Pixel Event Code/');
  }

  // TODO(T38225893): rewrite code in Administrator Mode.
  public function testInitiateCheckoutEventWithAdmin() {
    self::mockIsAdmin(true);

    FacebookWordpressWPECommerce::injectInitiateCheckoutEvent();
    $this->expectOutputString("");
  }

  public function testInjectPurchaseEventHookWithoutAdmin() {
    self::mockIsAdmin(false);

    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelPurchaseCode')
      ->andReturn('wp-e-commerce');

    $mock_purchase_log_object = \Mockery::mock();
    $purchase_log_object = $mock_purchase_log_object;
    $session_id = null;
    $display_to_screen = true;

    $mock_purchase_log_object->shouldReceive('get_items')
      ->andReturn(array(0 => (object) array('prodid' => "1")));
    $mock_purchase_log_object->shouldReceive('get_total')
      ->andReturn(999);

    FacebookWordpressWPECommerce::injectPurchaseEventHook($purchase_log_object, $session_id, $display_to_screen);
    $this->expectOutputRegex('/wp-e-commerce[\s\S]+End Facebook Pixel Event Code/');
  }

  // TODO(T38225893): test code in Administrator Mode.
}
