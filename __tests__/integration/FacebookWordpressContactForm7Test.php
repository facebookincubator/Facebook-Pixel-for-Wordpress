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

use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Integration\FacebookWordpressContactForm7;
use FacebookPixelPlugin\Tests\Mocks\MockContactForm7;
use FacebookPixelPlugin\Tests\Mocks\MockContactForm7Tag;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\ServerEventFactory;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */

final class FacebookWordpressContactForm7Test
  extends FacebookWordpressTestBase {
  public function testInjectLeadEventWithoutInternalUser() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $mock_response = array(
      'status' => 'mail_sent',
      'message' => 'Thank you for your message');

    $event = ServerEventFactory::newEvent('Lead');
    FacebookServerSideEvent::getInstance()->track($event);

    $response =
      FacebookWordpressContactForm7::injectLeadEvent($mock_response, null);
    $this->assertRegexp(
      '/Lead[\s\S]+contact-form-7/',
      $response['fb_pxl_code']);
  }

  public function testTrackServerEventWithoutInternalUser() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $mock_result = array(
      'status' => 'mail_sent',
      'message' => 'Thank you for your message');

    $mock_form = $this->createMockForm();
    $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

    \WP_Mock::expectActionAdded(
      'wpcf7_feedback_response',
      array(
        'FacebookPixelPlugin\\Integration\\FacebookWordpressContactForm7',
        'injectLeadEvent'
      ),
      20, 2
    );

    $result =
      FacebookWordpressContactForm7::trackServerEvent($mock_form, $mock_result);

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('Lead', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('pika', $event->getUserData()->getFirstName());
    $this->assertEquals('chu', $event->getUserData()->getLastName());
    $this->assertEquals('12223334444', $event->getUserData()->getPhone());
    $this->assertEquals('contact-form-7',
      $event->getCustomData()->getCustomProperty('fb_integration_tracking'));
    $this->assertEquals('TEST_REFERER', $event->getEventSourceUrl());
  }

  public function testTrackServerEventWithoutFormData() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $mock_result = array(
      'status' => 'mail_sent',
      'message' => 'Thank you for your message');

    $mock_form = new MockContactForm7();

    \WP_Mock::expectActionAdded(
      'wpcf7_feedback_response',
      array(
        'FacebookPixelPlugin\\Integration\\FacebookWordpressContactForm7',
        'injectLeadEvent'
      ),
      20, 2
    );

    $result =
      FacebookWordpressContactForm7::trackServerEvent($mock_form, $mock_result);

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('Lead', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
  }

  public function testTrackServerEventErrorReadingData() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $mock_result = array(
      'status' => 'mail_sent',
      'message' => 'Thank you for your message');

    $mock_form = new MockContactForm7();
    $mock_form->set_throw(true);

    \WP_Mock::expectActionAdded(
      'wpcf7_feedback_response',
      array(
        'FacebookPixelPlugin\\Integration\\FacebookWordpressContactForm7',
        'injectLeadEvent'
      ),
      20, 2
    );

    $result =
      FacebookWordpressContactForm7::trackServerEvent($mock_form, $mock_result);

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('Lead', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
  }

  public function testInjectLeadEventWithInternalUser() {
    self::mockIsInternalUser(true);

    $mock_response = array(
      'status' => 'mail_sent',
      'message' => 'Thank you for your message');

    $response =
      FacebookWordpressContactForm7::injectLeadEvent($mock_response, null);
    $this->assertArrayNotHasKey( 'fb_pxl_code', $response);
  }

  public function testInjectLeadEventWhenMailFails() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $bad_statuses = [
      'validation_failed',
      'acceptance_missing',
      'spam',
      'aborted',
      'mail_failed',
    ];

    $mock_form = new MockContactForm7();
    $mock_form->set_throw(true);

    foreach ($bad_statuses as $status) {
      $mock_result = array(
        'status' => $status,
        'message' => 'Error bad status'
      );
      FacebookWordpressContactForm7::trackServerEvent($mock_form, $mock_result);
    }

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(0, $tracked_events);
  }

  private function createMockForm() {
    $mock_form = new MockContactForm7();

    $mock_form->add_tag('email', 'your-email', 'pika.chu@s2s.com');
    $mock_form->add_tag('text', 'your-name', 'Pika Chu');
    $mock_form->add_tag('tel', 'your-phone-number', '12223334444');

    return $mock_form;
  }
}
