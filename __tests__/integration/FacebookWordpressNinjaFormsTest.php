<?php
/*
 * Copyright (C) 2017-present, Meta, Inc.
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
use FacebookPixelPlugin\Core\FacebookServerSideEvent;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in seperate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressNinjaFormsTest extends FacebookWordpressTestBase {
  public function testInjectPixelCode() {
    \WP_Mock::expectActionAdded(
      'ninja_forms_submission_actions',
      array(FacebookWordpressNinjaForms::class, 'injectLeadEvent'),
      10,
      3
    );

    FacebookWordpressNinjaForms::injectPixelCode();
    $this->assertHooksAdded();
  }

  public function testInjectLeadEventWithoutInternalUser() {
    parent::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $mock_actions = array(
      array(
        'id' => 1,
        'settings' => array(
          'type' => 'successmessage',
          'success_msg' => 'successful',
        ),
      ),
    );

    $mock_form_data = $this->getMockFormData();
    $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

    $result = FacebookWordpressNinjaForms::injectLeadEvent(
      $mock_actions,
      null,
      $mock_form_data
    );

    $this->assertNotEmpty($result);
    $this->assertArrayHasKey('settings', $result[0]);
    $this->assertArrayHasKey('success_msg', $result[0]['settings']);
    $msg = $result[0]['settings']['success_msg'];
    $this->assertRegexp(
      '/ninja-forms[\s\S]+End Meta Pixel Event Code/', $msg);

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('Lead', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('pika', $event->getUserData()->getFirstName());
    $this->assertEquals('chu', $event->getUserData()->getLastName());
    $this->assertEquals('12345', $event->getUserData()->getPhone());
    $this->assertEquals('oh', $event->getUserData()->getState());
    $this->assertEquals('springfield', $event->getUserData()->getCity());
    $this->assertEquals('us', $event->getUserData()->getCountryCode());
    $this->assertEquals('4321', $event->getUserData()->getZipCode());
    $this->assertEquals('m', $event->getUserData()->getGender());
    $this->assertEquals('ninja-forms',
      $event->getCustomData()->getCustomProperty('fb_integration_tracking'));
    $this->assertEquals('TEST_REFERER', $event->getEventSourceUrl());
  }

  public function testInjectLeadEventWithInternalUser() {
    parent::mockIsInternalUser(true);

    $result = FacebookWordpressNinjaForms::injectLeadEvent(
      'mock_actions',
      'mock_form_cache',
      'mock_form_data'
    );

    $this->assertEquals('mock_actions', $result);
  }

  private function getMockFormData() {
    $email = array('key' => 'email', 'value' => 'pika.chu@s2s.com');
    $name = array('key' => 'name', 'value' => 'Pika Chu');
    $phone = array('key' => 'phone', 'value' => '12345');
    $city = array('key' => 'city', 'value' => 'Springfield');
    $state = array('key' => 'liststate', 'value' => 'OH');
    $country = array('key' => 'listcountry', 'value' => 'US');
    $zip = array('key' => 'zip', 'value' => '4321');
    $gender = array('key' => 'gender', 'value' => 'M');
    $fields = array($email, $name, $phone, $city,
      $state, $country, $zip, $gender);
    return array('fields' => $fields);
  }
}
