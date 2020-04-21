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

use FacebookPixelPlugin\Integration\FacebookWordpressWPForms;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in seperate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressWPFormsTest extends FacebookWordpressTestBase {
  public function testInjectPixelCode() {
    \WP_Mock::expectActionAdded(
      'wpforms_process_before',
      array(
        'FacebookPixelPlugin\\Integration\\FacebookWordpressWPForms',
        'trackEvent'
      ),
      20,
      2
    );

    FacebookWordpressWPForms::injectPixelCode();
  }

  public function testInjectLeadEventWithoutAdmin() {
    parent::mockIsAdmin(false);
    self::mockUseS2S(false);

    $event = ServerEventFactory::newEvent('Lead');
    FacebookServerSideEvent::getInstance()->track($event);

    FacebookWordpressWPForms::injectLeadEvent();
    $this->expectOutputRegex(
      '/wpforms-lite[\s\S]+End Facebook Pixel Event Code/');
  }

  public function testInjectLeadEventWithAdmin() {
    parent::mockIsAdmin(true);
    self::mockUseS2S(false);

    FacebookWordpressWPForms::injectLeadEvent('mock_form_data');
    $this->expectOutputString("");
  }

  public function testTrackEventWithoutAdmin() {
    self::mockIsAdmin(false);
    self::mockUseS2S(true);

    $mock_entry = $this->createMockEntry();
    $mock_form_data = $this->createMockFormData();

    \WP_Mock::expectActionAdded(
      'wp_footer',
      array(
        'FacebookPixelPlugin\\Integration\\FacebookWordpressWPForms',
        'injectLeadEvent'
      ),
      20
    );

    FacebookWordpressWPForms::trackEvent(
      $mock_entry, $mock_form_data);

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('Lead', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('Pika', $event->getUserData()->getFirstName());
    $this->assertEquals('Chu', $event->getUserData()->getLastName());
    $this->assertEquals('wpforms-lite',
      $event->getCustomData()->getCustomProperty('fb_integration_tracking'));
  }

  public function testTrackEventWithoutAdminSimpleFormat() {
    self::mockIsAdmin(false);
    self::mockUseS2S(true);

    $mock_entry = $this->createMockEntry(true);
    $mock_form_data = $this->createMockFormData(true);
    $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

    \WP_Mock::expectActionAdded(
      'wp_footer',
      array(
        'FacebookPixelPlugin\\Integration\\FacebookWordpressWPForms',
        'injectLeadEvent'
      ),
      20
    );

    FacebookWordpressWPForms::trackEvent(
      $mock_entry, $mock_form_data);

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('Lead', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
    $this->assertEquals('pika.chu@s2s.com', $event->getUserData()->getEmail());
    $this->assertEquals('Pika', $event->getUserData()->getFirstName());
    $this->assertEquals('Chu', $event->getUserData()->getLastName());
    $this->assertEquals('TEST_REFERER', $event->getEventSourceUrl());
  }

  private function createMockEntry($simple_format = false) {
    return array(
      'fields' => array(
        '0' => $simple_format
                ? 'Pika Chu'
                : array('first' => 'Pika', 'last' => 'Chu'),
        '1' => 'pika.chu@s2s.com'
      )
    );
  }

  private function createMockFormData($simple_format = false) {
    return array(
      'fields' => array(
        array(
          'type' => 'name',
          'id' => '0',
          'format' => $simple_format ? 'simple' : 'first-last'),
        array('type' => 'email', 'id' => '1')
      )
    );
  }
}
