<?php
/**
 * Facebook Pixel Plugin FacebookWordpressWPFormsTest class.
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
use FacebookPixelPlugin\Core\CompositeDelivery;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Integration\FacebookWordpressWPForms;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressWPFormsTest class.
 *
 * These tests assert the same observable behavior as before the Signals
 * refactor: submitting a WPForms form ends up tracking a server-side Lead
 * event carrying the correct user/custom data. The only change is the entry
 * point — the data provider (read_form_data) plus the real Signals dispatch
 * path instead of the old trackEvent static method.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressWPFormsTest extends FacebookWordpressTestBase {

  /**
   * Builds a WPForms integration wired to a real Signals dispatcher, so a
   * dispatched event actually tracks through FacebookServerSideEvent.
   *
   * @return array [ FacebookWordpressWPForms, Signals ]
   */
  private function makeIntegration() {
    $signals     = new Signals();
    $integration = new FacebookWordpressWPForms(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * Mocks the current user, plugin options, and the JSON/sanitize helpers the
   * dispatch/render path relies on.
   *
   * @return void
   */
  private function setupUserAndOptions() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();

    $this->mocked_fbpixel->shouldReceive( 'get_logged_in_user_info' )
      ->andReturn( array() );

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
   * Asserts the shared user data carried on the tracked Lead event.
   *
   * @param object $event The tracked server event.
   * @return void
   */
  private function assertLeadUserData( $event ) {
    $user_data = $event->getUserData();
    $this->assertEquals( 'pika.chu@s2s.com', $user_data->getEmail() );
    $this->assertEquals( 'pika', $user_data->getFirstName() );
    $this->assertEquals( 'chu', $user_data->getLastName() );
    $this->assertEquals( '1234567', $user_data->getPhone() );
    $this->assertEquals( 'us', $user_data->getCountryCode() );
    $this->assertEquals( 'springfield', $user_data->getCity() );
    $this->assertEquals( 'ohio', $user_data->getState() );
    $this->assertEquals( '45401', $user_data->getZipCode() );
  }

  /**
   * set_up_tracking registers the Lead Signals event using a composite
   * delivery (footer + AJAX filters).
   *
   * @return void
   */
  public function testSetUpTrackingRegistersLeadSignalEvent() {
    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressWPForms(
      $signals,
      new EventDataBuilder()
    );

    $deliveries = array();
    $signals->expects( $this->once() )
      ->method( 'on' )
      ->willReturnCallback(
        function ( $hook, $event, $provider, $delivery ) use ( &$deliveries ) {
          $deliveries[ $event ] = $delivery;
        }
      );

    \WP_Mock::expectActionAdded(
      'wp_footer',
      array( $integration, 'inject_ajax_listener' ),
      9
    );

    $integration->set_up_tracking();

    $this->assertInstanceOf(
      CompositeDelivery::class,
      $deliveries['Lead']
    );
    $this->assertConditionsMet();
  }

  /**
   * A dispatched Lead tracks a Lead server event with the submission's user
   * data (first-last name format).
   *
   * @return void
   */
  public function testLeadEventWithoutInternalUser() {
    $this->setupUserAndOptions();

    list( $integration, $signals ) = $this->makeIntegration();

    $data = $integration->read_form_data(
      $this->createMockEntry(),
      $this->createMockFormData()
    );
    $signals->dispatch(
      'Lead',
      $data,
      FacebookWordpressWPForms::TRACKING_NAME
    );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 1, $tracked_events );

    $event = $tracked_events[0];
    $this->assertEquals( 'Lead', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
    $this->assertLeadUserData( $event );
    $this->assertEquals(
      'wpforms-lite',
      $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
    );
  }

  /**
   * A dispatched Lead tracks a Lead server event with the submission's user
   * data (simple name format), and prefers the referrer for the event source
   * URL.
   *
   * @return void
   */
  public function testLeadEventWithoutInternalUserSimpleFormat() {
    $this->setupUserAndOptions();
    $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

    list( $integration, $signals ) = $this->makeIntegration();

    $data = $integration->read_form_data(
      $this->createMockEntry( true ),
      $this->createMockFormData( true )
    );
    $signals->dispatch(
      'Lead',
      $data,
      FacebookWordpressWPForms::TRACKING_NAME
    );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 1, $tracked_events );

    $event = $tracked_events[0];
    $this->assertEquals( 'Lead', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
    $this->assertLeadUserData( $event );
    $this->assertEquals( 'TEST_REFERER', $event->getEventSourceUrl() );
    $this->assertEquals(
      'wpforms-lite',
      $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
    );
  }

  /**
   * read_form_data returns null (nothing to dispatch) when the entry is empty.
   *
   * @return void
   */
  public function testReadFormDataReturnsNullForEmptyEntry() {
    list( $integration ) = $this->makeIntegration();

    $this->assertNull(
      $integration->read_form_data(
        array(),
        $this->createMockFormData()
      )
    );
  }

  /**
   * read_form_data returns null (nothing to dispatch) when the form schema is
   * empty.
   *
   * @return void
   */
  public function testReadFormDataReturnsNullForEmptyFormData() {
    list( $integration ) = $this->makeIntegration();

    $this->assertNull(
      $integration->read_form_data(
        $this->createMockEntry(),
        array()
      )
    );
  }

  /**
   * Creates a mock entry with predefined field values.
   *
   * @param bool $simple_format Whether to use the simple format for the name
   *                            field.
   * @return array The mock entry with predefined field values.
   */
  private function createMockEntry( $simple_format = false ) {
    return array(
      'fields' => array(
        '0' => $simple_format ? 'Pika Chu' : array(
          'first' => 'Pika',
          'last'  => 'Chu',
        ),
        '1' => 'pika.chu@s2s.com',
        '2' => '1234567',
        '3' => array(
          'country' => 'US',
          'postal'  => '45401',
          'state'   => 'Ohio',
          'city'    => 'Springfield',
        ),
      ),
    );
  }

  /**
   * Creates a mock form data object with predefined field values.
   *
   * @param bool $simple_format Whether to use the simple format for the name
   *                            field.
   * @return array The mock form data object with predefined field values.
   */
  private function createMockFormData( $simple_format = false ) {
    return array(
      'fields' => array(
        array(
          'type'   => 'name',
          'id'     => '0',
          'format' => $simple_format ? 'simple' : 'first-last',
        ),
        array(
          'type' => 'email',
          'id'   => '1',
        ),
        array(
          'type' => 'phone',
          'id'   => '2',
        ),
        array(
          'type' => 'address',
          'id'   => '3',
        ),
      ),
    );
  }
}
