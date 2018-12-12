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

use FacebookPixelPlugin\Integration\FacebookWordpressWPForms;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

final class FacebookWordpressWPFormsTest extends FacebookWordpressTestBase {
  public function testInjectPixelCode() {
    \WP_Mock::expectActionAdded('wpforms_frontend_output',
      array(FacebookWordpressWPForms::class, 'injectLeadEventHook'), 11);
    FacebookWordpressWPForms::injectPixelCode();
    $this->assertHooksAdded();
  }

  public function testInjectLeadEventHook() {
    \WP_Mock::expectActionAdded('wp_footer',
      array(FacebookWordpressWPForms::class, 'injectLeadEvent'),
      11);
    FacebookWordpressWPForms::injectLeadEventHook('1234');
    $this->assertHooksAdded();
  }

  public function testInjectLeadEventWithoutAdmin() {
    parent::mockIsAdmin(false);

    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelLeadCode')
      ->with(array(), FacebookWordpressWPForms::TRACKING_NAME, false)
      ->andReturn('wpforms-form');
    FacebookWordpressWPForms::injectLeadEvent();
    $this->expectOutputRegex('/wpforms-form/');
    $this->expectOutputRegex('/End Facebook Pixel Event Code/');
  }

  public function testInjectLeadEventWithAdmin() {
    parent::mockIsAdmin(true);

    FacebookWordpressWPForms::injectLeadEvent();
    $this->expectOutputString("");
  }
}
