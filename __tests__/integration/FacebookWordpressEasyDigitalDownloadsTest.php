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

final class FacebookWordpressEasyDigitalDownloadsTest extends FacebookWordpressTestBase {

  public function testInjectPixelCode() {
    // AddToCart
    \WP_Mock::expectActionAdded('edd_after_download_content',
      array(FacebookWordpressEasyDigitalDownloads::class, 'injectAddToCartEventHook'),
      11);

    // InitiateCheckout
    \WP_Mock::expectActionAdded('edd_after_checkout_cart',
      array(FacebookWordpressEasyDigitalDownloads::class, 'injectInitiateCheckoutEventHook'),
      11);

    // Purchase
    \WP_Mock::expectActionAdded('edd_payment_receipt_after',
      array(FacebookWordpressEasyDigitalDownloads::class, 'injectPurchaseEventHook'),
      11);

    // ViewContent
    \WP_Mock::expectActionAdded('edd_after_download_content',
      array(FacebookWordpressEasyDigitalDownloads::class, 'injectViewContentEventHook'),
      11);

    FacebookWordpressEasyDigitalDownloads::injectPixelCode();

    $this->assertHooksAdded();
  }

  public function testiInjectAddToCartEventHook() {
    \WP_Mock::expectActionAdded('wp_footer',
      array(FacebookWordpressEasyDigitalDownloads::class, 'injectAddToCartEvent'),
      11);

    FacebookWordpressEasyDigitalDownloads::injectAddToCartEventHook('1234');

    $this->assertHooksAdded();
  }

  public function testInjectInitiateCheckoutEventHook() {
    \WP_Mock::expectActionAdded('wp_footer',
      array(FacebookWordpressEasyDigitalDownloads::class, 'injectInitiateCheckoutEvent'),
      11);

    FacebookWordpressEasyDigitalDownloads::injectInitiateCheckoutEventHook();

    $this->assertHooksAdded();
  }

  public function testInjectPurchaseEventHook() {
    \WP_Mock::expectActionAdded('wp_footer',
      array(FacebookWordpressEasyDigitalDownloads::class, 'injectPurchaseEvent'),
      11);

    FacebookWordpressEasyDigitalDownloads::injectPurchaseEventHook(
      (object) array('ID' => '1234'));

    $this->assertHooksAdded();
  }

  public function testInjectViewContentEventHook() {
    \WP_Mock::expectActionAdded('wp_footer',
      array(FacebookWordpressEasyDigitalDownloads::class, 'injectViewContentEvent'),
      11);

    FacebookWordpressEasyDigitalDownloads::injectViewContentEventHook('1234');

    $this->assertHooksAdded();
  }

  public function testInjectAddToCartEventWithoutAdmin() {
    self::mockIsAdmin(false);

    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelAddToCartCode')
      ->with('param', FacebookWordpressEasyDigitalDownloads::TRACKING_NAME, false)
      ->andReturn('edd-add-to-cart');

    FacebookWordpressEasyDigitalDownloads::injectAddToCartEvent();
    $this->expectOutputRegex('/edd-add-to-cart[\s\S]+End Facebook Pixel Event Code/');
  }

  public function testInjectAddToCartEventWithAdmin() {
    self::mockIsAdmin(true);

    FacebookWordpressEasyDigitalDownloads::injectAddToCartEvent();
    $this->expectOutputString("");
  }

  public function testInjectInitiateCheckoutEventWithAdmin() {
    self::mockIsAdmin(true);

    FacebookWordpressEasyDigitalDownloads::injectInitiateCheckoutEvent();
    $this->expectOutputString("");
  }

  public function testInjectPurchaseEventWithAdmin() {
    self::mockIsAdmin(true);

    FacebookWordpressEasyDigitalDownloads::injectPurchaseEvent();
    $this->expectOutputString("");
  }

  public function testInjectViewContentEventWithAdmin() {
    self::mockIsAdmin(true);

    FacebookWordpressEasyDigitalDownloads::injectViewContentEvent();
    $this->expectOutputString("");
  }
}
