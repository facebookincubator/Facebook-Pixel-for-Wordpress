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

use FacebookPixelPlugin\Integration\FacebookWordpressWooCommerce;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Tests\Mocks\MockWC;
use FacebookPixelPlugin\Tests\Mocks\MockWCCart;
use FacebookPixelPlugin\Tests\Mocks\MockWCOrder;
use FacebookPixelPlugin\Tests\Mocks\MockWCProduct;
use FacebookAds\Object\ServerSide\Event;

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

  public function testPurchaseEventWithoutInternalUser() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $this->setupMocks();

    FacebookWordpressWooCommerce::trackPurchaseEvent(1);
    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('Purchase', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('pika', $event->getUserData()->getFirstName());
    $this->assertEquals('chu', $event->getUserData()->getLastName());
    $this->assertEquals('2062062006', $event->getUserData()->getPhone());
    $this->assertEquals('springfield', $event->getUserData()->getCity());
    $this->assertEquals('ohio', $event->getUserData()->getState());
    $this->assertEquals('us', $event->getUserData()->getCountryCode());
    $this->assertEquals('12345', $event->getUserData()->getZipCode());
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

  public function testInitiateCheckoutEventWithoutInternalUser() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $this->setupMocks();
    $this->setupCustomerBillingAddress();

    FacebookWordpressWooCommerce::trackInitiateCheckout();
    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];

    $this->assertEquals('InitiateCheckout', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('pika', $event->getUserData()->getFirstName());
    $this->assertEquals('chu', $event->getUserData()->getLastName());
    $this->assertEquals('2062062006', $event->getUserData()->getPhone());
    $this->assertEquals('springfield', $event->getUserData()->getCity());
    $this->assertEquals('ohio', $event->getUserData()->getState());
    $this->assertEquals('us', $event->getUserData()->getCountryCode());
    $this->assertEquals('12345', $event->getUserData()->getZipCode());
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

  public function testAddToCartEventWithoutInternalUser() {
    \WP_Mock::userFunction(
      'wp_doing_ajax',
      array('return' => false)
    );
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $this->setupMocks();
    $this->setupCustomerBillingAddress();

    FacebookWordpressWooCommerce::trackAddToCartEvent(1, 1, 3, null);
    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];

    $this->assertEquals('AddToCart', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('pika', $event->getUserData()->getFirstName());
    $this->assertEquals('chu', $event->getUserData()->getLastName());
    $this->assertEquals('2062062006', $event->getUserData()->getPhone());
    $this->assertEquals('springfield', $event->getUserData()->getCity());
    $this->assertEquals('ohio', $event->getUserData()->getState());
    $this->assertEquals('us', $event->getUserData()->getCountryCode());
    $this->assertEquals('12345', $event->getUserData()->getZipCode());
    $this->assertEquals('USD', $event->getCustomData()->getCurrency());
    $this->assertEquals(900, $event->getCustomData()->getValue());
    $this->assertEquals('wc_post_id_1',
      $event->getCustomData()->getContentIds()[0]);

    $this->assertEquals('woocommerce',
      $event->getCustomData()->getCustomProperty('fb_integration_tracking'));
  }

  public function testAddToCartEventAjaxWithoutInternalUser() {
    \WP_Mock::userFunction(
      'wp_doing_ajax',
      array('return' => true)
    );
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $this->setupMocks();
    $this->setupCustomerBillingAddress();

    \WP_Mock::expectFilterAdded(
      'woocommerce_add_to_cart_fragments',
      array(
        FacebookWordpressWooCommerce::class,
        'addPixelCodeToAddToCartFragment'
      )
    );

    FacebookWordpressWooCommerce::trackAddToCartEvent(1, 1, 3, null);

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];

    $this->assertEquals('AddToCart', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('pika', $event->getUserData()->getFirstName());
    $this->assertEquals('chu', $event->getUserData()->getLastName());
    $this->assertEquals('2062062006', $event->getUserData()->getPhone());
    $this->assertEquals('springfield', $event->getUserData()->getCity());
    $this->assertEquals('ohio', $event->getUserData()->getState());
    $this->assertEquals('us', $event->getUserData()->getCountryCode());
    $this->assertEquals('12345', $event->getUserData()->getZipCode());
    $this->assertEquals('USD', $event->getCustomData()->getCurrency());
    $this->assertEquals(900, $event->getCustomData()->getValue());
    $this->assertEquals('wc_post_id_1',
      $event->getCustomData()->getContentIds()[0]);

    $this->assertEquals('woocommerce',
      $event->getCustomData()->getCustomProperty('fb_integration_tracking'));
  }

  public function testViewContentWithoutAdmin(){
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $this->setupMocks();
    $this->setupCustomerBillingAddress();

    $raw_post = new \stdClass();
    $raw_post->ID = 1;
    global $post;
    $post = $raw_post;

    FacebookWordpressWooCommerce::trackViewContentEvent();

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];

    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('pika', $event->getUserData()->getFirstName());
    $this->assertEquals('chu', $event->getUserData()->getLastName());
    $this->assertEquals('2062062006', $event->getUserData()->getPhone());
    $this->assertEquals('springfield', $event->getUserData()->getCity());
    $this->assertEquals('ohio', $event->getUserData()->getState());
    $this->assertEquals('us', $event->getUserData()->getCountryCode());
    $this->assertEquals('12345', $event->getUserData()->getZipCode());

    $this->assertEquals(10, $event->getCustomData()->getValue());
    $this->assertEquals('wc_post_id_1',
      $event->getCustomData()->getContentIds()[0]);
    $this->assertEquals('Stegosaurus',
      $event->getCustomData()->getContentName()
    );
    $this->assertEquals('product',
      $event->getCustomData()->getContentType());
    $this->assertEquals('USD',
      $event->getCustomData()->getCurrency()
    );
    $this->assertEquals('Dinosaurs',
      $event->getCustomData()->getContentCategory()
    );

    $this->assertEquals('woocommerce',
      $event->getCustomData()->getCustomProperty('fb_integration_tracking'));
  }

  public function testEnqueuePixelEvent(){
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $this->setupMocks();
    $server_event = new Event();
    $pixel_code = FacebookWordpressWooCommerce::enqueuePixelCode($server_event);
    $this->assertRegExp(
      '/woocommerce[\s\S]+End Meta Pixel Event Code/', $pixel_code);
  }

  public function testAddPixelCodeToAddToCartFragment(){
    self::mockFacebookWordpressOptions();

    $server_event = new Event();
    FacebookServerSideEvent::getInstance()->setPendingPixelEvent(
        'addPixelCodeToAddToCartFragment',
        $server_event
      );

    $fragments =
      FacebookWordpressWooCommerce::addPixelCodeToAddToCartFragment(array());

    $this->assertArrayHasKey('#'.FacebookWordpressWooCommerce::DIV_ID_FOR_AJAX_PIXEL_EVENTS, $fragments);
    $pxl_div_code =
      $fragments[
          '#'.FacebookWordpressWooCommerce::DIV_ID_FOR_AJAX_PIXEL_EVENTS
        ];
    $this->assertRegExp(
      '/id=\'fb-pxl-ajax-code\'[\s\S]+woocommerce/', $pxl_div_code);
  }

  private function mockFacebookForWooCommerce($active) {
    \WP_Mock::userFunction('get_option', array(
      'return' => $active ?
          array('facebook-for-woocommerce/facebook-for-woocommerce.php')
          : array()
    ));
  }

  private function setupCustomerBillingAddress(){
    \WP_Mock::userFunction( 'get_user_meta', array(
      'times' => 1,
      'args' => array( \WP_Mock\Functions::type('int'), 'billing_city', true ),
      'return' => 'Springfield'
      )
    );
    \WP_Mock::userFunction( 'get_user_meta', array(
      'times' => 1,
      'args' => array(\WP_Mock\Functions::type('int'), 'billing_state', true),
      'return' => 'Ohio'
      )
    );
    \WP_Mock::userFunction( 'get_user_meta', array(
      'times' => 1,
      'args' => array(\WP_Mock\Functions::type('int'), 'billing_postcode',
        true
      ),
      'return' => '12345'
      )
    );
    \WP_Mock::userFunction( 'get_user_meta', array(
      'times' => 1,
      'args' => array(\WP_Mock\Functions::type('int'), 'billing_country', true),
      'return' => 'US'
      )
    );
    \WP_Mock::userFunction( 'get_user_meta', array(
      'times' => 1,
      'args' => array(\WP_Mock\Functions::type('int'), 'billing_phone', true),
      'return' => '2062062006'
      )
    );
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
      'Pika', 'Chu', 'pika.chu@s2s.com', '2062062006',
      'Springfield', '12345', 'Ohio', 'US'
    );
    $order->add_item(1, 3, 900);

    \WP_Mock::userFunction('wc_get_order', array(
      'return' => $order)
    );

    \WP_Mock::userFunction('wc_get_product', array(
      'return' => new MockWCProduct(1, 'single_product', 'Stegosaurus', 10))
    );

    \WP_Mock::userFunction('get_current_user_id', array(
        'return' => 1
      )
    );
    $term = new \stdClass();
    $term->name = 'Dinosaurs';
    \WP_Mock::userFunction('get_the_terms', array(
        'return' => array($term)
      )
    );

    \WP_Mock::userFunction('wc_enqueue_js', array());
  }
}
