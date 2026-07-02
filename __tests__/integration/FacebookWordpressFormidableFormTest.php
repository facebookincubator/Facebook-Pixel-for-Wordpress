<?php
/**
 * Facebook Pixel Plugin FacebookWordpressFormidableFormTest class.
 *
 * @package FacebookPixelPlugin
 */

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

use FacebookPixelPlugin\Core\Signals;
use FacebookPixelPlugin\Core\EventDataBuilder;
use FacebookPixelPlugin\Core\FooterDelivery;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Integration\FacebookWordpressFormidableForm;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormField;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormFieldValue;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormEntryValues;

/**
 * FacebookWordpressFormidableFormTest class.
 *
 * These tests assert the same observable behavior as before the Signals
 * refactor: a Formidable entry submission ends up tracking a Lead server-side
 * event carrying the correct user data. The only change is the entry point —
 * read_form_data plus the real Signals dispatch path instead of the old
 * trackServerEvent static method.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressFormidableFormTest
  extends FacebookWordpressTestBase {

  /**
   * Builds a Formidable integration wired to a real Signals dispatcher, so a
   * dispatched event actually tracks through FacebookServerSideEvent.
   *
   * @return array [ FacebookWordpressFormidableForm, Signals ]
   */
  private function makeIntegration() {
    $signals     = new Signals();
    $integration = new FacebookWordpressFormidableForm(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * Stubs the JSON/sanitize helpers the dispatch/render path relies on.
   *
   * @return void
   */
  private function mockRenderHelpers() {
    \WP_Mock::userFunction(
      'wp_json_encode',
      array(
        'args'   => array( \Mockery::type( 'array' ), \Mockery::type( 'int' ) ),
        'return' => function ( $data, $options ) {
          return json_encode( $data );
        },
      )
    );
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );
  }

  /**
   * set_up_tracking registers the Lead event on Signals with the footer
   * delivery.
   *
   * @return void
   */
  public function testSetUpTrackingRegistersLeadEvent() {
    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressFormidableForm(
      $signals,
      new EventDataBuilder()
    );

    $signals->expects( $this->once() )
      ->method( 'on' )
      ->with(
        'frm_after_create_entry',
        'Lead',
        $this->isType( 'callable' ),
        $this->isInstanceOf( FooterDelivery::class ),
        FacebookWordpressFormidableForm::TRACKING_NAME,
        20,
        2
      );

    $integration->set_up_tracking();

    $this->assertConditionsMet();
  }

  /**
   * A dispatched Lead tracks a Lead server event carrying the submitter's user
   * data and the formidable-lite tracking property — the same outcome the old
   * trackServerEvent path asserted.
   *
   * @return void
   */
  public function testTrackEventWithoutInternalUser() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();
    $this->mockRenderHelpers();

    $mock_entry_id = 1;
    $mock_form_id  = 1;

    self::setupMockFormidableForm( $mock_entry_id );
    $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

    list( $integration, $signals ) = $this->makeIntegration();

    $data = $integration->read_form_data( $mock_entry_id, $mock_form_id );
    $signals->dispatch(
      'Lead',
      $data,
      FacebookWordpressFormidableForm::TRACKING_NAME
    );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 1, $tracked_events );

    $event = $tracked_events[0];
    $this->assertEquals( 'Lead', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
    $this->assertEquals(
      'pika.chu@s2s.com',
      $event->getUserData()->getEmail()
    );
    $this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
    $this->assertEquals( 'chu', $event->getUserData()->getLastName() );
    $this->assertEquals( '123456', $event->getUserData()->getPhone() );
    $this->assertEquals( 'springfield', $event->getUserData()->getCity() );
    $this->assertEquals( 'ohio', $event->getUserData()->getState() );
    $this->assertEquals( '45501', $event->getUserData()->getZipCode() );
    $this->assertNull( $event->getUserData()->getCountryCode() );
    $this->assertEquals(
      'formidable-lite',
      $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
    );
    $this->assertEquals( 'TEST_REFERER', $event->getEventSourceUrl() );
  }

  /**
   * read_form_data returns null (nothing dispatched) when the entry id is
   * empty.
   *
   * @return void
   */
  public function testReadFormDataSkipsWhenEntryIdEmpty() {
    list( $integration ) = $this->makeIntegration();

    $this->assertNull( $integration->read_form_data( 0, 1 ) );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 0, $tracked_events );
  }

  /**
   * read_form_data returns null (nothing dispatched) when the entry carries no
   * field values.
   *
   * @return void
   */
  public function testReadFormDataSkipsWhenNoFieldValues() {
    $mock_entry_id = 1;

    $entry_values = new MockFormidableFormEntryValues( array() );
    $mock_utils   = \Mockery::mock(
      'alias:FacebookPixelPlugin\Integration\IntegrationUtils'
    );
    $mock_utils->shouldReceive( 'get_formidable_forms_entry_values' )
      ->with( $mock_entry_id )
      ->andReturn( $entry_values );

    list( $integration ) = $this->makeIntegration();

    $this->assertNull( $integration->read_form_data( $mock_entry_id, 1 ) );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 0, $tracked_events );
  }

  /**
   * Alias-mocks the Formidable entry values with sample email/name/phone/
   * address fields.
   *
   * @param int $entry_id The mocked entry id.
   * @return void
   */
  private static function setupMockFormidableForm( $entry_id ) {
    $email = new MockFormidableFormFieldValue(
      new MockFormidableFormField( 'email', null, null ),
      'pika.chu@s2s.com'
    );

    $first_name = new MockFormidableFormFieldValue(
      new MockFormidableFormField( 'text', 'Name', 'First' ),
      'Pika'
    );

    $last_name = new MockFormidableFormFieldValue(
      new MockFormidableFormField( 'text', 'Last', 'Last' ),
      'Chu'
    );

    $phone = new MockFormidableFormFieldValue(
      new MockFormidableFormField( 'phone', null, null ),
      '123456'
    );

    $address = new MockFormidableFormFieldValue(
      new MockFormidableFormField( 'address', null, null ),
      array(
        'city'    => 'Springfield',
        'state'   => 'Ohio',
        'zip'     => '45501',
        'country' => 'United States',
      )
    );

    $entry_values = new MockFormidableFormEntryValues(
      array( $email, $first_name, $last_name, $phone, $address )
    );

    $mock_utils = \Mockery::mock(
      'alias:FacebookPixelPlugin\Integration\IntegrationUtils'
    );
    $mock_utils->shouldReceive( 'get_formidable_forms_entry_values' )
      ->with( $entry_id )
      ->andReturn( $entry_values );
  }
}
