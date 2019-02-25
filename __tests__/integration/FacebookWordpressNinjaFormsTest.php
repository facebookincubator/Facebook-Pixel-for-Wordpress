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
    \WP_Mock::expectActionAdded('ninja_forms_submission_actions', array(FacebookWordpressNinjaForms::class, 'injectLeadEvent'),
      10, 2);
    FacebookWordpressNinjaForms::injectPixelCode();
    $this->assertHooksAdded();
  }

  public function testInjectLeadEventWithoutAdmin() {
    parent::mockIsAdmin(false);
    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelLeadCode')
      ->andReturn('NinjaForms');

    $mock_actions = array(
      array(
        'id' => 1,
        'settings' => array(
          'type' => 'successmessage',
          'success_msg' => 'successful',
        ),
      ),
    );
    $result = FacebookWordpressNinjaForms::injectLeadEvent($mock_actions, 'mock_form_data');
    $this->assertNotEmpty($result);
    $this->assertArrayHasKey('settings', $result[0]);
    $this->assertArrayHasKey('success_msg', $result[0]['settings']);
    $msg = $result[0]['settings']['success_msg'];
    $this->assertRegexp('/NinjaForms[\s\S]+End Facebook Pixel Event Code/', $msg);
  }

  public function testInjectLeadEventWithAdmin() {
    parent::mockIsAdmin(true);

    $result = FacebookWordpressNinjaForms::injectLeadEvent('mock_actions', 'mock_form_data');
    $this->assertEquals('mock_actions', $result);
  }
}
