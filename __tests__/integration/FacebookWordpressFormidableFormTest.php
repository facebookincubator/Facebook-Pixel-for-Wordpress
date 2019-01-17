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

use FacebookPixelPlugin\Integration\FacebookWordpressFormidableForm;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

final class FacebookWordpressFormidableFormTest extends FacebookWordpressTestBase {
  public function testInjectPixelCode() {
    \Wp_Mock::expectActionAdded('frm_after_create_entry',
      array(FacebookWordpressFormidableForm::class, 'injectLeadEventHook'), 30, 2);

    FacebookWordpressFormidableForm::injectPixelCode();
    $this->assertHooksAdded();
  }

  public function testInjectLeadEventHook() {
    \WP_Mock::expectActionAdded('wp_footer',
      array(FacebookWordpressFormidableForm::class, 'injectLeadEvent'), 11);
    FacebookWordpressFormidableForm::injectLeadEventHook('mock_entry_id', 'mock_form_id');
    $this->assertHooksAdded();
  }

  public function testInjectLeadEventWithouAdmin() {
    self::mockIsAdmin(false);

    $mock_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mock_fbpixel->shouldReceive('getPixelLeadCode')
      ->with(array(), FacebookWordpressFormidableForm::TRACKING_NAME, false)
      ->andReturn('formidable-lite');
    FacebookWordpressFormidableForm::injectLeadEvent();
    $this->expectOutputRegex('/script[\s\S]+formidable-lite/');
  }

  public function testInjectLeadEventWithAdmin() {
    self::mockIsAdmin(true);

    FacebookWordpressFormidableForm::injectLeadEvent();
    $this->expectOutputString("");
  }
}
