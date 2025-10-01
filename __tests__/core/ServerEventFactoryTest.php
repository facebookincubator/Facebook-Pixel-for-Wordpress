<?php
/**
 * Facebook Pixel Plugin ServerEventFactoryTest class.
 *
 * This file contains the main logic for ServerEventFactoryTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define ServerEventFactoryTest class.
 *
 * @return void
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
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

use FacebookPixelPlugin\FacebookAdsObject\ServerSide\AdsPixelSettings;

/**
 * ServerEventFactoryTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class ServerEventFactoryTest extends FacebookWordpressTestBase {
  /**
   * Tests that a new event has a valid event ID.
   *
   * This test verifies that the event ID generated for a new event
   * is not null and has a length of 36 characters, which corresponds
   * to the expected format of a UUID.
   *
   * @return void
   */
  public function testNewEventHasEventId() {
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'Lead' );

    $this->assertNotNull( $event->getEventId() );
    $this->assertEquals( 36, strlen( $event->getEventId() ) );
  }

  /**
   * Tests that a new event has a valid event time.
   *
   * This test verifies that the event time generated for a new event
   * is not null and is a valid Unix timestamp, i.e. it is less than
   * the current time.
   *
   * @return void
   */
  public function testNewEventHasEventTime() {
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'Lead' );

    $this->assertNotNull( $event->getEventTime() );
    $this->assertLessThan( 1, time() - $event->getEventTime() );
  }

  /**
   * Tests that a new event has the correct event name.
   *
   * This test verifies that the event name generated for a new event
   * matches the event name passed to the new_event method.
   *
   * @return void
   */
  public function testNewEventHasEventName() {
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'Lead' );

    $this->assertEquals( 'Lead', $event->getEventName() );
  }

  /**
   * Tests that a new event has an action source.
   *
   * This test verifies that the action source generated for a new event
   * is 'website'.
   *
   * @return void
   */
  public function testNewEventHasActionSource() {
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'ViewContent' );
    $this->assertEquals( 'website', $event->getActionSource() );
  }

  /**
   * Tests that a new event can be created with an IPv4 address.
   *
   * This test verifies that when an IPv4 address is passed in the
   * X-Forwarded-For header, a new event can be created with the correct
   * client IP address.
   *
   * @return void
   */
  public function testNewEventWorksWithIpV4() {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '24.17.77.101';

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'Lead' );
    $this->assertEquals(
      '24.17.77.101',
      $event->getUserData()->getClientIpAddress()
    );
  }

  /**
   * Tests that a new event can be created with an IPv6 address.
   *
   * This test verifies that when an IPv6 address is passed in the
   * X-Forwarded-For header, a new event can be created with the correct
   * client IP address.
   *
   * @return void
   */
  public function testNewEventWorksWithIpV6() {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '2120:10a:c191:401::5:7170';

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'Lead' );
    $this->assertEquals(
      '2120:10a:c191:401::5:7170',
      $event->getUserData()->getClientIpAddress()
    );
  }

  /**
   * Tests that a new event takes the first IP address from a list.
   *
   * This test verifies that when multiple IP addresses are provided
   * in the X-Forwarded-For header, the first IP address is correctly
   * used as the client IP address for the new event.
   *
   * @return void
   */
  public function testNewEventTakesFirstWithIpAddressList() {
    $_SERVER['HTTP_X_FORWARDED_FOR']
      = '2120:10a:c191:401::5:7170, 24.17.77.101';

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'Lead' );
    $this->assertEquals(
      '2120:10a:c191:401::5:7170',
      $event->getUserData()->getClientIpAddress()
    );
  }

  /**
   * Tests that a new event honors the precedence
   * order for determining the client's IP address.
   *
   * This test verifies that when both the
   * HTTP_X_FORWARDED_FOR and REMOTE_ADDR
   * headers are present, the HTTP_X_FORWARDED_FOR header is used to determine
   * the client's IP address for the new event.
   *
   * @return void
   */
  public function testNewEventHonorsPrecedenceForIpAddress() {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '24.17.77.101';
    $_SERVER['REMOTE_ADDR']          = '24.17.77.100';

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'Lead' );
    $this->assertEquals(
      '24.17.77.101',
      $event->getUserData()->getClientIpAddress()
    );
  }

  /**
   * Tests that a new event handles an invalid IP address correctly.
   *
   * This test verifies that when an invalid IP address is provided
   * in the HTTP_X_FORWARDED_FOR header, the client's IP address for
   * the new event is null, indicating that the invalid IP address was
   * not used.
   *
   * @return void
   */
  public function testNewEventWithInvalidIpAddress() {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = 'INVALID';

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'Lead' );
    $this->assertNull( $event->getUserData()->getClientIpAddress() );
  }

  /**
   * Tests that a new event has the correct user agent.
   *
   * This test verifies that the user agent generated for a new event
   * matches the user agent value set in the HTTP_USER_AGENT server variable.
   *
   * @return void
   */
  public function testNewEventHasUserAgent() {
    $_SERVER['HTTP_USER_AGENT'] = 'HTTP_USER_AGENT_VALUE';

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'Lead' );

    $this->assertEquals(
      'HTTP_USER_AGENT_VALUE',
      $event->getUserData()->getClientUserAgent()
    );
  }

  /**
   * Tests that a new event has the correct event source URL with HTTPS.
   *
   * This test verifies that when the HTTPS server variable is set,
   * the event source URL generated for a
   * new event includes the 'https' scheme.
   *
   * @return void
   */
  public function testNewEventHasEventSourceUrlWithHttps() {
    $_SERVER['HTTPS']       = 'anyvalue';
    $_SERVER['HTTP_HOST']   = 'www.pikachu.com';
    $_SERVER['REQUEST_URI'] = '/index.php';

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'Lead' );

    $this->assertEquals(
      'https://www.pikachu.com/index.php',
      $event->getEventSourceUrl()
    );
  }

  /**
   * Tests that a new event has the correct event source URL with HTTP.
   *
   * This test verifies that when the HTTPS server variable is not set,
   * the event source URL generated for a
   * new event includes the 'http' scheme.
   *
   * @return void
   */
  public function testNewEventHasEventSourceUrlWithHttp() {
    $_SERVER['HTTPS']       = '';
    $_SERVER['HTTP_HOST']   = 'www.pikachu.com';
    $_SERVER['REQUEST_URI'] = '/index.php';

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'Lead' );

    $this->assertEquals(
      'http://www.pikachu.com/index.php',
      $event->getEventSourceUrl()
    );
  }

  /**
   * Tests that a new event has the correct event
   * source URL with HTTP when HTTPS is set to 'off'.
   *
   * This test verifies that when the HTTPS server
   * variable is explicitly set to 'off',
   * the event source URL generated for a new event
   * includes the 'http' scheme.
   *
   * @return void
   */
  public function testNewEventHasEventSourceUrlWithHttpsOff() {
    $_SERVER['HTTPS']       = 'off';
    $_SERVER['HTTP_HOST']   = 'www.pikachu.com';
    $_SERVER['REQUEST_URI'] = '/index.php';

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'Lead' );

    $this->assertEquals(
      'http://www.pikachu.com/index.php',
      $event->getEventSourceUrl()
    );
  }

  /**
   * Tests that a new event has the correct event
   * source URL when the prefer_referer_for_event_src flag is set to true.
   *
   * This test verifies that when the HTTP_REFERER server variable is set
   * and the prefer_referer_for_event_src flag is set to true,
   * the event source URL generated for a new event is the value in the
   * HTTP_REFERER server variable.
   *
   * @return void
   */
  public function testNewEventEventSourceUrlPreferReferer() {
    $_SERVER['HTTPS']        = 'off';
    $_SERVER['HTTP_HOST']    = 'www.pikachu.com';
    $_SERVER['REQUEST_URI']  = '/index.php';
    $_SERVER['HTTP_REFERER'] = 'http://referrer/';

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'Lead', true );

    $this->assertEquals( 'http://referrer/', $event->getEventSourceUrl() );
  }

  /**
   * Tests that a new event has the correct event
   * source URL when the prefer_referer_for_event_src flag is set to true,
   * but the HTTP_REFERER server variable is not set.
   *
   * This test verifies that when the HTTP_REFERER server variable is not set
   * and the prefer_referer_for_event_src flag is set to true,
   * the event source URL generated for a new event is the value in the
   * HTTP_HOST and REQUEST_URI server variables.
   *
   * @return void
   */
  public function testNewEventEventSourceUrlWithoutReferer() {
    $_SERVER['HTTPS']       = 'off';
    $_SERVER['HTTP_HOST']   = 'www.pikachu.com';
    $_SERVER['REQUEST_URI'] = '/index.php';

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'Lead', true );

    $this->assertEquals(
      'http://www.pikachu.com/index.php',
      $event->getEventSourceUrl()
    );
  }

  /**
   * Tests that the fbclid is extracted from the
   * URL if the FBC cookie is not found.
   *
   * This test verifies that when the FBC cookie is
   * not set, the fbclid parameter
   * from the URL is used to set the FBC value in the event.
   *
   * @return void
   */
  public function testFBClidExtractedFromUrlIfFbcNotFound() {
    $_GET['fbclid'] = 'fbclid_str';

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    \WP_Mock::userFunction(
      'wp_unslash',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'Lead' );

    $event_fbc = $event->getUserData()->getFbc();

    $this->assertEquals( true, str_starts_with( $event_fbc, 'fb.1.' ) );
    $this->assertEquals( true, str_ends_with( $event_fbc, '.fbclid_str' ) );
  }

  /**
   * Tests that a new event correctly uses the FBC value from the cookie.
   *
   * This test verifies that when the FBC cookie is set, the FBC value
   * is correctly retrieved and assigned to the event's user data.
   *
   * @return void
   */
  public function testNewEventHasFbc() {
    $_COOKIE['_fbc'] = '_fbc_value';

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    \WP_Mock::userFunction(
      'wp_unslash',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $event = ServerEventFactory::new_event( 'Lead' );

    $this->assertEquals( '_fbc_value', $event->getUserData()->getFbc() );
  }

  /**
   * Tests that a new event correctly uses the FBP value from the cookie.
   *
   * This test verifies that when the FBP cookie is set, the FBP value
   * is correctly retrieved and assigned to the event's user data.
   *
   * @return void
   */
  public function testNewEventHasFbp() {
    $_COOKIE['_fbp'] = '_fbp_value';

    \WP_Mock::userFunction(
      'wp_unslash',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
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

    $event = ServerEventFactory::new_event( 'Lead' );

    $this->assertEquals( '_fbp_value', $event->getUserData()->getFbp() );
  }

  /**
   * Tests the safe_create_event method with
   * Personally Identifiable Information (PII).
   *
   * This test verifies that when PII extraction is enabled and all AAM fields
   * are available, the safe_create_event
   * method correctly creates a server event
   * with the expected user data attributes,
   * including email, phone, first name,
   * last name, state, city, country code, zip code, and gender.
   *
   * @return void
   */
  public function testSafeCreateEventWithPII() {
    $this->mockUseAAM( '1234', true, AAMSettingsFields::get_all_fields() );

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $server_event = ServerEventFactory::safe_create_event(
      'Lead',
      array( $this, 'getEventData' ),
      array(),
      'test_integration'
    );
    $this->assertEquals(
      'pika.chu@s2s.com',
      $server_event->getUserData()->getEmail()
    );
    $this->assertEquals(
      '12345',
      $server_event->getUserData()->getPhone()
    );
    $this->assertEquals(
      'pika',
      $server_event->getUserData()->getFirstName()
    );
    $this->assertEquals(
      'chu',
      $server_event->getUserData()->getLastName()
    );
    $this->assertEquals(
      'oh',
      $server_event->getUserData()->getState()
    );
    $this->assertEquals(
      'springfield',
      $server_event->getUserData()->getCity()
    );
    $this->assertEquals(
      'us',
      $server_event->getUserData()->getCountryCode()
    );
    $this->assertEquals(
      '4321',
      $server_event->getUserData()->getZipCode()
    );
    $this->assertEquals(
      'm',
      $server_event->getUserData()->getGender()
    );
  }

  /**
   * Tests the safe_create_event method with
   * Personally Identifiable Information (PII) extraction disabled.
   *
   * This test verifies that when PII extraction is
   * disabled, the safe_create_event method correctly creates a server event
   * with all user data attributes set to null.
   *
   * @return void
   */
  public function testSafeCreateEventWithPIIDisabled() {
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $server_event = ServerEventFactory::safe_create_event(
      'Lead',
      array( $this, 'getEventData' ),
      array(),
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

  /**
   * Returns a sample event data array.
   *
   * This method returns an associative array
   * containing sample user data fields
   * such as email, first name, last name, phone,
   * state, city, country, zip code,
   * and gender. These fields are used for testing purposes.
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
   * This method sets up a mock of the AdsPixelSettings
   * class and the FacebookWordpressOptions class.
   * The AdsPixelSettings class is configured with the
   * given pixel ID, whether AAM is enabled,
   * and which fields are enabled for AAM.
   *
   * The method then sets up the FacebookWordpressOptions
   * class to return the configured AdsPixelSettings
   * instance when the get_aam_settings method is called.
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
