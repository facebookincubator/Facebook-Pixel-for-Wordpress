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

use FacebookPixelPlugin\Integration\FacebookWordpressGravityForms;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

final class FacebookWordpressGravityFormsTest extends FacebookWordpressTestBase {
  public function testInjectPixelCode() {
    \Wp_Mock::expectActionAdded('gform_after_submission',
      array(FacebookWordpressGravityForms::class, 'injectLeadEventHook'), 10, 2);

    FacebookWordpressGravityForms::injectPixelCode();
    $this->assertHooksAdded();
  }

  public function testInjectLeadEventHook() {
    \WP_Mock::expectActionAdded('wp_footer',
      array(FacebookWordpressGravityForms::class, 'injectLeadEvent'), 11);
    FacebookWordpressGravityForms::injectLeadEventHook('mock_entry', 'mock_form');
    $this->assertHooksAdded();
  }

  public function testInjectLeadEventWithoutAdmin() {
    self::mockIsAdmin(false);

    $mock_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mock_fbpixel->shouldReceive('getPixelLeadCode')
      ->with(array(), FacebookWordpressGravityForms::TRACKING_NAME, false)
      ->andReturn('gravityforms');
    FacebookWordpressGravityForms::injectLeadEvent();
    $this->expectOutputRegex('/script[\s\S]+gravityforms/');
  }

  public function testInjectLeadEventWithAdmin() {
    self::mockIsAdmin(true);

    FacebookWordpressGravityForms::injectLeadEvent();
    $this->expectOutputString("");
  }
}
