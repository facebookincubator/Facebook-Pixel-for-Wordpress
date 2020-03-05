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

use FacebookPixelPlugin\Integration\FacebookWordpressEasyDigitalDownloads;
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
final class FacebookWordpressEasyDigitalDownloadsTest
  extends FacebookWordpressTestBase {

  public function testInjectPixelCode() {
    $eventHookMap = array(
      'injectAddToCartEvent' => 'edd_after_download_content',
      'injectInitiateCheckoutEvent' => 'edd_after_checkout_cart',
      'injectViewContentEvent' => 'edd_after_download_content',
    );

    $mocked_base = \Mockery::mock(
      'alias:FacebookPixelPlugin\Integration\FacebookWordpressIntegrationBase');
    foreach ($eventHookMap as $event => $hook) {
      $mocked_base->shouldReceive('addPixelFireForHook')
        ->with(array(
          'hook_name' => $hook,
          'classname' => FacebookWordpressEasyDigitalDownloads::class,
          'inject_function' => $event))
        ->once();
    }

    FacebookWordpressEasyDigitalDownloads::injectPixelCode();
  }

  public function testInjectAddToCartEventWithoutAdmin() {
    self::mockIsAdmin(false);

    $download_id = '1234';
    FacebookWordpressEasyDigitalDownloads::injectAddToCartEvent($download_id);
    $this->expectOutputRegex(
      '/edd-add-to-cart[\s\S]+End Facebook Pixel Event Code/');
  }

  public function testInitiateCheckoutEventWithoutAdmin() {
    self::mockIsAdmin(false);
    self::mockUseS2S(true);
    $this->setupEDDMocks();

    FacebookWordpressEasyDigitalDownloads::injectInitiateCheckoutEvent();

    $this->expectOutputRegex(
      '/easy-digital-downloads[\s\S]+End Facebook Pixel Event Code/');

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
    $this->assertEquals('300', $event->getCustomData()->getValue());
  }

  public function testPurchaseEventWithoutAdmin() {
    self::mockIsAdmin(false);
    self::mockUseS2S(true);
    $this->setupEDDMocks();

    $payment = new class {
      public $ID = 1;
    };

    \WP_Mock::expectActionAdded(
      'wp_footer',
      array(
        'FacebookPixelPlugin\\Integration\\FacebookWordpressEasyDigitalDownloads',
        'injectPurchaseEvent'
      ),
      20);

    FacebookWordpressEasyDigitalDownloads::trackPurchaseEvent($payment, null);

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('Purchase', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('Pika', $event->getUserData()->getFirstName());
    $this->assertEquals('Chu', $event->getUserData()->getLastName());
    $this->assertEquals('USD', $event->getCustomData()->getCurrency());
    $this->assertEquals(700, $event->getCustomData()->getValue());
    $this->assertEquals('product', $event->getCustomData()->getContentType());
    $this->assertEquals([99, 999], $event->getCustomData()->getContentIds());
  }

  public function testInjectAddToCartEventWithAdmin() {
    self::mockIsAdmin(true);

    $download_id = '1234';
    FacebookWordpressEasyDigitalDownloads::injectAddToCartEvent($download_id);
    $this->expectOutputString("");
  }

  public function testInjectInitiateCheckoutEventWithAdmin() {
    self::mockIsAdmin(true);

    FacebookWordpressEasyDigitalDownloads::injectInitiateCheckoutEvent();
    $this->expectOutputString("");
  }

  public function testInjectPurchaseEventWithAdmin() {
    self::mockIsAdmin(true);
    $payment = array('ID' => '1234');
    FacebookWordpressEasyDigitalDownloads::trackPurchaseEvent($payment, null);
    $this->expectOutputString("");
  }

  public function testInjectViewContentEventWithAdmin() {
    self::mockIsAdmin(true);

    $download_id = '1234';
    FacebookWordpressEasyDigitalDownloads::injectViewContentEvent($download_id);
    $this->expectOutputString("");
  }

  private function setupEDDMocks() {
    \WP_Mock::userFunction('EDD');
    $mock_edd_utils = \Mockery::mock(
      'alias:FacebookPixelPlugin\Integration\EDDUtils');
    $mock_edd_utils->shouldReceive('getCurrency')->andReturn('USD');
    $mock_edd_utils->shouldReceive('getCartTotal')->andReturn(300);

    $this->mocked_fbpixel->shouldReceive('getLoggedInUserInfo')
      ->andReturn(array(
        'email' => 'pika.chu@s2s.com',
        'first_name' => 'Pika',
        'last_name' => 'Chu'
      )
    );

    \WP_Mock::userFunction('edd_get_payment_meta', array(
      'args' => 1,
      'return' => array(
        'email' => 'pika.chu@s2s.com',
        'user_info' => array(
          'first_name' => 'Pika',
          'last_name' => 'Chu'
        ),
        'cart_details' => array(
          array(
            'id' => 99,
            'price' => 300
          ),
          array(
            'id' => 999,
            'price' => 400
          )
        ),
        'currency' => 'USD'
      )
    ));
  }
}
