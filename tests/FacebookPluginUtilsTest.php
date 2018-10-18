<?php
namespace FacebookPixelPlugin\Tests;

use FacebookPixelPlugin\Core\FacebookPluginUtils;

final class FacebookPluginUtilsTest extends FacebookWordpressTestBase {
  public function testWhenIsPositiveInteger() {
    $this->assertTrue(FacebookPluginUtils::isPositiveInteger(1));
    $this->assertFalse(FacebookPluginUtils::isPositiveInteger(0));
    $this->assertFalse(FacebookPluginUtils::isPositiveInteger(-1));
  }
}
