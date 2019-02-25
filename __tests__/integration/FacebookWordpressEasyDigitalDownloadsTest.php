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

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in seperate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressEasyDigitalDownloadsTest extends FacebookWordpressTestBase {

  public function testInjectPixelCode() {
    $eventHookMap = array(
      'injectAddToCartEvent' => 'edd_after_download_content',
      'injectInitiateCheckoutEvent' => 'edd_after_checkout_cart',
      'injectPurchaseEvent' => 'edd_payment_receipt_after',
      'injectViewContentEvent' => 'edd_after_download_content',
    );

    $mocked_base = \Mockery::mock('alias:FacebookPixelPlugin\Integration\FacebookWordpressIntegrationBase');
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
    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelAddToCartCode')
      ->with('param', FacebookWordpressEasyDigitalDownloads::TRACKING_NAME, false)
      ->andReturn('edd-add-to-cart');

    FacebookWordpressEasyDigitalDownloads::injectAddToCartEvent($download_id);
    $this->expectOutputRegex('/edd-add-to-cart[\s\S]+End Facebook Pixel Event Code/');
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
    FacebookWordpressEasyDigitalDownloads::injectPurchaseEvent($payment);
    $this->expectOutputString("");
  }

  public function testInjectViewContentEventWithAdmin() {
    self::mockIsAdmin(true);

    $download_id = '1234';
    FacebookWordpressEasyDigitalDownloads::injectViewContentEvent($download_id);
    $this->expectOutputString("");
  }
}
