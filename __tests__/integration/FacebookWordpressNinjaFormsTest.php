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

use FacebookPixelPlugin\Integration\FacebookWordpressNinjaForms;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

final class FacebookWordpressNinjaFormsTest extends FacebookWordpressTestBase {
  public function testInjectPixelCode() {
    \WP_Mock::expectActionAdded('ninja_forms_display_after_form', array(FacebookWordpressNinjaForms::class, 'injectLeadEventHook'),
      11);
    FacebookWordpressNinjaForms::injectPixelCode();
    $this->assertHooksAdded();
  }

  public function testInjectLeadEventHook() {
    \WP_Mock::expectActionAdded('wp_footer',
      array(FacebookWordpressNinjaForms::class, 'injectLeadEvent'),
      11);
    FacebookWordpressNinjaForms::injectLeadEventHook('1234');
    $this->assertHooksAdded();
  }

  public function testInjectLeadEventWithoutAdmin() {
    parent::mockIsAdmin(false);
    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelLeadCode')
      ->andReturn('facebookWordpressNinjaFormsController');
    FacebookWordpressNinjaForms::injectLeadEvent();
    $this->expectOutputRegex('/facebookWordpressNinjaFormsController[\s\S]+End Facebook Pixel Event Code/');
  }

  public function testInjectLeadEventWithAdmin() {
    parent::mockIsAdmin(true);

    FacebookWordpressNinjaForms::injectLeadEvent();
    $this->expectOutputString("");
  }
}
