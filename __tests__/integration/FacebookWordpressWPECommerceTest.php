<?php
/*
 * Copyright (C) 2017-present, Meta, Inc.
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
use FacebookPixelPlugin\Core\FacebookServerSideEvent;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in seperate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressWPECommerceTest extends FacebookWordpressTestBase {
  public function testInjectPixelCode() {
    // AddToCart
    \WP_Mock::expectActionAdded('wpsc_add_to_cart_json_response',
      array(FacebookWordpressWPECommerce::class, 'injectAddToCartEvent'), 11);

    // InitiateCheckout
    $mocked_base = \Mockery::mock('alias:FacebookPixelPlugin\Integration\FacebookWordpressIntegrationBase');
    $mocked_base->shouldReceive('addPixelFireForHook')
      ->with(array(
        'hook_name' => 'wpsc_before_shopping_cart_page',
        'classname' => FacebookWordpressWPECommerce::class,
        'inject_function' => 'injectInitiateCheckoutEvent'))
      ->once();
    // Purchase
    \WP_Mock::expectActionAdded('wpsc_transaction_results_shutdown',
      array(FacebookWordpressWPECommerce::class, 'injectPurchaseEvent'), 11, 3);

    FacebookWordpressWPECommerce::injectPixelCode();

    $this->assertHooksAdded();
  }

  public function testInjectAddToCartEventWithoutInternalUser() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $parameter = array('product_id' => 1, 'widget_output' => '');

    $this->setupMocks();

    $response = FacebookWordpressWPECommerce::injectAddToCartEvent($parameter);

    $this->assertArrayHasKey('widget_output', $response);
    $code = $response['widget_output'];
    $this->assertRegexp(
      '/wp-e-commerce[\s\S]+End Meta Pixel Event Code/', $code);

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('AddToCart', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('pika', $event->getUserData()->getFirstName());
    $this->assertEquals('chu', $event->getUserData()->getLastName());
    $this->assertEquals('USD', $event->getCustomData()->getCurrency());
    $this->assertEquals(999, $event->getCustomData()->getValue());
    $this->assertEquals('product', $event->getCustomData()->getContentType());
    $this->assertEquals([1], $event->getCustomData()->getContentIds());
    $this->assertEquals('wp-e-commerce',
      $event->getCustomData()->getCustomProperty('fb_integration_tracking'));
  }

  public function testInjectAddToCartEventWithInternalUser() {
    self::mockIsInternalUser(true);
    $parameter = array('product_id' => 1, 'widget_output' => '');

    $response = FacebookWordpressWPECommerce::injectAddToCartEvent($parameter);

    $this->assertArrayHasKey('widget_output', $response);
    $code = $response['widget_output'];
    $this->assertEquals("", $code);
  }

  public function testInitiateCheckoutEventWithoutInternalUser() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $this->setupMocks();

    FacebookWordpressWPECommerce::injectInitiateCheckoutEvent();
    $this->expectOutputRegex(
      '/wp-e-commerce[\s\S]+End Meta Pixel Event Code/');

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('InitiateCheckout', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('pika', $event->getUserData()->getFirstName());
    $this->assertEquals('chu', $event->getUserData()->getLastName());
    $this->assertEquals('USD', $event->getCustomData()->getCurrency());
    $this->assertEquals(999, $event->getCustomData()->getValue());
    $this->assertEquals('wp-e-commerce',
      $event->getCustomData()->getCustomProperty('fb_integration_tracking'));
  }

  public function testInitiateCheckoutEventWithInternalUser() {
    self::mockIsInternalUser(true);

    FacebookWordpressWPECommerce::injectInitiateCheckoutEvent();
    $this->expectOutputString("");
  }

  public function testInjectPurchaseEventWithoutInternalUser() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $this->setupMocks();

    $mock_purchase_log_object = \Mockery::mock();
    $purchase_log_object = $mock_purchase_log_object;
    $session_id = null;
    $display_to_screen = true;

    $mock_purchase_log_object->shouldReceive('get_items')
      ->andReturn(array(0 => (object) array('prodid' => "1")));
    $mock_purchase_log_object->shouldReceive('get_total')
      ->andReturn(999);

    FacebookWordpressWPECommerce::injectPurchaseEvent(
      $purchase_log_object,
      $session_id,
      $display_to_screen);

    $this->expectOutputRegex(
      '/wp-e-commerce[\s\S]+End Meta Pixel Event Code/');

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('Purchase', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('pika', $event->getUserData()->getFirstName());
    $this->assertEquals('chu', $event->getUserData()->getLastName());
    $this->assertEquals('USD', $event->getCustomData()->getCurrency());
    $this->assertEquals(999, $event->getCustomData()->getValue());
    $this->assertEquals('wp-e-commerce',
      $event->getCustomData()->getCustomProperty('fb_integration_tracking'));
  }

  public function testInjectPurchaseEventWithInternalUser() {
    self::mockIsInternalUser(true);

    $mock_purchase_log_object = \Mockery::mock();
    $purchase_log_object = $mock_purchase_log_object;
    $session_id = null;
    $display_to_screen = true;

    $mock_purchase_log_object->shouldReceive('get_items')
      ->andReturn(array(0 => (object) array('prodid' => "1")));
    $mock_purchase_log_object->shouldReceive('get_total')
      ->andReturn(999);

    FacebookWordpressWPECommerce::injectPurchaseEvent(
      $purchase_log_object, $session_id, $display_to_screen);
    $this->expectOutputString("");
  }

  private function setupMocks() {
    $mock_cart = \Mockery::mock();
    $mock_cart->shouldReceive('get_items')
      ->andReturn(
        array('1' => (object) array('product_id' => 1, 'unit_price' => 999)));

    $GLOBALS['wpsc_cart'] = $mock_cart;

    $this->mocked_fbpixel->shouldReceive('getLoggedInUserInfo')
      ->andReturn(array(
        'email' => 'pika.chu@s2s.com',
        'first_name' => 'Pika',
        'last_name' => 'Chu'
      )
    );

    \WP_Mock::userFunction('wpsc_get_currency_code', array(
      'return' => 'USD')
    );
  }
}
