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
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\UserData;

final class FacebookWordpressCalderaFormTest extends FacebookWordpressTestBase {
  public function testInjectPixelCode() {
    \WP_Mock::expectActionAdded('caldera_forms_ajax_return',
      array(FacebookWordpressCalderaForm::class, 'injectLeadEvent'),
      10, 2);

    $s2s_spy = \Mockery::spy('alias:FacebookPixelPlugin\Core\FacebookServerSideEvent');

    FacebookWordpressCalderaForm::injectPixelCode();
    $this->assertHooksAdded();

    $s2s_spy->shouldNotHaveReceived('send');
  }

  public function testInjectLeadEventWithoutAdminAndSubmitted() {
    self::mockIsAdmin(false);
    self::mockUseS2S(false);
    $mock_out = array('status' => 'complete', 'html' => 'successful submitted');
    $mock_form = array();

    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelLeadCode')
      ->andReturn('caldera-forms');

    $s2s_spy = \Mockery::spy('alias:FacebookPixelPlugin\Core\FacebookServerSideEvent');

    $out = FacebookWordpressCalderaForm::injectLeadEvent($mock_out, $mock_form);

    $this->assertArrayHasKey('html', $out);
    $code = $out['html'];
    $this->assertRegexp('/caldera-forms[\s\S]+End Facebook Pixel Event Code/', $code);

    $s2s_spy->shouldNotHaveReceived('send');
  }

  public function testInjectLeadEventWithoutAdminAndNotSubmitted() {
    self::mockIsAdmin(false);
    self::mockUseS2S(false);
    $mock_out = array('status' => 'preprocess', 'html' => 'fail to submit form');
    $mock_form = array();

    $s2s_spy = \Mockery::spy('alias:FacebookPixelPlugin\Core\FacebookServerSideEvent');

    $out = FacebookWordpressCalderaForm::injectLeadEvent($mock_out, $mock_form);

    $this->assertArrayHasKey('html', $out);
    $code = $out['html'];
    $this->assertEquals('fail to submit form', $code);

    $s2s_spy->shouldNotHaveReceived('send');
  }

  public function testInjectLeadEventWithAdmin() {
    self::mockIsAdmin(true);
    self::mockUseS2S(false);
    $mock_out = array('status' => 'complete', 'html' => 'successful submitted');
    $mock_form = array();

    $s2s_spy = \Mockery::spy('alias:FacebookPixelPlugin\Core\FacebookServerSideEvent');

    $out = FacebookWordpressCalderaForm::injectLeadEvent($mock_out, $mock_form);

    $this->assertArrayHasKey('html', $out);
    $code = $out['html'];
    $this->assertEquals('successful submitted', $code);

    $s2s_spy->shouldNotHaveReceived('send');
  }

  public function testSendLeadEventViaServerAPISuccessWithoutAdmin() {
    self::mockIsAdmin(false);
    self::mockUseS2S(true);
    $mock_out = array('status' => 'complete', 'html' => 'successful submitted');
    $mock_form = self::createMockForm();

    $mocked_fbpixel = \Mockery::mock('alias:FacebookPixelPlugin\Core\FacebookPixel');
    $mocked_fbpixel->shouldReceive('getPixelLeadCode')
      ->andReturn('caldera-forms');

    $s2s_spy = \Mockery::spy('alias:FacebookPixelPlugin\Core\FacebookServerSideEvent');

    $out = FacebookWordpressCalderaForm::injectLeadEvent($mock_out, $mock_form);

    $this->assertArrayHasKey('html', $out);
    $code = $out['html'];
    $this->assertRegexp('/caldera-forms[\s\S]+End Facebook Pixel Event Code/', $code);

    $s2s_spy->shouldHaveReceived('send')->with(\Mockery::on(function ($event) {
      $user_data = $event->getUserData();
      if ($event->getEventName() == 'Lead'
          && !is_null($event->getEventTime())
          && $user_data->getEmail() == 'pika.chu@s2s.com'
          && $user_data->getFirstName() == 'Pika'
          && $user_data->getLastName() == 'Chu') {
        return true;
      }

      return false;
    }));
  }

  public function testSendLeadEventViaServerAPIFailureWithoutAdmin() {
    self::mockIsAdmin(false);
    self::mockUseS2S(true);
    $mock_out = array('status' => 'preprocess', 'html' => 'fail to submit form');
    $mock_form = array();

    $s2s_spy = \Mockery::spy('alias:FacebookPixelPlugin\Core\FacebookServerSideEvent');

    $out = FacebookWordpressCalderaForm::injectLeadEvent($mock_out, $mock_form);

    $this->assertArrayHasKey('html', $out);
    $code = $out['html'];
    $this->assertEquals('fail to submit form', $code);

    $s2s_spy->shouldNotHaveReceived('send');
  }

  public function testSendLeadEventViaServerAPIFailureWithAdmin() {
    self::mockIsAdmin(true);
    self::mockUseS2S(true);
    $mock_out = array('status' => 'complete', 'html' => 'successful submitted');
    $mock_form = array();

    $s2s_spy = \Mockery::spy('alias:FacebookPixelPlugin\Core\FacebookServerSideEvent');

    $out = FacebookWordpressCalderaForm::injectLeadEvent($mock_out, $mock_form);

    $this->assertArrayHasKey('html', $out);
    $code = $out['html'];
    $this->assertEquals('successful submitted', $code);

    $s2s_spy->shouldNotHaveReceived('send');
  }

  private static function createMockForm() {
    $email_field = array(
      'ID' => 'fld_1',
      'type' => 'email'
    );

    $first_name_field = array(
      'ID' => 'fld_2',
      'slug' => 'first_name'
    );

    $last_name_field = array(
      'ID' => 'fld_3',
      'slug' => 'last_name'
    );

    $_POST['fld_1'] = 'pika.chu@s2s.com';
    $_POST['fld_2'] = 'Pika';
    $_POST['fld_3'] = 'Chu';

    return array('fields' => array($email_field, $first_name_field, $last_name_field));
  }
}
