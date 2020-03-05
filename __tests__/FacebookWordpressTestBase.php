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

namespace FacebookPixelPlugin\Tests;

use \WP_Mock\Tools\TestCase;

abstract class FacebookWordpressTestBase extends TestCase {
  public function setUp() {
    \WP_Mock::setUp();
    $GLOBALS['wp_version'] = '1.0';
    \Mockery::getConfiguration()->setConstantsMap([
      'FacebookPixelPlugin\Core\FacebookPixel' => [
        'FB_INTEGRATION_TRACKING_KEY' => 'fb_integration_tracking',
      ],
    ]);
  }

  public function tearDown() {
    $this->addToAssertionCount(
      \Mockery::getContainer()->mockery_getExpectationCount());
    unset($GLOBALS['wp_version']);
    \WP_Mock::tearDown();
  }

  // mock Wordpress Core Function
  protected function mockIsAdmin($is_admin) {
    $this->mocked_fbpixel = \Mockery::mock
      ('alias:FacebookPixelPlugin\Core\FacebookPluginUtils');
    $this->mocked_fbpixel->shouldReceive('isAdmin')
      ->andReturn($is_admin);
  }

  protected function mockUseS2S($use_s2s, $use_pii = true) {
    $this->mocked_options = \Mockery::mock(
      'alias:FacebookPixelPlugin\Core\FacebookWordpressOptions');
    $this->mocked_options->shouldReceive('getUseS2S')->andReturn($use_s2s);
    $this->mocked_options->shouldReceive('getUsePii')->andReturn($use_pii);
  }
}
