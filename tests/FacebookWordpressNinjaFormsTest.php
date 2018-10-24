<?php
namespace FacebookPixelPlugin\Tests;

use FacebookPixelPlugin\Integration\FacebookWordpressNinjaForms;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Core\FacebookPixel;

final class FacebookWordpressNinjaFormsTest extends FacebookWordpressTestBase {

  public function testInjectLeadEventWithoutAdmin() {
    self::$mockUsePII = '1';

    FacebookWordpressOptions::initialize();
    FacebookPixel::initialize(1234);

    FacebookWordpressNinjaForms::injectLeadEvent();
    $this->expectOutputRegex('/facebookWordpressNinjaFormsController/');
  }

  public function testInjectLeadEventWithAdmin() {
    self::$isAdmin = true;

    FacebookWordpressNinjaForms::injectLeadEvent();
    $this->expectOutputString("");
  }

  public function testInjectLeadEventHook() {
    $this->assertEquals(self::$addActionCallCount, 0);
    FacebookWordpressNinjaForms::injectLeadEventHook('1234');
    $this->assertEquals(self::$addActionCallCount, 1);
  }

  public function testInjectPixelCode() {
    $this->assertEquals(self::$addActionCallCount, 0);
    FacebookWordpressNinjaForms::injectPixelCode();
    $this->assertEquals(self::$addActionCallCount, 1);
  }
}
