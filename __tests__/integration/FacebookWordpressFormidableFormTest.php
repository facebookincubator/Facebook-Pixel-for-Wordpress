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

use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Integration\FacebookWordpressFormidableForm;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormField;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormFieldValue;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormEntryValues;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in seperate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressFormidableFormTest
  extends FacebookWordpressTestBase {

  public function testInjectPixelCode() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    \WP_Mock::expectActionAdded(
      'frm_after_create_entry',
      array(
        'FacebookPixelPlugin\\Integration\\FacebookWordpressFormidableForm',
        'trackServerEvent'
      ),
      20,
      2);

    FacebookWordpressFormidableForm::injectPixelCode();
  }

  public function testInjectLeadEventWithoutInternalUser() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $event = ServerEventFactory::newEvent('Lead');
    FacebookServerSideEvent::getInstance()->track($event);

    FacebookWordpressFormidableForm::injectLeadEvent();

    $this->expectOutputRegex('/script[\s\S]+formidable-lite/');
  }

  public function testInjectLeadEventWithInternalUser() {
    self::mockIsInternalUser(true);
    self::mockFacebookWordpressOptions();

    FacebookWordpressFormidableForm::injectLeadEvent();

    $this->expectOutputString("");
  }

  public function testTrackEventWithoutInternalUser() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $mock_entry_id = 1;
    $mock_form_id = 1;

    self::setupMockFormidableForm($mock_entry_id);
    $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

    \WP_Mock::expectActionAdded(
      'wp_footer',
      array(
        'FacebookPixelPlugin\\Integration\\FacebookWordpressFormidableForm',
        'injectLeadEvent'
      ),
      20
    );

    FacebookWordpressFormidableForm::trackServerEvent(
      $mock_entry_id, $mock_form_id);

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
    $this->assertEquals('45501', $event->getUserData()->getZipCode());
    $this->assertNull($event->getUserData()->getCountryCode());
    $this->assertEquals('formidable-lite',
      $event->getCustomData()->getCustomProperty('fb_integration_tracking'));
    $this->assertEquals('TEST_REFERER', $event->getEventSourceUrl());
  }

  public function testTrackEventWithoutInternalUserErrorReadingForm() {
    self::mockIsInternalUser(false);
    self::mockFacebookWordpressOptions();

    $mock_entry_id = 1;
    $mock_form_id = 1;

    self::setupErrorForm($mock_entry_id);

    FacebookWordpressFormidableForm::trackServerEvent(
      $mock_entry_id, $mock_form_id);

    $tracked_events =
      FacebookServerSideEvent::getInstance()->getTrackedEvents();

    $this->assertCount(1, $tracked_events);

    $event = $tracked_events[0];
    $this->assertEquals('Lead', $event->getEventName());
    $this->assertNotNull($event->getEventTime());
  }

  private static function setupErrorForm($entry_id) {
    $entry_values = new MockFormidableFormEntryValues(array());
    $entry_values->set_throw(true);

    $mock_utils = \Mockery::mock(
      'alias:FacebookPixelPlugin\Integration\IntegrationUtils');
    $mock_utils->shouldReceive('getFormidableFormsEntryValues')->with($entry_id)->andReturn($entry_values);
  }

  private static function setupMockFormidableForm($entry_id) {
    $email = new MockFormidableFormFieldValue(
      new MockFormidableFormField('email', null, null),
      'pika.chu@s2s.com'
    );

    $first_name = new MockFormidableFormFieldValue(
      new MockFormidableFormField('text', 'Name', 'First'),
      'Pika'
    );

    $last_name = new MockFormidableFormFieldValue(
      new MockFormidableFormField('text', 'Last', 'Last'),
      'Chu'
    );

    $phone = new MockFormidableFormFieldValue(
      new MockFormidableFormField('phone', null, null),
      '123456'
    );

    $address = new MockFormidableFormFieldValue(
      new MockFormidableFormField('address', null, null),
      array(
        'city' => 'Springfield',
        'state' => 'Ohio',
        'zip' => '45501',
        'country' => 'United States'
      )
    );

    $entry_values = new MockFormidableFormEntryValues(
      array($email, $first_name, $last_name, $phone, $address)
    );

    $mock_utils = \Mockery::mock(
      'alias:FacebookPixelPlugin\Integration\IntegrationUtils');
    $mock_utils->shouldReceive('getFormidableFormsEntryValues')->with($entry_id)->andReturn($entry_values);
  }
}
