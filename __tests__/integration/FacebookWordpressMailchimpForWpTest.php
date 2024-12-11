<?php
/**
 * Facebook Pixel Plugin FacebookWordpressMailchimpForWpTest class.
 *
 * This file contains the main logic for FacebookWordpressMailchimpForWpTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressMailchimpForWpTest class.
 *
 * @return void
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

use FacebookPixelPlugin\Integration\FacebookWordpressMailchimpForWp;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;

/**
 * FacebookWordpressMailchimpForWpTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressMailchimpForWpTest extends FacebookWordpressTestBase {
  /**
   * Tests that the inject_pixel_code method correctly sets up the
   * necessary WordPress hooks for the Facebook Pixel events in
   * the MailChimp for WP integration.
   *
   * This test verifies that the add_pixel_fire_for_hook method is called
   * with the correct parameters, ensuring that the 'mc4wp_form_subscribed'
   * hook is added to trigger the 'injectLeadEvent' method.
   */
  public function testInjectPixelCode() {
    $mocked_base = \Mockery::mock(
      'alias:FacebookPixelPlugin\Integration\FacebookWordpressIntegrationBase'
    );
    $mocked_base->shouldReceive( 'add_pixel_fire_for_hook' )
    ->with(
      array(
        'hook_name'       => 'mc4wp_form_subscribed',
        'classname'       => FacebookWordpressMailchimpForWp::class,
        'inject_function' => 'injectLeadEvent',
      )
    )
    ->once();
    FacebookWordpressMailchimpForWp::inject_pixel_code();
  }

  /**
   * Tests the injectLeadEvent method when the user is not an internal user.
   *
   * This test verifies that the Pixel code is correctly
     * appended to the HTML output
   * and that the server-side event is tracked with the correct parameters.
   * It ensures that the 'Lead' event is recorded with the expected user data
   * and custom properties when the MailChimp for WP integration is triggered.
   *
   * @return void
   */
  public function testInjectLeadEventWithoutInternalUser() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();

    $_POST['EMAIL']          = 'pika.chu@s2s.com';
    $_POST['FNAME']          = 'Pika';
    $_POST['LNAME']          = 'Chu';
    $_POST['PHONE']          = '123456';
    $_POST['ADDRESS']        = array(
      'city'    => 'Springfield',
      'state'   => 'Ohio',
      'zip'     => '54321',
      'country' => 'US',
    );
    $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

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
            'sanitize_email',
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

        \WP_Mock::userFunction(
            'wp_json_encode',
            array(
        'args'   => array(
                    \Mockery::type( 'array' ),
          \Mockery::type( 'int' ),
        ),
        'return' => function ( $data, $options ) {
          return json_encode( $data );
        },
            )
        );

    FacebookWordpressMailchimpForWp::injectLeadEvent();
    $this->expectOutputRegex(
      '/mailchimp-for-wp[\s\S]+End Meta Pixel Event Code/'
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
    $this->assertEquals( '54321', $event->getUserData()->getZipCode() );
    $this->assertEquals( 'us', $event->getUserData()->getCountryCode() );
    $this->assertEquals(
      'mailchimp-for-wp',
      $event->getCustomData()
            ->getCustomProperty( 'fb_integration_tracking' )
    );
    $this->assertEquals( 'TEST_REFERER', $event->getEventSourceUrl() );
  }

  /**
   * Tests the injectLeadEvent method when the user is an internal user.
   *
   * This test verifies that no Pixel code is appended to the HTML output
   * when the user is an internal user. It
     * asserts that the output HTML remains
   * unchanged and that no events are tracked.
   *
   * @return void
   */
  public function testInjectLeadEventWithInternalUser() {
    self::mockIsInternalUser( true );
    FacebookWordpressMailchimpForWp::injectLeadEvent();
    $this->expectOutputString( '' );
  }
}
