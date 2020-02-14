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
use FacebookPixelPlugin\Core\ServerEventHelper;
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

  public function testInjectLeadEventWithoutAdmin() {
    parent::mockIsAdmin(false);

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
    $result = FacebookWordpressNinjaForms::injectLeadEvent(
      $mock_actions,
      null,
      $mock_form_data,
    );

    $this->assertNotEmpty($result);
    $this->assertArrayHasKey('settings', $result[0]);
    $this->assertArrayHasKey('success_msg', $result[0]['settings']);
    $msg = $result[0]['settings']['success_msg'];
    $this->assertRegexp(
      '/ninja-forms[\s\S]+End Facebook Pixel Event Code/', $msg);

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('Lead', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('Pika', $event->getUserData()->getFirstName());
    $this->assertEquals('Chu', $event->getUserData()->getLastName());
  }

  public function testInjectLeadEventWithAdmin() {
    parent::mockIsAdmin(true);

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
    $fields = array($email, $name);

    return array('fields' => $fields);
  }
}
