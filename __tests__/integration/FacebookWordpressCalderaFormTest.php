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

use FacebookPixelPlugin\Integration\FacebookWordpressCalderaForm;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

final class FacebookWordpressCalderaFormTest extends FacebookWordpressTestBase {
  public function testInjectPixelCode() {
    \WP_Mock::expectActionAdded('caldera_forms_ajax_return',
      array(FacebookWordpressCalderaForm::class, 'injectLeadEvent'),
      10, 2);

    FacebookWordpressCalderaForm::injectPixelCode();
    $this->assertHooksAdded();
  }

  public function testInjectLeadEventWithoutAdminAndSubmitted() {
    self::mockIsAdmin(false);
    self::mockUseS2S(false);
    $mock_out = array('status' => 'complete', 'html' => 'successful submitted');
    $mock_form = array();

    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelLeadCode')
      ->andReturn('caldera-forms');

    $out = FacebookWordpressCalderaForm::injectLeadEvent($mock_out, $mock_form);

    $this->assertArrayHasKey('html', $out);
    $code = $out['html'];
    $this->assertRegexp('/caldera-forms[\s\S]+End Facebook Pixel Event Code/', $code);
  }

  public function testInjectLeadEventWithoutAdminAndNotSubmitted() {
    self::mockIsAdmin(false);
    self::mockUseS2S(false);
    $mock_out = array('status' => 'preprocess', 'html' => 'fail to submit form');
    $mock_form = array();

    $out = FacebookWordpressCalderaForm::injectLeadEvent($mock_out, $mock_form);

    $this->assertArrayHasKey('html', $out);
    $code = $out['html'];
    $this->assertEquals('fail to submit form', $code);
  }

  public function testInjectLeadEventWithAdmin() {
    self::mockIsAdmin(true);
    self::mockUseS2S(false);
    $mock_out = array('status' => 'complete', 'html' => 'successful submitted');
    $mock_form = array();

    $out = FacebookWordpressCalderaForm::injectLeadEvent($mock_out, $mock_form);

    $this->assertArrayHasKey('html', $out);
    $code = $out['html'];
    $this->assertEquals('successful submitted', $code);
  }
}
