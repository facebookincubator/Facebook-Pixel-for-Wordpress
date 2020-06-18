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

use FacebookPixelPlugin\Integration\FacebookWordpressWooCommerce;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Tests\Mocks\MockWC;
use FacebookPixelPlugin\Tests\Mocks\MockWCCart;
use FacebookPixelPlugin\Tests\Mocks\MockWCOrder;
use FacebookPixelPlugin\Tests\Mocks\MockWCProduct;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressWooCommerceTest extends FacebookWordpressTestBase {
  public function testInjectPixelCodeWithWooNotActive() {
    $this->mockFacebookForWooCommerce(false);

    \WP_Mock::expectActionAdded('woocommerce_after_checkout_form',
      array(FacebookWordpressWooCommerce::class,
        'trackInitiateCheckout'),
        40);

    FacebookWordpressWooCommerce::injectPixelCode();
  }

  public function testInjectPixelCodeWithWooActive() {
    $this->mockFacebookForWooCommerce(true);

    \WP_Mock::expectActionNotAdded('woocommerce_after_checkout_form',
      array(FacebookWordpressWooCommerce::class,
        'trackInitiateCheckout'),
        40);

    FacebookWordpressWooCommerce::injectPixelCode();
  }

  public function testPurchaseEventWithoutAdmin() {
    self::mockIsAdmin(false);
    self::mockUseS2S(true);

    $this->setupMocks();

    FacebookWordpressWooCommerce::trackPurchaseEvent(1);
    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('Purchase', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('Pika', $event->getUserData()->getFirstName());
    $this->assertEquals('Chu', $event->getUserData()->getLastName());
    $this->assertEquals('2062062006', $event->getUserData()->getPhone());
    $this->assertEquals('USD', $event->getCustomData()->getCurrency());
    $this->assertEquals(900, $event->getCustomData()->getValue());
    $this->assertEquals('wc_post_id_1',
      $event->getCustomData()->getContentIds()[0]);

    $contents = $event->getCustomData()->getContents();
    $this->assertCount(1, $contents);
    $this->assertEquals('wc_post_id_1', $contents[0]->getProductId());
    $this->assertEquals(3, $contents[0]->getQuantity());
    $this->assertEquals(300, $contents[0]->getItemPrice());

    $this->assertEquals('woocommerce',
      $event->getCustomData()->getCustomProperty('fb_integration_tracking'));
  }

  public function testInitiateCheckoutEventWithoutAdmin() {
    self::mockIsAdmin(false);
    self::mockUseS2S(true);

    $this->setupMocks();

    FacebookWordpressWooCommerce::trackInitiateCheckout();
    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];

    $this->assertEquals('InitiateCheckout', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('Pika', $event->getUserData()->getFirstName());
    $this->assertEquals('Chu', $event->getUserData()->getLastName());
    $this->assertEquals('USD', $event->getCustomData()->getCurrency());
    $this->assertEquals(900, $event->getCustomData()->getValue());
    $this->assertEquals(3, $event->getCustomData()->getNumItems());
    $this->assertEquals('wc_post_id_1',
      $event->getCustomData()->getContentIds()[0]);

    $contents = $event->getCustomData()->getContents();
    $this->assertCount(1, $contents);
    $this->assertEquals('wc_post_id_1', $contents[0]->getProductId());
    $this->assertEquals(3, $contents[0]->getQuantity());
    $this->assertEquals(300, $contents[0]->getItemPrice());

    $this->assertEquals('woocommerce',
      $event->getCustomData()->getCustomProperty('fb_integration_tracking'));
  }

  public function testAddToCartEventWithoutAdmin() {
    self::mockIsAdmin(false);
    self::mockUseS2S(true);

    $this->setupMocks();

    FacebookWordpressWooCommerce::trackAddToCartEvent(1, 1, 3, null);
    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];

    $this->assertEquals('AddToCart', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('Pika', $event->getUserData()->getFirstName());
    $this->assertEquals('Chu', $event->getUserData()->getLastName());
    $this->assertEquals('USD', $event->getCustomData()->getCurrency());
    $this->assertEquals(900, $event->getCustomData()->getValue());
    $this->assertEquals('wc_post_id_1',
      $event->getCustomData()->getContentIds()[0]);

    $this->assertEquals('woocommerce',
      $event->getCustomData()->getCustomProperty('fb_integration_tracking'));
  }

  private function mockFacebookForWooCommerce($active) {
    \WP_Mock::userFunction('get_option', array(
      'return' => $active ?
          array('facebook-for-woocommerce/facebook-for-woocommerce.php')
          : array()
    ));
  }

  private function setupMocks() {
    $this->mocked_fbpixel->shouldReceive('getLoggedInUserInfo')
      ->andReturn(array(
        'email' => 'pika.chu@s2s.com',
        'first_name' => 'Pika',
        'last_name' => 'Chu'
      )
    );

    \WP_Mock::userFunction('get_woocommerce_currency', array(
      'return' => 'USD')
    );

    $cart = new MockWCCart();
    $cart->add_item(1, 1, 3, 300);

    \WP_Mock::userFunction('WC', array(
      'return' => new MockWC($cart))
    );

    $order = new MockWCOrder(
      'Pika', 'Chu', 'pika.chu@s2s.com', '2062062006');
    $order->add_item(1, 3, 900);

    \WP_Mock::userFunction('wc_get_order', array(
      'return' => $order)
    );

    \WP_Mock::userFunction('wc_get_product', array(
      'return' => new MockWCProduct(1))
    );
  }
}
