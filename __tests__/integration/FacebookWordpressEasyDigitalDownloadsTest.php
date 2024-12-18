<?php
/**
 * Facebook Pixel Plugin FacebookWordpressEasyDigitalDownloadsTest class.
 *
 * This file contains the main logic
 * for FacebookWordpressEasyDigitalDownloadsTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressEasyDigitalDownloadsTest class.
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

use FacebookPixelPlugin\Integration\FacebookWordpressEasyDigitalDownloads;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;

/**
 * FacebookWordpressEasyDigitalDownloadsTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressEasyDigitalDownloadsTest extends FacebookWordpressTestBase {

  /**
   * Tests that the inject_pixel_code method correctly sets up the
   * necessary WordPress hooks for the Facebook Pixel events in
   * the Easy Digital Downloads integration.
   *
   * This test verifies that the appropriate hooks are added for
   * the 'injectInitiateCheckoutEvent' event with the expected
   * hook name 'edd_after_checkout_cart'.
   *
   * Utilizes the Mockery library to mock the base integration class
   * and validate that the add_pixel_fire_for_hook method is called with
   * the correct parameters.
   */
  public function testInjectPixelCode() {
    $event_hook_map = array(
      'injectInitiateCheckoutEvent' => 'edd_after_checkout_cart',
    );

    $mocked_base = \Mockery::mock(
      'alias:FacebookPixelPlugin\Integration\FacebookWordpressIntegrationBase'
    );
    foreach ( $event_hook_map as $event => $hook ) {
      $mocked_base->shouldReceive( 'add_pixel_fire_for_hook' )
      ->with(
        array(
          'hook_name'       => $hook,
          'classname'       =>
                    FacebookWordpressEasyDigitalDownloads::class,
          'inject_function' => $event,
        )
      )
      ->once();
    }

    FacebookWordpressEasyDigitalDownloads::inject_pixel_code();
  }

  /**
   * Tests that the injectAddToCartEventId method injects the correct
   * hidden input field when the user is not an internal user.
   *
   * This test verifies that the hidden input field is correctly injected
   * into the HTML output when the user is not an internal user
   * and the injectAddToCartEventId method is called.
   */
  public function testInjectAddToCartEventIdWithoutInternalUser() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();

    FacebookWordpressEasyDigitalDownloads::injectAddToCartEventId();
    $this->expectOutputRegex(
      '/input type="hidden" name="facebook_event_id"/'
    );
  }

  /**
   * Tests that the injectInitiateCheckoutEvent method correctly injects
   * the Pixel code for the 'InitiateCheckout' event when the user is
   * not an internal user.
   *
   * This test verifies that the output contains the expected Meta Pixel
   * Event Code for Easy Digital Downloads and that the server-side event
   * tracking records the 'InitiateCheckout' event with the correct user
   * and custom data attributes.
   */
  public function testInitiateCheckoutEventWithoutInternalUser() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();

    $this->setupEDDMocks();

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

    FacebookWordpressEasyDigitalDownloads::injectInitiateCheckoutEvent();

    $this->expectOutputRegex(
      '/easy-digital-downloads[\s\S]+End Meta Pixel Event Code/'
    );

    $tracked_events =
    FacebookServerSideEvent::get_instance()->get_tracked_events();

    $this->assertCount( 1, $tracked_events );

    $event = $tracked_events[0];
    $this->assertEquals( 'InitiateCheckout', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
    $this->assertEquals(
            'pika.chu@s2s.com',
            $event->getUserData()->getEmail()
        );
    $this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
    $this->assertEquals( 'chu', $event->getUserData()->getLastName() );
    $this->assertEquals( 'USD', $event->getCustomData()->getCurrency() );
    $this->assertEquals( '300', $event->getCustomData()->getValue() );
    $this->assertEquals(
      'easy-digital-downloads',
      $event->getCustomData()->getCustomProperty(
                'fb_integration_tracking'
            )
    );
  }

  /**
   * Tests that the trackPurchaseEvent method correctly injects the Pixel code
   * for the 'Purchase' event when the user is not an internal user.
   *
   * This test verifies that the server-side event tracking records the
   * 'Purchase' event with the correct user and custom data attributes.
   */
  public function testPurchaseEventWithoutInternalUser() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();

    $this->setupEDDMocks();

    $payment = new class() {
      /**
       * Unique ID
       *
       * @var integer
       */
      public $ID = 1;
    };

    \WP_Mock::expectActionAdded(
      'wp_footer',
      array(
        'FacebookPixelPlugin\\Integration\\FacebookWordpressEasyDigitalDownloads',
        'injectPurchaseEvent',
      ),
      20
    );

    FacebookWordpressEasyDigitalDownloads::trackPurchaseEvent(
            $payment,
            null
        );

    $tracked_events =
    FacebookServerSideEvent::get_instance()->get_tracked_events();

    $this->assertCount( 1, $tracked_events );

    $event = $tracked_events[0];
    $this->assertEquals( 'Purchase', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
    $this->assertEquals(
            'pika.chu@s2s.com',
            $event->getUserData()->getEmail()
        );
    $this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
    $this->assertEquals( 'chu', $event->getUserData()->getLastName() );
    $this->assertEquals( 'USD', $event->getCustomData()->getCurrency() );
    $this->assertEquals( 700, $event->getCustomData()->getValue() );
    $this->assertEquals(
            'product',
            $event->getCustomData()->getContentType()
        );
    $this->assertEquals(
            array( 99, 999 ),
            $event->getCustomData()->getContentIds()
        );
    $this->assertEquals(
      'easy-digital-downloads',
      $event->getCustomData()->getCustomProperty(
                'fb_integration_tracking'
            )
    );
  }

  /**
   * Tests that the injectAddToCartListener method does not inject
   * any Pixel code when the user is an internal user.
   *
   * This test verifies that the output does not contain any Meta Pixel
   * Event Code when the injectAddToCartListener method is called by an
   * internal user.
   */
  public function testInjectAddToCartEventListenerWithInternalUser() {
    self::mockIsInternalUser( true );

    $download_id = '1234';
    FacebookWordpressEasyDigitalDownloads::injectAddToCartListener(
      $download_id
    );
    $this->expectOutputString( '' );
  }

  /**
   * Tests that the injectAddToCartEventId method does not inject
   * any Pixel code when the user is an internal user.
   *
   * This test verifies that the output does not contain any Meta Pixel
   * Event Code when the injectAddToCartEventId method is called by an
   * internal user.
   */
  public function testInjectAddToCartEventIdWithInternalUser() {
    self::mockIsInternalUser( true );

    FacebookWordpressEasyDigitalDownloads::injectAddToCartEventId();
    $this->expectOutputString( '' );
  }

  /**
   * Tests that the injectInitiateCheckoutEvent method does not inject
   * any Pixel code when the user is an internal user.
   *
   * This test verifies that the output does not contain any Meta Pixel
   * Event Code when the injectInitiateCheckoutEvent method is called by an
   * internal user.
   */
  public function testInjectInitiateCheckoutEventWithInternalUser() {
    self::mockIsInternalUser( true );

    FacebookWordpressEasyDigitalDownloads::injectInitiateCheckoutEvent();
    $this->expectOutputString( '' );
  }

  /**
   * Tests that the trackPurchaseEvent method does not inject any Pixel code
   * when the user is an internal user.
   *
   * This test verifies that the output does not contain any Meta Pixel
   * Event Code when the trackPurchaseEvent method is called by an
   * internal user.
   */
  public function testInjectPurchaseEventWithInternalUser() {
    self::mockIsInternalUser( true );
    $payment = array( 'ID' => '1234' );
    FacebookWordpressEasyDigitalDownloads::trackPurchaseEvent(
            $payment,
            null
        );
    $this->expectOutputString( '' );
  }

  /**
   * Tests that the injectViewContentEvent method does not inject
   * any Pixel code when the user is an internal user.
   *
   * This test verifies that the output does not contain any Meta Pixel
   * Event Code when the injectViewContentEvent method is called by an
   * internal user.
   */
  public function testInjectViewContentEventWithInternalUser() {
    self::mockIsInternalUser( true );

    $download_id = 1234;
    FacebookWordpressEasyDigitalDownloads::injectViewContentEvent(
            $download_id
        );
    $this->expectOutputString( '' );
  }

  /**
   * Tests that the injectViewContentEvent method
     * correctly injects the Pixel code
   * for the 'ViewContent' event when the user is not an internal user.
   *
   * This test verifies that the output contains the expected Meta Pixel
   * Event Code for Easy Digital Downloads and that the server-side event
   * tracking records the 'ViewContent' event with the correct user and
   * custom data attributes, such as content IDs, content type, currency,
   * content name, and value.
   */
  public function testInjectViewContentEventWithoutInternalUser() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();

    $this->setupEDDMocks();

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

    FacebookWordpressEasyDigitalDownloads::injectViewContentEvent( 1234 );
    $this->expectOutputRegex(
      '/easy-digital-downloads[\s\S]+End Meta Pixel Event Code/'
    );
    $tracked_events =
    FacebookServerSideEvent::get_instance()->get_tracked_events();

    $this->assertCount( 1, $tracked_events );

    $event       = $tracked_events[0];
    $custom_data = $event->getCustomData();
    $user_data   = $event->getUserData();

    $this->assertEquals( 'pika.chu@s2s.com', $user_data->getEmail() );
    $this->assertEquals( 'pika', $user_data->getFirstName() );
    $this->assertEquals( 'chu', $user_data->getLastName() );
    $this->assertEquals( 'ViewContent', $event->getEventName() );
    $this->assertEquals( array( '1234' ), $custom_data->getContentIds() );
    $this->assertEquals( 'product', $custom_data->getContentType() );
    $this->assertEquals( 'USD', $custom_data->getCurrency() );
    $this->assertEquals( 'Encarta', $custom_data->getContentName() );
    $this->assertEquals( 50, $custom_data->getValue() );
    $this->assertNotNull( $event->getEventTime() );
  }

  /**
   * Tests that the injectAddToCartEventAjax method correctly injects
   * the Pixel code for the 'AddToCart' event when the user is not an
   * internal user.
   *
   * This test verifies that the output contains the expected Meta Pixel
   * Event Code for Easy Digital Downloads and that the server-side event
   * tracking records the 'AddToCart' event with the correct user and
   * custom data attributes, such as content IDs, content type, currency,
   * content name, and value.
   */
  public function testInjectAddToCartEventAjax() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();

    $this->setupEDDMocks();

        \WP_Mock::userFunction(
            'wp_unslash',
            array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
            )
        );

    FacebookWordpressEasyDigitalDownloads::injectAddToCartEventAjax();

    $tracked_events =
    FacebookServerSideEvent::get_instance()->get_tracked_events();

    $this->assertCount( 2, $tracked_events );

    $event       = $tracked_events[0];
    $custom_data = $event->getCustomData();
    $user_data   = $event->getUserData();

    $this->assertEquals( 'abc-123', $event->getEventId() );
    $this->assertEquals( 'pika.chu@s2s.com', $user_data->getEmail() );
    $this->assertEquals( 'pika', $user_data->getFirstName() );
    $this->assertEquals( 'chu', $user_data->getLastName() );
    $this->assertEquals( 'AddToCart', $event->getEventName() );
    $this->assertEquals( array( '1234' ), $custom_data->getContentIds() );
    $this->assertEquals( 'product', $custom_data->getContentType() );
    $this->assertEquals( 'USD', $custom_data->getCurrency() );
    $this->assertEquals( 'Encarta', $custom_data->getContentName() );
    $this->assertEquals( 50, $custom_data->getValue() );
    $this->assertNotNull( $event->getEventTime() );
  }

  /**
   * Sets up the necessary mocks for Easy Digital Downloads integration tests.
   *
   * This method mocks various functions and methods
     * related to Easy Digital Downloads
   * to simulate the environment for testing purposes.
     * It configures the expected
   * return values for functions like currency retrieval,
     * cart total calculation,
   * payment metadata, and download object information. It also sets up mock
   * POST data and ensures nonce verification functions behave as expected.
   */
  private function setupEDDMocks() {
    \WP_Mock::userFunction( 'EDD' );
    $mock_edd_utils = \Mockery::mock(
      'alias:FacebookPixelPlugin\Integration\EDDUtils'
    );
    $mock_edd_utils->shouldReceive( 'get_currency' )->andReturn( 'USD' );
    $mock_edd_utils->shouldReceive( 'get_cart_total' )->andReturn( 300 );

    $this->mocked_fbpixel->shouldReceive( 'get_logged_in_user_info' )
    ->andReturn(
      array(
        'email'      => 'pika.chu@s2s.com',
        'first_name' => 'Pika',
        'last_name'  => 'Chu',
      )
    );

    \WP_Mock::userFunction(
      'edd_get_payment_meta',
      array(
        'args'   => 1,
        'return' => array(
          'email'        => 'pika.chu@s2s.com',
          'user_info'    => array(
            'first_name' => 'Pika',
            'last_name'  => 'Chu',
          ),
          'cart_details' => array(
            array(
              'id'    => 99,
              'price' => 300,
            ),
            array(
              'id'    => 999,
              'price' => 400,
            ),
          ),
          'currency'     => 'USD',
        ),
      )
    );

    \WP_Mock::userFunction(
      'get_post_meta',
      array(
        'args'   => array(
          1234,
          \WP_Mock\Functions::type( 'string' ),
          true,
        ),
        'return' => array(
          array(
            'amount' => 50,
          ),
        ),
      )
    );
    $download_object             = new \stdClass();
    $download_object->post_title = 'Encarta';
    \WP_Mock::userFunction(
      'edd_get_download',
      array(
        'args'   => array( \WP_Mock\Functions::type( 'int' ) ),
        'return' => $download_object,
      )
    );

    $_POST['nonce']       = '54321';
    $_POST['download_id'] = '1234';
    $_POST['post_data']   = 'facebook_event_id=abc-123';

    \WP_Mock::userFunction(
      'absint',
      array(
        'args'   => array( \WP_Mock\Functions::type( 'string' ) ),
        'return' => 1234,
      )
    );

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \WP_Mock\Functions::type( 'string' ) ),
        'return' => '54321',
      )
    );

    \WP_Mock::userFunction(
      'wp_verify_nonce',
      array(
        'args'   => array(
          \WP_Mock\Functions::type( 'string' ),
          \WP_Mock\Functions::type( 'string' ),
        ),
        'return' => true,
      )
    );
  }
}
