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

use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Integration\FacebookWordpressContactForm7;
use FacebookPixelPlugin\Tests\Mocks\MockContactForm7;
use FacebookPixelPlugin\Tests\Mocks\MockContactForm7Tag;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

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
  public function testInjectLeadEventWithoutAdmin() {
    self::mockIsAdmin(false);
    self::mockUseS2S(false);

    $mock_result = array(
      'status' => 'mail_sent',
      'message' => 'Thank you for your message');

    $result =
      FacebookWordpressContactForm7::injectLeadEvent(null, $mock_result);
    $this->assertRegexp(
      '/contact-form-7[\s\S]+End Facebook Pixel Event Code/',
      $result['message']);
  }

  public function testInjectLeadEventFiresS2SEventsWithoutAdmin() {
    self::mockIsAdmin(false);
    self::mockUseS2S(true);

    $mock_result = array(
      'status' => 'mail_sent',
      'message' => 'Thank you for your message');

    $mock_form = $this->createMockForm();

    $result =
      FacebookWordpressContactForm7::injectLeadEvent($mock_form, $mock_result);

    $this->assertRegexp(
      '/contact-form-7[\s\S]+End Facebook Pixel Event Code/',
      $result['message']);

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

  public function testInjectLeadEventFiresS2SEventsWithoutFormData() {
    self::mockIsAdmin(false);
    self::mockUseS2S(true);

    $mock_result = array(
      'status' => 'mail_sent',
      'message' => 'Thank you for your message');

    $mock_form = new MockContactForm7();

    $result =
      FacebookWordpressContactForm7::injectLeadEvent($mock_form, $mock_result);

    $this->assertRegexp(
      '/contact-form-7[\s\S]+End Facebook Pixel Event Code/',
      $result['message']);

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('Lead', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
  }

  public function testInjectLeadEventFiresS2SEventsEvenWithErrorReadingData() {
    self::mockIsAdmin(false);
    self::mockUseS2S(true);

    $mock_result = array(
      'status' => 'mail_sent',
      'message' => 'Thank you for your message');

    $mock_form = new MockContactForm7();
    $mock_form->set_throw(true);

    $result =
      FacebookWordpressContactForm7::injectLeadEvent($mock_form, $mock_result);

    $this->assertRegexp(
      '/contact-form-7[\s\S]+End Facebook Pixel Event Code/',
      $result['message']);

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('Lead', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
  }

  public function testInjectLeadEventWithAdmin() {
    self::mockIsAdmin(true);

    $mock_result = array(
      'status' => 'mail_sent',
      'message' => 'Thank you for your message');

    $result =
      FacebookWordpressContactForm7::injectLeadEvent(null, $mock_result);
    $this->assertEquals('Thank you for your message', $result['message']);
  }

  private function createMockForm() {
    $mock_form = new MockContactForm7();

    $mock_form->add_tag('email', 'your-email', 'pika.chu@s2s.com');
    $mock_form->add_tag('text', 'your-name', 'Pika Chu');

    return $mock_form;
  }
}
