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

use FacebookPixelPlugin\Integration\FacebookWordpressMailchimpForWp;
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
final class FacebookWordpressMailchimpForWpTest extends FacebookWordpressTestBase {
  public function testInjectPixelCode() {
    $mocked_base = \Mockery::mock(
      'alias:FacebookPixelPlugin\Integration\FacebookWordpressIntegrationBase');
    $mocked_base->shouldReceive('addPixelFireForHook')
      ->with(array(
        'hook_name' => 'mc4wp_form_subscribed',
        'classname' => FacebookWordpressMailchimpForWp::class,
        'inject_function' => 'injectLeadEvent'))
      ->once();
    FacebookWordpressMailchimpForWp::injectPixelCode();
  }

  public function testInjectLeadEventWithoutInternalUser() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $_POST['EMAIL'] = 'pika.chu@s2s.com';
    $_POST['FNAME'] = 'Pika';
    $_POST['LNAME'] = 'Chu';
    $_POST['PHONE'] = '123456';
    $_POST['ADDRESS'] = array(
      'city' => 'Springfield',
      'state' => 'Ohio',
      'zip' => '54321',
      'country' => 'US'
    );
    $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

    FacebookWordpressMailchimpForWp::injectLeadEvent();
    $this->expectOutputRegex(
      '/mailchimp-for-wp[\s\S]+End Meta Pixel Event Code/');

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('Lead', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('pika', $event->getUserData()->getFirstName());
    $this->assertEquals('chu', $event->getUserData()->getLastName());
    $this->assertEquals('123456', $event->getUserData()->getPhone());
    $this->assertEquals('springfield', $event->getUserData()->getCity());
    $this->assertEquals('ohio', $event->getUserData()->getState());
    $this->assertEquals('54321', $event->getUserData()->getZipCode());
    $this->assertEquals('us', $event->getUserData()->getCountryCode());
    $this->assertEquals('mailchimp-for-wp',
      $event->getCustomData()->getCustomProperty('fb_integration_tracking'));
    $this->assertEquals('TEST_REFERER', $event->getEventSourceUrl());
  }

  public function testInjectLeadEventWithInternalUser() {
    self::mockIsInternalUser(true);
    FacebookWordpressMailchimpForWp::injectLeadEvent();
    $this->expectOutputString("");
  }
}
