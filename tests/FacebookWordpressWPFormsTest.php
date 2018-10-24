<?php
namespace FacebookPixelPlugin\Tests;

use FacebookPixelPlugin\Integration\FacebookWordpressWPForms;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Core\FacebookPixel;

final class FacebookWordpressWPFormsTest extends FacebookWordpressTestBase {

  public function testInjectLeadEventWithoutAdmin() {
    self::$mockUsePII = '1';

    FacebookWordpressOptions::initialize();
    FacebookPixel::initialize(1234);

    FacebookWordpressWPForms::injectLeadEvent();
    $this->expectOutputRegex('/wpforms-form/');
  }

  public function testInjectLeadEventWithAdmin() {
    self::$isAdmin = true;

    FacebookWordpressWPForms::injectLeadEvent();
    $this->expectOutputString("");
  }

  public function testInjectLeadEventHook() {
    $this->assertEquals(self::$addActionCallCount, 0);
    FacebookWordpressWPForms::injectLeadEventHook(array());
    $this->assertEquals(self::$addActionCallCount, 1);
  }

  public function testInjectPixelCode() {
    $this->assertEquals(self::$addActionCallCount, 0);
    FacebookWordpressWPForms::injectPixelCode();
    $this->assertEquals(self::$addActionCallCount, 1);
  }
}
