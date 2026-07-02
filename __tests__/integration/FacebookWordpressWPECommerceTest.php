<?php
/**
 * Facebook Pixel Plugin FacebookWordpressWPECommerceTest class.
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
use FacebookPixelPlugin\Integration\FacebookWordpressWPECommerce;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressWPECommerceTest class.
 *
 * These tests assert the same observable behavior as before the Signals
 * refactor: driving an event through the integration tracks a server-side
 * event carrying the correct user/custom data. The only change is the entry
 * point — the read_* data provider plus the real Signals dispatch path
 * instead of the old inject/track static methods.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressWPECommerceTest extends FacebookWordpressTestBase {

  /**
   * Builds a WP eCommerce integration wired to a real Signals dispatcher, so a
   * dispatched event actually tracks through FacebookServerSideEvent.
   *
   * @return array [ FacebookWordpressWPECommerce, Signals ]
   */
  private function makeIntegration() {
    $signals     = new Signals();
    $integration = new FacebookWordpressWPECommerce(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * Mocks the current user, plugin options, and logged-in user PII used by the
   * create* event builders.
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
   * Sets up the WP eCommerce cart global and currency helper used by the
   * AddToCart/InitiateCheckout builders.
   *
   * @return void
   */
  private function setupCart() {
    $mock_cart = \Mockery::mock();
    $mock_cart->shouldReceive( 'get_items' )
      ->andReturn(
        array(
          '1' => (object) array(
            'product_id' => 1,
            'unit_price' => 999,
          ),
        )
      );

    $GLOBALS['wpsc_cart'] = $mock_cart;

    \WP_Mock::userFunction(
      'wpsc_get_currency_code',
      array( 'return' => 'USD' )
    );
  }

  /**
   * set_up_tracking registers the three WP eCommerce events.
   *
   * @return void
   */
  public function testInjectPixelCodeRegistersThreeEvents() {
    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressWPECommerce(
      $signals,
      new EventDataBuilder()
    );

    $signals->expects( $this->exactly( 3 ) )->method( 'on' );

    $integration->set_up_tracking();

    $this->assertConditionsMet();
  }

  /**
   * A dispatched AddToCart tracks an AddToCart server event with cart data.
   *
   * @return void
   */
  public function testAddToCartEventWithoutInternalUser() {
    $this->setupUserAndOptions();
    $this->setupCart();
    $this->mockRenderHelpers();

    list( $integration, $signals ) = $this->makeIntegration();

    $data = $integration->read_add_to_cart(
      array(
        'product_id'    => 1,
        'widget_output' => '',
      )
    );
    $signals->dispatch(
      'AddToCart',
      $data,
      FacebookWordpressWPECommerce::TRACKING_NAME
    );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 1, $tracked_events );

    $event = $tracked_events[0];
    $this->assertEquals( 'AddToCart', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
    $this->assertEquals(
      'pika.chu@s2s.com',
      $event->getUserData()->getEmail()
    );
    $this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
    $this->assertEquals( 'chu', $event->getUserData()->getLastName() );
    $this->assertEquals( 'USD', $event->getCustomData()->getCurrency() );
    $this->assertEquals( 999, $event->getCustomData()->getValue() );
    $this->assertEquals(
      'product',
      $event->getCustomData()->getContentType()
    );
    $this->assertEquals(
      array( 1 ),
      $event->getCustomData()->getContentIds()
    );
    $this->assertEquals(
      'wp-e-commerce',
      $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
    );
  }

  /**
   * A dispatched InitiateCheckout tracks an InitiateCheckout server event with
   * the cart data.
   *
   * @return void
   */
  public function testInitiateCheckoutEventWithoutInternalUser() {
    $this->setupUserAndOptions();
    $this->setupCart();
    $this->mockRenderHelpers();

    list( $integration, $signals ) = $this->makeIntegration();

    $data = $integration->read_initiate_checkout();
    $signals->dispatch(
      'InitiateCheckout',
      $data,
      FacebookWordpressWPECommerce::TRACKING_NAME
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
    $this->assertEquals( 999, $event->getCustomData()->getValue() );
    $this->assertEquals(
      array( 1 ),
      $event->getCustomData()->getContentIds()
    );
    $this->assertEquals(
      'wp-e-commerce',
      $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
    );
  }

  /**
   * A dispatched Purchase tracks a Purchase server event with order data.
   *
   * @return void
   */
  public function testPurchaseEventWithoutInternalUser() {
    $this->setupUserAndOptions();
    $this->mockRenderHelpers();

    \WP_Mock::userFunction(
      'wpsc_get_currency_code',
      array( 'return' => 'USD' )
    );

    $log = \Mockery::mock();
    $log->shouldReceive( 'get_items' )
      ->andReturn( array( 0 => (object) array( 'prodid' => '1' ) ) );
    $log->shouldReceive( 'get_total' )->andReturn( 999 );

    list( $integration, $signals ) = $this->makeIntegration();

    $data = $integration->read_purchase( $log, null, true );
    $signals->dispatch(
      'Purchase',
      $data,
      FacebookWordpressWPECommerce::TRACKING_NAME
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
    $this->assertEquals( 999, $event->getCustomData()->getValue() );
    $this->assertEquals(
      'product',
      $event->getCustomData()->getContentType()
    );
    $this->assertEquals(
      array( '1' ),
      $event->getCustomData()->getContentIds()
    );
    $this->assertEquals(
      'wp-e-commerce',
      $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
    );
  }

  /**
   * read_add_to_cart returns null when the response has no product id.
   *
   * @return void
   */
  public function testReadAddToCartSkipsWithoutProductId() {
    list( $integration ) = $this->makeIntegration();

    $this->assertNull( $integration->read_add_to_cart( array() ) );
  }

  /**
   * read_purchase returns null when results are not displayed.
   *
   * @return void
   */
  public function testReadPurchaseSkipsWhenNotDisplayed() {
    list( $integration ) = $this->makeIntegration();
    $log                 = \Mockery::mock();

    $this->assertNull( $integration->read_purchase( $log, null, false ) );
  }
}
