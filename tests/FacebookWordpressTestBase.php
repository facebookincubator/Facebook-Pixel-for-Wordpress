<?php

namespace FacebookPixelPlugin\Tests;

use PHPUnit\Framework\TestCase;

abstract class FacebookWordpressTestBase extends TestCase {
  public static $addActionCallCount = 0;
  public static $checkedCallCount = 0;
  public static $escHtmlCallCount = 0;
  public static $isAdmin = false;
  public static $mockPixelId = 0;
  public static $mockUsePII = 0;
  public static $mockUserId = '0';

  public static function setUpBeforeClass() {
    \setup();
  }

  protected function setUp() {
    self::resetState();
  }

  private static function resetState() {
    self::$addActionCallCount = 0;
    self::$checkedCallCount = 0;
    self::$escHtmlCallCount = 0;
    self::$isAdmin = false;
    self::$mockPixelId = 0;
    self::$mockUsePII = '0';
    self::$mockUserId = 0;
  }
}
