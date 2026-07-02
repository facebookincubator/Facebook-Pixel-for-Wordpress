<?php
/**
 * Facebook Pixel Plugin FacebookWordpressEasyDigitalDownloadsTest class.
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
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Integration\FacebookWordpressEasyDigitalDownloads;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressEasyDigitalDownloadsTest class.
 *
 * These tests assert the same observable behavior as before the Signals
 * refactor: driving an event through the integration tracks a server-side
 * event with the correct user/custom data (and the shared event id for the
 * AJAX add-to-cart). The only change is the entry point — the data provider
 * plus the real Signals dispatch path.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressEasyDigitalDownloadsTest extends FacebookWordpressTestBase {

  /**
   * Builds an EDD integration wired to a real Signals dispatcher.
   *
   * @return array [ FacebookWordpressEasyDigitalDownloads, Signals ]
   */
  private function makeIntegration() {
    $signals     = new Signals();
    $integration = new FacebookWordpressEasyDigitalDownloads(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * Mocks the current user, plugin options, and the JSON/sanitize helpers the
   * dispatch/render path relies on. Adds the logged-in user PII used by the
   * cart/product event builders.
   *
   * @return void
   */
  private function setupUserAndOptions() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();

    $this->mocked_fbpixel->shouldReceive( 'get_logged_in_user_info' )
      ->andReturn(
        array(
          'email'      => 'pika.chu@s2s.com',
          'first_name' => 'Pika',
          'last_name'  => 'Chu',
        )
      );

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
   * Alias-mocks EDDUtils currency + cart-total helpers.
   *
   * @return void
   */
  private function setupEddUtils() {
    $edd_utils = \Mockery::mock(
      'alias:FacebookPixelPlugin\Integration\EDDUtils'
    );
    $edd_utils->shouldReceive( 'get_currency' )->andReturn( 'USD' );
    $edd_utils->shouldReceive( 'get_cart_total' )->andReturn( 300 );
  }

  /**
   * Mocks the download product lookups used by the AddToCart/ViewContent
   * builders.
   *
   * @return void
   */
  private function setupProduct() {
    $download             = new \stdClass();
    $download->post_title = 'Encarta';
    \WP_Mock::userFunction(
      'edd_get_download',
      array( 'return' => $download )
    );
    \WP_Mock::userFunction(
      'get_post_meta',
      array( 'return' => array( array( 'amount' => 50 ) ) )
    );
  }

  /**
   * set_up_tracking registers the five Signals-routed EDD events.
   *
   * @return void
   */
  public function testInjectPixelCodeRegistersFiveSignalEvents() {
    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressEasyDigitalDownloads(
      $signals,
      new EventDataBuilder()
    );

    $signals->expects( $this->exactly( 5 ) )->method( 'on' );

    \WP_Mock::expectActionAdded(
      'edd_purchase_link_top',
      array( $integration, 'inject_add_to_cart_event_id' )
    );

    $integration->set_up_tracking();

    $this->assertConditionsMet();
  }

  /**
   * A dispatched InitiateCheckout tracks the event with cart totals.
   *
   * @return void
   */
  public function testInitiateCheckoutEventWithoutInternalUser() {
    $this->setupUserAndOptions();
    $this->setupEddUtils();
    \WP_Mock::userFunction( 'EDD' );

    list( $integration, $signals ) = $this->makeIntegration();

    $data = $integration->read_initiate_checkout();
    $signals->dispatch(
      'InitiateCheckout',
      $data,
      FacebookWordpressEasyDigitalDownloads::TRACKING_NAME
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
      $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
    );
  }

  /**
   * A dispatched Purchase tracks the event with order data.
   *
   * @return void
   */
  public function testPurchaseEventWithoutInternalUser() {
    $this->setupUserAndOptions();

    \WP_Mock::userFunction(
      'edd_get_payment_meta',
      array(
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

    list( $integration, $signals ) = $this->makeIntegration();

    $payment     = new \stdClass();
    $payment->ID = 1;

    $data = $integration->read_purchase( $payment, null );
    $signals->dispatch(
      'Purchase',
      $data,
      FacebookWordpressEasyDigitalDownloads::TRACKING_NAME
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
    $this->assertEquals( 'product', $event->getCustomData()->getContentType() );
    $this->assertEquals(
      array( 99, 999 ),
      $event->getCustomData()->getContentIds()
    );
    $this->assertEquals(
      'easy-digital-downloads',
      $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
    );
  }

  /**
   * A dispatched ViewContent tracks the event with product data.
   *
   * @return void
   */
  public function testViewContentEventWithoutInternalUser() {
    $this->setupUserAndOptions();
    $this->setupEddUtils();
    $this->setupProduct();

    list( $integration, $signals ) = $this->makeIntegration();

    $data = $integration->read_view_content( 1234 );
    $signals->dispatch(
      'ViewContent',
      $data,
      FacebookWordpressEasyDigitalDownloads::TRACKING_NAME
    );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 1, $tracked_events );

    $event       = $tracked_events[0];
    $custom_data = $event->getCustomData();
    $user_data   = $event->getUserData();

    $this->assertEquals( 'ViewContent', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
    $this->assertEquals( 'pika.chu@s2s.com', $user_data->getEmail() );
    $this->assertEquals( 'pika', $user_data->getFirstName() );
    $this->assertEquals( 'chu', $user_data->getLastName() );
    $this->assertEquals( array( '1234' ), $custom_data->getContentIds() );
    $this->assertEquals( 'product', $custom_data->getContentType() );
    $this->assertEquals( 'USD', $custom_data->getCurrency() );
    $this->assertEquals( 'Encarta', $custom_data->getContentName() );
    $this->assertEquals( 50, $custom_data->getValue() );
  }

  /**
   * A dispatched AJAX AddToCart tracks the event with the shared event id and
   * product data.
   *
   * @return void
   */
  public function testAddToCartEventAjaxWithoutInternalUser() {
    $this->setupUserAndOptions();
    $this->setupEddUtils();
    $this->setupProduct();

    $_POST['nonce']       = '54321';
    $_POST['download_id'] = '1234';
    $_POST['post_data']   = 'facebook_event_id=abc-123';

    \WP_Mock::userFunction( 'absint', array( 'return' => 1234 ) );
    \WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );

    list( $integration, $signals ) = $this->makeIntegration();

    $data = $integration->read_add_to_cart();
    $signals->dispatch(
      'AddToCart',
      $data,
      FacebookWordpressEasyDigitalDownloads::TRACKING_NAME
    );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 1, $tracked_events );

    $event       = $tracked_events[0];
    $custom_data = $event->getCustomData();

    $this->assertEquals( 'AddToCart', $event->getEventName() );
    $this->assertEquals( 'abc-123', $event->getEventId() );
    $this->assertNotNull( $event->getEventTime() );
    $this->assertEquals(
      'pika.chu@s2s.com',
      $event->getUserData()->getEmail()
    );
    $this->assertEquals( array( '1234' ), $custom_data->getContentIds() );
    $this->assertEquals( 'product', $custom_data->getContentType() );
    $this->assertEquals( 'USD', $custom_data->getCurrency() );
    $this->assertEquals( 'Encarta', $custom_data->getContentName() );
    $this->assertEquals( 50, $custom_data->getValue() );
  }

  /**
   * read_add_to_cart returns null (nothing dispatched) when the nonce fails.
   *
   * @return void
   */
  public function testAddToCartSkipsOnBadNonce() {
    $_POST['nonce']       = 'bad';
    $_POST['download_id'] = '1234';
    $_POST['post_data']   = 'facebook_event_id=abc-123';

    \WP_Mock::userFunction( 'absint', array( 'return' => 1234 ) );
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $input ) {
          return $input;
        },
      )
    );
    \WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => false ) );

    list( $integration ) = $this->makeIntegration();

    $this->assertNull( $integration->read_add_to_cart() );
  }

  /**
   * The hidden shared-id field is printed for a non-internal user.
   *
   * @return void
   */
  public function testInjectAddToCartEventIdOutputsHiddenField() {
    self::mockIsInternalUser( false );
    \WP_Mock::userFunction(
      'esc_attr',
      array(
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    list( $integration ) = $this->makeIntegration();

    $integration->inject_add_to_cart_event_id();

    $this->expectOutputRegex(
      '/input type="hidden" name="facebook_event_id"/'
    );
  }

  /**
   * The hidden shared-id field is not printed for an internal user.
   *
   * @return void
   */
  public function testInjectAddToCartEventIdSkipsInternalUser() {
    self::mockIsInternalUser( true );

    list( $integration ) = $this->makeIntegration();

    $integration->inject_add_to_cart_event_id();

    $this->expectOutputString( '' );
  }

  /**
   * The AddToCart listener enqueues nothing for an internal user.
   *
   * @return void
   */
  public function testInjectAddToCartListenerSkipsInternalUser() {
    self::mockIsInternalUser( true );

    list( $integration ) = $this->makeIntegration();

    $integration->inject_add_to_cart_listener( 1234 );

    $this->expectOutputString( '' );
  }
}
