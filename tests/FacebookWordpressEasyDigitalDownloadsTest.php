<?php
namespace FacebookPixelPlugin\Tests;

use FacebookPixelPlugin\Integration\FacebookWordpressEasyDigitalDownloads;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Core\FacebookPixel;

final class FacebookWordpressEasyDigitalDownloadsTest extends FacebookWordpressTestBase {

  public function testInjectAddToCartEventWithoutAdmin() {
    self::$mockUsePII = '1';

    FacebookWordpressOptions::initialize();
    FacebookPixel::initialize(1234);

    FacebookWordpressEasyDigitalDownloads::injectAddToCartEvent();
    $this->expectOutputRegex('/edd-add-to-cart/');
  }

  public function testInjectAddToCartEventWithAdmin() {
    self::$isAdmin = true;

    FacebookWordpressEasyDigitalDownloads::injectAddToCartEvent();
    $this->expectOutputString("");
  }

  public function testInjectAddToCartEventHook() {
    $this->assertEquals(self::$addActionCallCount, 0);
    FacebookWordpressEasyDigitalDownloads::injectAddToCartEventHook('1234');
    $this->assertEquals(self::$addActionCallCount, 1);
  }

  public function testInjectInitiateCheckoutEventWithAdmin() {
    self::$isAdmin = true;

    FacebookWordpressEasyDigitalDownloads::injectInitiateCheckoutEvent();
    $this->expectOutputString("");
  }

  public function testInjectInitiateCheckoutEventHook() {
    $this->assertEquals(self::$addActionCallCount, 0);
    FacebookWordpressEasyDigitalDownloads::injectInitiateCheckoutEventHook();
    $this->assertEquals(self::$addActionCallCount, 1);
  }

  public function testInjectPurchaseEventWithAdmin() {
    self::$isAdmin = true;

    FacebookWordpressEasyDigitalDownloads::injectPurchaseEvent();
    $this->expectOutputString("");
  }

  public function testInjectPurchaseEventHook() {
    $this->assertEquals(self::$addActionCallCount, 0);
    FacebookWordpressEasyDigitalDownloads::injectPurchaseEventHook(
      (object) array('ID' => '1234'));
    $this->assertEquals(self::$addActionCallCount, 1);
  }

  public function testInjectViewContentEventWithAdmin() {
    self::$isAdmin = true;

    FacebookWordpressEasyDigitalDownloads::injectViewContentEvent();
    $this->expectOutputString("");
  }

  public function testInjectViewContentEventHook() {
    $this->assertEquals(self::$addActionCallCount, 0);
    FacebookWordpressEasyDigitalDownloads::injectViewContentEventHook('1234');
    $this->assertEquals(self::$addActionCallCount, 1);
  }

  public function testInjectPixelCode() {
    $this->assertEquals(self::$addActionCallCount, 0);
    FacebookWordpressEasyDigitalDownloads::injectPixelCode();
    $this->assertEquals(self::$addActionCallCount, 4);
  }
}
