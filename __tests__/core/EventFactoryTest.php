<?php
/**
 * Facebook Pixel Plugin EventFactoryTest class.
 *
 * @package FacebookPixelPlugin
 */

/**
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

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Core\AAMSettingsFields;
use FacebookPixelPlugin\Core\EventFactory;
use FacebookPixelPlugin\Core\FacebookParamBuilder;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

use FacebookPixelPlugin\FacebookAds\Object\ServerSide\AdsPixelSettings;

/**
 * EventFactoryTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class EventFactoryTest extends FacebookWordpressTestBase {
  /**
   * Passthrough mock for sanitize_text_field.
   *
   * @return void
   */
  private function mockSanitize() {
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

  public function testCreateHasEventId() {
    $this->mockSanitize();

    $event = EventFactory::create( 'Lead', array(), 'test' );

    $this->assertNotNull( $event->getEventId() );
    $this->assertEquals( 36, strlen( $event->getEventId() ) );
  }

  public function testCreateHasEventTime() {
    $this->mockSanitize();

    $event = EventFactory::create( 'Lead', array(), 'test' );

    $this->assertNotNull( $event->getEventTime() );
    $this->assertLessThan( 1, time() - $event->getEventTime() );
  }

  public function testCreateHasEventName() {
    $this->mockSanitize();

    $event = EventFactory::create( 'Lead', array(), 'test' );

    $this->assertEquals( 'Lead', $event->getEventName() );
  }

  public function testCreateHasActionSource() {
    $this->mockSanitize();

    $event = EventFactory::create( 'ViewContent', array(), 'test' );

    $this->assertEquals( 'website', $event->getActionSource() );
  }

  public function testCreateWiresRequestUserIdentifiersOntoEvent() {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '24.17.77.101';
    $_SERVER['HTTP_USER_AGENT']      = 'HTTP_USER_AGENT_VALUE';
    $_COOKIE['_fbp']                 = '_fbp_value';
    $_COOKIE['_fbc']                 = '_fbc_value';
    $this->mockSanitize();

    $event     = EventFactory::create( 'Lead', array(), 'test' );
    $user_data = $event->getUserData();

    $this->assertEquals( '24.17.77.101', $user_data->getClientIpAddress() );
    $this->assertEquals(
      'HTTP_USER_AGENT_VALUE',
      $user_data->getClientUserAgent()
    );

    $param_builder_fbp = FacebookParamBuilder::get_fbp();
    if ( ! empty( $param_builder_fbp ) ) {
      $this->assertEquals( $param_builder_fbp, $user_data->getFbp() );
    } else {
      $this->assertEquals( '_fbp_value', $user_data->getFbp() );
    }

    $param_builder_fbc = FacebookParamBuilder::get_fbc();
    if ( ! empty( $param_builder_fbc ) ) {
      $this->assertEquals( $param_builder_fbc, $user_data->getFbc() );
    } else {
      $this->assertEquals( '_fbc_value', $user_data->getFbc() );
    }
  }

  public function testCreateStampsIntegrationTracking() {
    $this->mockSanitize();

    $event = EventFactory::create( 'Lead', array(), 'my-integration' );

    $this->assertEquals(
      'my-integration',
      $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
    );
  }

  public function testCreateHasEventSourceUrlWithHttps() {
    $_SERVER['HTTPS']       = 'anyvalue';
    $_SERVER['HTTP_HOST']   = 'www.pikachu.com';
    $_SERVER['REQUEST_URI'] = '/index.php';
    $this->mockSanitize();

    $event = EventFactory::create( 'Lead', array(), 'test' );

    $this->assertEquals(
      'https://www.pikachu.com/index.php',
      $event->getEventSourceUrl()
    );
  }

  public function testCreateHasEventSourceUrlWithHttp() {
    $_SERVER['HTTPS']       = '';
    $_SERVER['HTTP_HOST']   = 'www.pikachu.com';
    $_SERVER['REQUEST_URI'] = '/index.php';
    $this->mockSanitize();

    $event = EventFactory::create( 'Lead', array(), 'test' );

    $this->assertEquals(
      'http://www.pikachu.com/index.php',
      $event->getEventSourceUrl()
    );
  }

  public function testCreateHasEventSourceUrlWithHttpsOff() {
    $_SERVER['HTTPS']       = 'off';
    $_SERVER['HTTP_HOST']   = 'www.pikachu.com';
    $_SERVER['REQUEST_URI'] = '/index.php';
    $this->mockSanitize();

    $event = EventFactory::create( 'Lead', array(), 'test' );

    $this->assertEquals(
      'http://www.pikachu.com/index.php',
      $event->getEventSourceUrl()
    );
  }

  public function testCreateEventSourceUrlPreferReferer() {
    $_SERVER['HTTPS']        = 'off';
    $_SERVER['HTTP_HOST']    = 'www.pikachu.com';
    $_SERVER['REQUEST_URI']  = '/index.php';
    $_SERVER['HTTP_REFERER'] = 'http://referrer/';
    $this->mockSanitize();

    $event = EventFactory::create( 'Lead', array(), 'test', true );

    $this->assertEquals( 'http://referrer/', $event->getEventSourceUrl() );
  }

  public function testCreateEventSourceUrlWithoutReferer() {
    $_SERVER['HTTPS']       = 'off';
    $_SERVER['HTTP_HOST']   = 'www.pikachu.com';
    $_SERVER['REQUEST_URI'] = '/index.php';
    $this->mockSanitize();

    $event = EventFactory::create( 'Lead', array(), 'test', true );

    $this->assertEquals(
      'http://www.pikachu.com/index.php',
      $event->getEventSourceUrl()
    );
  }

  public function testCreateWithPII() {
    $this->mockUseAAM( '1234', true, AAMSettingsFields::get_all_fields() );
    $this->mockSanitize();

    $server_event = EventFactory::create(
      'Lead',
      $this->getEventData(),
      'test_integration'
    );

    $this->assertEquals(
      'pika.chu@s2s.com',
      $server_event->getUserData()->getEmail()
    );
    $this->assertEquals( '12345', $server_event->getUserData()->getPhone() );
    $this->assertEquals( 'pika', $server_event->getUserData()->getFirstName() );
    $this->assertEquals( 'chu', $server_event->getUserData()->getLastName() );
    $this->assertEquals( 'oh', $server_event->getUserData()->getState() );
    $this->assertEquals(
      'springfield',
      $server_event->getUserData()->getCity()
    );
    $this->assertEquals(
      'us',
      $server_event->getUserData()->getCountryCode()
    );
    $this->assertEquals( '4321', $server_event->getUserData()->getZipCode() );
    $this->assertEquals( 'm', $server_event->getUserData()->getGender() );
  }

  public function testCreateWithPIIDisabled() {
    $this->mockSanitize();

    $server_event = EventFactory::create(
      'Lead',
      $this->getEventData(),
      'test_integration'
    );

    $this->assertNull( $server_event->getUserData()->getEmail() );
    $this->assertNull( $server_event->getUserData()->getFirstName() );
    $this->assertNull( $server_event->getUserData()->getLastName() );
    $this->assertNull( $server_event->getUserData()->getPhone() );
    $this->assertNull( $server_event->getUserData()->getState() );
    $this->assertNull( $server_event->getUserData()->getCity() );
    $this->assertNull( $server_event->getUserData()->getCountryCode() );
    $this->assertNull( $server_event->getUserData()->getZipCode() );
    $this->assertNull( $server_event->getUserData()->getGender() );
  }

  public function testCreateFromArraySetsScalarFields() {
    $event = EventFactory::create_from_array(
      array(
        'event_name'       => 'Purchase',
        'event_time'       => 1700000000,
        'event_id'         => 'evt.123',
        'action_source'    => 'website',
        'event_source_url' => 'https://example.com',
      )
    );

    $this->assertEquals( 'Purchase', $event->getEventName() );
    $this->assertEquals( 1700000000, $event->getEventTime() );
    $this->assertEquals( 'evt.123', $event->getEventId() );
  }

  public function testCreateFromArrayWrapsUserData() {
    $event = EventFactory::create_from_array(
      array(
        'event_name' => 'Lead',
        'event_time' => 1700000000,
        'user_data'  => array(
          'em' => array( 'abc123hash' ),
          'ph' => array( 'def456hash' ),
        ),
      )
    );

    $user_data = $event->getUserData();
    $this->assertInstanceOf(
      'FacebookPixelPlugin\FacebookAds\Object\ServerSide\UserData',
      $user_data
    );
    // Verifies map_user_data_keys translated the short AAM keys (em/ph) to the
    // UserData constructor keys (emails/phones).
    $this->assertEquals( array( 'abc123hash' ), $user_data->getEmails() );
    $this->assertEquals( array( 'def456hash' ), $user_data->getPhones() );
  }

  public function testCreateFromArrayWrapsCustomData() {
    $event = EventFactory::create_from_array(
      array(
        'event_name'  => 'Purchase',
        'event_time'  => 1700000000,
        'custom_data' => array(
          'value'    => 99.99,
          'currency' => 'USD',
        ),
      )
    );

    $custom_data = $event->getCustomData();
    $this->assertInstanceOf(
      'FacebookPixelPlugin\FacebookAds\Object\ServerSide\CustomData',
      $custom_data
    );
  }

  /**
   * Returns a sample event data array.
   *
   * @return array The sample event data array with user information.
   */
  public function getEventData() {
    return array(
        'email'      => 'pika.chu@s2s.com',
        'first_name' => 'Pika',
        'last_name'  => 'Chu',
        'phone'      => '12345',
        'state'      => 'OH',
        'city'       => 'Springfield',
        'country'    => 'US',
        'zip'        => '4321',
        'gender'     => 'M',
    );
  }

  /**
   * Mocks the use of AAM settings for tests.
   *
   * @param string $pixel_id The ID of the pixel.
   * @param bool   $enable_aam Whether AAM is enabled.
   * @param array  $enable_aam_fields The fields to enable for AAM.
   *
   * @return void
   */
  private function mockUseAAM(
    $pixel_id = '1234',
    $enable_aam = false,
    $enable_aam_fields = array()
  ) {
    $aam_settings = new AdsPixelSettings();
    $aam_settings->setPixelId( $pixel_id );
    $aam_settings->setEnableAutomaticMatching( $enable_aam );
    $aam_settings->setEnabledAutomaticMatchingFields( $enable_aam_fields );
    $this->mocked_options = \Mockery::mock(
      'alias:FacebookPixelPlugin\Core\FacebookWordpressOptions'
    );
    $this->mocked_options->shouldReceive( 'get_aam_settings' )
      ->andReturn( $aam_settings );
  }
}
