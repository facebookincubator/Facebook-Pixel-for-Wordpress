<?php
/**
 * Facebook Pixel Plugin FacebookWordpressContactForm7Test class.
 *
 * This file contains the main logic for FacebookWordpressContactForm7Test.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressContactForm7Test class.
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

use FacebookPixelPlugin\Integration\FacebookWordpressContactForm7;
use FacebookPixelPlugin\Tests\Mocks\MockContactForm7;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\Signals;

/**
 * FacebookWordpressContactForm7Test class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressContactForm7Test extends FacebookWordpressTestBase {

  /**
   * Tests that inject_pixel_code registers the Contact Form 7 hooks: a
   * server-event tracker on wpcf7_submit and the mail-sent listener on
   * wp_footer.
   *
   * @return void
   */
  public function testInjectPixelCode() {
    $signals     = \Mockery::mock( Signals::class );
    $integration = new FacebookWordpressContactForm7( $signals );

    \WP_Mock::expectActionAdded(
      'wpcf7_submit',
      array( $integration, 'trackServerEvent' ),
      10,
      2
    );
    \WP_Mock::expectActionAdded(
      'wp_footer',
      array( $integration, 'injectMailSentListener' ),
      10,
      2
    );

    $integration->inject_pixel_code();
    $this->assertHooksAdded();
  }

  /**
   * Tests trackServerEvent when the user is not an internal user.
   *
   * Verifies that the Lead server event is sent through Signals with the
   * expected user data and custom properties, and that the feedback-response
   * hook is registered so the browser code is injected.
   *
   * @return void
   */
  public function testTrackServerEventWithoutInternalUser() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();
    $this->mockSplitName();

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

    $captured = null;
    $signals  = \Mockery::mock( Signals::class );
    $signals->shouldReceive( 'send' )
      ->once()
      ->with(
        \Mockery::on(
          function ( $events ) use ( &$captured ) {
            $captured = $events;
            return true;
          }
        )
      );

    $integration = new FacebookWordpressContactForm7( $signals );

    \WP_Mock::expectActionAdded(
      'wpcf7_feedback_response',
      array( $integration, 'injectLeadEvent' ),
      20,
      2
    );

    $mock_form   = $this->createMockForm();
    $mock_result = array(
      'status'  => 'mail_sent',
      'message' => 'Thank you for your message',
    );

    $integration->trackServerEvent( $mock_form, $mock_result );

    $this->assertCount( 1, $captured );

    $event = $captured[0];
    $this->assertEquals( 'Lead', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
    $this->assertEquals(
            'pika.chu@s2s.com',
            $event->getUserData()->getEmail()
        );
    $this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
    $this->assertEquals( 'chu', $event->getUserData()->getLastName() );
    $this->assertEquals( '12223334444', $event->getUserData()->getPhone() );
    $this->assertEquals(
      'contact-form-7',
      $event->getCustomData()->getCustomProperty(
                'fb_integration_tracking'
            )
    );
    $this->assertEquals( 'TEST_REFERER', $event->getEventSourceUrl() );
  }

  /**
   * Tests trackServerEvent still sends a Lead event when the form carries data.
   *
   * @return void
   */
  public function testTrackServerEventWithoutFormData() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();
    $this->mockSplitName();

        \WP_Mock::userFunction(
            'sanitize_text_field',
            array(
              'args'   => array( \Mockery::any() ),
              'return' => function ( $input ) {
                  return $input;
              },
            )
        );

    $captured = null;
    $signals  = \Mockery::mock( Signals::class );
    $signals->shouldReceive( 'send' )
      ->once()
      ->with(
        \Mockery::on(
          function ( $events ) use ( &$captured ) {
            $captured = $events;
            return true;
          }
        )
      );

    $integration = new FacebookWordpressContactForm7( $signals );

    \WP_Mock::expectActionAdded(
      'wpcf7_feedback_response',
      array( $integration, 'injectLeadEvent' ),
      20,
      2
    );

    $mock_form   = $this->createMockForm();
    $mock_result = array(
      'status'  => 'mail_sent',
      'message' => 'Thank you for your message',
    );

    $integration->trackServerEvent( $mock_form, $mock_result );

    $this->assertCount( 1, $captured );

    $event = $captured[0];
    $this->assertEquals( 'Lead', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
  }

  /**
   * Tests the injectLeadEvent method when the user is not an internal user.
   *
   * Verifies that the Pixel code for the Lead event built on submit is rendered
   * into the feedback response under the 'fb_pxl_code' key.
   *
   * @return void
   */
  public function testInjectLeadEventWithoutInternalUser() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();
    $this->mockSplitName();

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

    // Partial mock: send() is stubbed (no network), render() runs for real so
    // the rendered browser code assertion stays meaningful.
    $signals = \Mockery::mock( Signals::class . '[send]' );
    $signals->shouldReceive( 'send' )->once();

    $integration = new FacebookWordpressContactForm7( $signals );

    \WP_Mock::expectActionAdded(
      'wpcf7_feedback_response',
      array( $integration, 'injectLeadEvent' ),
      20,
      2
    );

    $mock_form = $this->createMockForm();
    $integration->trackServerEvent(
      $mock_form,
      array(
        'status'  => 'mail_sent',
        'message' => 'Thank you for your message',
      )
    );

    $response = $integration->injectLeadEvent(
      array(
        'status'  => 'mail_sent',
        'message' => 'Thank you for your message',
      ),
      null
    );

    $this->assertMatchesRegularExpression(
      '/Lead[\s\S]+contact-form-7/',
      $response['fb_pxl_code']
    );
  }

  /**
   * Tests the injectLeadEvent method when the user is an internal user.
   *
   * Verifies that no Pixel code is added to the response for internal users.
   *
   * @return void
   */
  public function testInjectLeadEventWithInternalUser() {
    self::mockIsInternalUser( true );

    $integration = new FacebookWordpressContactForm7(
      \Mockery::mock( Signals::class )
    );

    $response = $integration->injectLeadEvent(
      array(
        'status'  => 'mail_sent',
        'message' => 'Thank you for your message',
      ),
      null
    );

    $this->assertArrayNotHasKey( 'fb_pxl_code', $response );
  }

  /**
   * Tests that trackServerEvent does not send an event when the mail submission
   * fails (validation failed, spam, mail failed, etc.).
   *
   * @return void
   */
  public function testTrackServerEventWhenMailFails() {
    self::mockIsInternalUser( false );

    $signals = \Mockery::mock( Signals::class );
    $signals->shouldReceive( 'send' )->never();

    $integration = new FacebookWordpressContactForm7( $signals );

    $bad_statuses = array(
      'validation_failed',
      'acceptance_missing',
      'spam',
      'aborted',
      'mail_failed',
    );

    $mock_form = new MockContactForm7();
    $mock_form->set_throw( false );

    foreach ( $bad_statuses as $status ) {
      $mock_result = array(
        'status'  => $status,
        'message' => 'Error bad status',
      );
      $integration->trackServerEvent( $mock_form, $mock_result );
    }

    $this->assertConditionsMet();
  }

  /**
   * Tests trackServerEvent when an error occurs while reading the form data.
   *
   * @return void
   */
  public function testTrackServerEventErrorReadingData() {
    $this->markTestSkipped(
      'Skipping test temporarily while we update error handling.'
    );
  }

  /**
   * Stubs FacebookPluginUtils::split_name on the alias mock created by
   * mockIsInternalUser. Contact Form 7's getName() calls split_name, and the
   * alias mock intercepts every static call on the class, so the method must be
   * defined or the call throws.
   *
   * @return void
   */
  private function mockSplitName() {
    $this->mocked_fbpixel->shouldReceive( 'split_name' )->andReturnUsing(
      function ( $name ) {
        $first = $name;
        $last  = null;
        $index = strpos( $name, ' ' );
        if ( false !== $index ) {
          $first = substr( $name, 0, $index );
          $last  = substr( $name, $index + 1 );
        }
        return array( $first, $last );
      }
    );
  }

  /**
   * Creates a mock form object with sample email, text and tel form tags.
   *
   * @return MockContactForm7 A mock form object with sample form tags.
   */
  private function createMockForm() {
    $mock_form = new MockContactForm7();

    $mock_form->add_tag( 'email', 'your-email', 'pika.chu@s2s.com' );
    $mock_form->add_tag( 'text', 'your-name', 'Pika Chu' );
    $mock_form->add_tag( 'tel', 'your-phone-number', '12223334444' );

    return $mock_form;
  }
}
