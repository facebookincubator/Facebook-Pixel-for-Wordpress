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
      'injectInitiateCheckoutEvent' => 'edd_after_checkout_cart'
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

  public function testInjectAddToCartEventListenerWithoutInternalUser() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $download_id = '1234';
    FacebookWordpressEasyDigitalDownloads::injectAddToCartListener(
      $download_id
    );
    $this->expectOutputRegex(
      '/edd-add-to-cart[\s\S]+End Meta Pixel Event Code/');
  }

  public function testInjectAddToCartEventIdWithoutInternalUser() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    FacebookWordpressEasyDigitalDownloads::injectAddToCartEventId();
    $this->expectOutputRegex(
      '/input type="hidden" name="facebook_event_id"/');
  }

  public function testInitiateCheckoutEventWithoutInternalUser() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $this->setupEDDMocks();

    FacebookWordpressEasyDigitalDownloads::injectInitiateCheckoutEvent();

    $this->expectOutputRegex(
      '/easy-digital-downloads[\s\S]+End Meta Pixel Event Code/');

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
    $this->assertEquals('300', $event->getCustomData()->getValue());
    $this->assertEquals('easy-digital-downloads',
      $event->getCustomData()->getCustomProperty('fb_integration_tracking'));
  }

  public function testPurchaseEventWithoutInternalUser() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

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
    $this->assertEquals('pika', $event->getUserData()->getFirstName());
    $this->assertEquals('chu', $event->getUserData()->getLastName());
    $this->assertEquals('USD', $event->getCustomData()->getCurrency());
    $this->assertEquals(700, $event->getCustomData()->getValue());
    $this->assertEquals('product', $event->getCustomData()->getContentType());
    $this->assertEquals([99, 999], $event->getCustomData()->getContentIds());
    $this->assertEquals('easy-digital-downloads',
      $event->getCustomData()->getCustomProperty('fb_integration_tracking'));
  }

  public function testInjectAddToCartEventListenerWithInternalUser() {
    self::mockIsInternalUser(true);

    $download_id = '1234';
    FacebookWordpressEasyDigitalDownloads::injectAddToCartListener(
      $download_id
    );
    $this->expectOutputString("");
  }

  public function testInjectAddToCartEventIdWithInternalUser() {
    self::mockIsInternalUser(true);

    FacebookWordpressEasyDigitalDownloads::injectAddToCartEventId();
    $this->expectOutputString("");
  }

  public function testInjectInitiateCheckoutEventWithInternalUser() {
    self::mockIsInternalUser(true);

    FacebookWordpressEasyDigitalDownloads::injectInitiateCheckoutEvent();
    $this->expectOutputString("");
  }

  public function testInjectPurchaseEventWithInternalUser() {
    self::mockIsInternalUser(true);
    $payment = array('ID' => '1234');
    FacebookWordpressEasyDigitalDownloads::trackPurchaseEvent($payment, null);
    $this->expectOutputString("");
  }

  public function testInjectViewContentEventWithInternalUser() {
    self::mockIsInternalUser(true);

    $download_id = 1234;
    FacebookWordpressEasyDigitalDownloads::injectViewContentEvent($download_id);
    $this->expectOutputString("");
  }

  public function testInjectViewContentEventWithoutInternalUser() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $this->setupEDDMocks();

    FacebookWordpressEasyDigitalDownloads::injectViewContentEvent(1234);
    $this->expectOutputRegex(
      '/easy-digital-downloads[\s\S]+End Meta Pixel Event Code/');
    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $custom_data = $event->getCustomData();
    $user_data = $event->getUserData();

    $this->assertEquals('pika.chu@s2s.com', $user_data->getEmail());
    $this->assertEquals('pika', $user_data->getFirstName());
    $this->assertEquals('chu', $user_data->getLastName());
    $this->assertEquals('ViewContent', $event->getEventName());
    $this->assertEquals(['1234'], $custom_data->getContentIds() );
    $this->assertEquals('product', $custom_data->getContentType());
    $this->assertEquals('USD', $custom_data->getCurrency());
    $this->assertEquals('Encarta', $custom_data->getContentName());
    $this->assertEquals( 50, $custom_data->getValue());
    $this->assertNotNull($event->getEventTime());
  }

  public function testInjectAddToCartEventAjax() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $this->setupEDDMocks();

    FacebookWordpressEasyDigitalDownloads::injectAddToCartEventAjax();

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $custom_data = $event->getCustomData();
    $user_data = $event->getUserData();

    $this->assertEquals('abc-123', $event->getEventId());
    $this->assertEquals('pika.chu@s2s.com', $user_data->getEmail());
    $this->assertEquals('pika', $user_data->getFirstName());
    $this->assertEquals('chu', $user_data->getLastName());
    $this->assertEquals('AddToCart', $event->getEventName());
    $this->assertEquals(['1234'], $custom_data->getContentIds() );
    $this->assertEquals('product', $custom_data->getContentType());
    $this->assertEquals('USD', $custom_data->getCurrency());
    $this->assertEquals('Encarta', $custom_data->getContentName());
    $this->assertEquals( 50, $custom_data->getValue());
    $this->assertNotNull($event->getEventTime());
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

    \WP_Mock::userFunction('get_post_meta', array(
        'args' => array(
          1234,
          \WP_Mock\Functions::type( 'string' ),
          true
        ),
        'return' => array(
          array(
            'amount' => 50
          )
        )
      )
    );
    $download_object = new \stdClass;
    $download_object->post_title = 'Encarta';
    \WP_Mock::userFunction('edd_get_download', array(
        'args' => array(\WP_Mock\Functions::type( 'int' )),
        'return' => $download_object
      )
    );

    $_POST['nonce'] = '54321';
    $_POST['download_id'] = '1234';
    $_POST['post_data'] = 'facebook_event_id=abc-123';

    \WP_Mock::userFunction('absint', array(
        'args' => array(\WP_Mock\Functions::type( 'string' )),
        'return' => 1234
      )
    );

    \WP_Mock::userFunction('sanitize_text_field', array(
        'args' => array(\WP_Mock\Functions::type( 'string' )),
        'return' => '54321'
      )
    );

    \WP_Mock::userFunction('wp_verify_nonce', array(
        'args' => array(\WP_Mock\Functions::type( 'string' ),
          \WP_Mock\Functions::type( 'string' )),
        'return' => true
      )
    );

  }
}
