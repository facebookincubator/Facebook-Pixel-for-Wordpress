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
      array(FacebookWordpressWPECommerce::class, 'injectAddToCartEvent'), 11);

    // InitiateCheckout
    $hook_name = 'hook';
    $inject_function = 'inject_function';
    $mocked_base = \Mockery::mock(FacebookWordpressTestBase::class);
    $mocked_base->shouldReceive('addPixelFireForHook')
      ->with($hook_name, $inject_function);
    // Purchase
    \WP_Mock::expectActionAdded('wpsc_transaction_results_shutdown',
      array(FacebookWordpressWPECommerce::class, 'injectPurchaseEvent'), 11, 3);

    FacebookWordpressWPECommerce::injectPixelCode();

    $this->assertHooksAdded();
  }

  public function testInjectAddToCartEventWithoutAdmin() {
    self::mockIsAdmin(false);
    $parameter = array('product_id' => 1, 'widget_output' => '');

    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelAddToCartCode')
      ->andReturn('wp-e-commerce');

    $mock_cart = \Mockery::mock();
    $mock_cart->shouldReceive('get_items')
      ->andReturn(array('1' => (object) array('product_id' => 1, 'unit_price' => 999)));

    $GLOBALS['wpsc_cart'] = $mock_cart;

    $response = FacebookWordpressWPECommerce::injectAddToCartEvent($parameter);

    $this->assertArrayHasKey('widget_output', $response);
    $code = $response['widget_output'];
    $this->assertRegexp('/wp-e-commerce[\s\S]+End Facebook Pixel Event Code/', $code);
  }

  public function testInjectAddToCartEventWithAdmin() {
    self::mockIsAdmin(true);
    $parameter = array('product_id' => 1, 'widget_output' => '');

    $response = FacebookWordpressWPECommerce::injectAddToCartEvent($parameter);

    $this->assertArrayHasKey('widget_output', $response);
    $code = $response['widget_output'];
    $this->assertEquals("", $code);
  }

  public function testInitiateCheckoutEventWithoutAdmin() {
    self::mockIsAdmin(false);

    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelInitiateCheckoutCode')
      ->andReturn('wp-e-commerce');
    FacebookWordpressWPECommerce::injectInitiateCheckoutEvent();
    $this->expectOutputRegex('/wp-e-commerce[\s\S]+End Facebook Pixel Event Code/');
  }

  public function testInitiateCheckoutEventWithAdmin() {
    self::mockIsAdmin(true);

    FacebookWordpressWPECommerce::injectInitiateCheckoutEvent();
    $this->expectOutputString("");
  }

  public function testInjectPurchaseEventWithoutAdmin() {
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

    FacebookWordpressWPECommerce::injectPurchaseEvent($purchase_log_object, $session_id, $display_to_screen);
    $this->expectOutputRegex('/wp-e-commerce[\s\S]+End Facebook Pixel Event Code/');
  }

  public function testInjectPurchaseEventWithAdmin() {
    self::mockIsAdmin(true);

    $mock_purchase_log_object = \Mockery::mock();
    $purchase_log_object = $mock_purchase_log_object;
    $session_id = null;
    $display_to_screen = true;

    $mock_purchase_log_object->shouldReceive('get_items')
      ->andReturn(array(0 => (object) array('prodid' => "1")));
    $mock_purchase_log_object->shouldReceive('get_total')
      ->andReturn(999);

    FacebookWordpressWPECommerce::injectPurchaseEvent($purchase_log_object, $session_id, $display_to_screen);
    $this->expectOutputString("");
  }
}
