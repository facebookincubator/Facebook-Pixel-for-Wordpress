<?php
/**
 * Facebook Pixel Plugin FacebookWordpressWooCommerceTest class.
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
use FacebookPixelPlugin\Core\InlineScriptDelivery;
use FacebookPixelPlugin\Core\CartFragmentDelivery;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Integration\FacebookWordpressWooCommerce;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Tests\Mocks\MockWC;
use FacebookPixelPlugin\Tests\Mocks\MockWCCart;
use FacebookPixelPlugin\Tests\Mocks\MockWCOrder;
use FacebookPixelPlugin\Tests\Mocks\MockWCProduct;

/**
 * FacebookWordpressWooCommerceTest class.
 *
 * These tests assert the same observable behavior as before the Signals
 * refactor: firing an event through the integration ends up tracking a
 * server-side event carrying the correct user/custom data, and the AJAX
 * add-to-cart path still yields a cart fragment with the pixel code. The only
 * change is the entry point — the data provider plus the real Signals dispatch
 * path instead of the old track/inject static methods.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressWooCommerceTest extends FacebookWordpressTestBase {

  /**
   * Builds a WooCommerce integration wired to a real Signals dispatcher, so a
   * dispatched event actually tracks through FacebookServerSideEvent.
   *
   * @return array [ FacebookWordpressWooCommerce, Signals ]
   */
  private function makeIntegration() {
    $signals     = new Signals();
    $integration = new FacebookWordpressWooCommerce(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * Mocks the Facebook-for-WooCommerce active-plugin check.
   *
   * @param bool $active Whether the plugin is active.
   * @return void
   */
  private function mockFacebookForWooCommerce( $active ) {
    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => $active
          ? array( 'facebook-for-woocommerce/facebook-for-woocommerce.php' )
          : array(),
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
   * Sets up the WooCommerce data-source mocks used by the create* builders.
   *
   * @return void
   */
  private function setupMocks() {
    $this->mocked_fbpixel->shouldReceive( 'get_logged_in_user_info' )
      ->andReturn(
        array(
          'email'      => 'pika.chu@s2s.com',
          'first_name' => 'Pika',
          'last_name'  => 'Chu',
        )
      );

    \WP_Mock::userFunction(
      'get_woocommerce_currency',
      array( 'return' => 'USD' )
    );

    $cart = new MockWCCart();
    $cart->add_item( 1, 1, 3, 300 );
    \WP_Mock::userFunction( 'WC', array( 'return' => new MockWC( $cart ) ) );

    $order = new MockWCOrder(
      'Pika',
      'Chu',
      'pika.chu@s2s.com',
      '2062062006',
      'Springfield',
      '12345',
      'Ohio',
      'US'
    );
    $order->add_item( 1, 3, 900 );
    \WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );

    \WP_Mock::userFunction(
      'wc_get_product',
      array(
        'return' => new MockWCProduct( 1, 'single_product', 'Stegosaurus', 10 ),
      )
    );

    \WP_Mock::userFunction( 'get_current_user_id', array( 'return' => 1 ) );

    $term       = new \stdClass();
    $term->name = 'Dinosaurs';
    \WP_Mock::userFunction(
      'get_the_terms',
      array( 'return' => array( $term ) )
    );
  }

  /**
   * Sets up the logged-in customer's billing address meta.
   *
   * @return void
   */
  private function setupCustomerBillingAddress() {
    $meta = array(
      'billing_city'     => 'Springfield',
      'billing_state'    => 'Ohio',
      'billing_postcode' => '12345',
      'billing_country'  => 'US',
      'billing_phone'    => '2062062006',
    );
    foreach ( $meta as $key => $value ) {
      \WP_Mock::userFunction(
        'get_user_meta',
        array(
          'args'   => array( \WP_Mock\Functions::type( 'int' ), $key, true ),
          'return' => $value,
        )
      );
    }
  }

  /**
   * Asserts the shared user data present on every WooCommerce event.
   *
   * @param object $event The tracked server event.
   * @return void
   */
  private function assertCustomerUserData( $event ) {
    $user_data = $event->getUserData();
    $this->assertEquals( 'pika.chu@s2s.com', $user_data->getEmail() );
    $this->assertEquals( 'pika', $user_data->getFirstName() );
    $this->assertEquals( 'chu', $user_data->getLastName() );
    $this->assertEquals( '2062062006', $user_data->getPhone() );
    $this->assertEquals( 'springfield', $user_data->getCity() );
    $this->assertEquals( 'ohio', $user_data->getState() );
    $this->assertEquals( 'us', $user_data->getCountryCode() );
    $this->assertEquals( '12345', $user_data->getZipCode() );
  }

  /**
   * inject_pixel_code registers six Signals events when Facebook for
   * WooCommerce is not active, and AddToCart alone uses the cart-fragment
   * delivery while the rest use the inline-script delivery.
   *
   * @return void
   */
  public function testInjectPixelCodeRegistersEventsWhenWooNotActive() {
    $this->mockFacebookForWooCommerce( false );

    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressWooCommerce(
      $signals,
      new EventDataBuilder()
    );

    $deliveries = array();
    $signals->expects( $this->exactly( 6 ) )
      ->method( 'on' )
      ->willReturnCallback(
        function ( $hook, $event, $provider, $delivery ) use ( &$deliveries ) {
          $deliveries[ $event ] = $delivery;
        }
      );

    $integration->inject_pixel_code();

    $this->assertInstanceOf(
      CartFragmentDelivery::class,
      $deliveries['AddToCart']
    );
    $this->assertInstanceOf(
      InlineScriptDelivery::class,
      $deliveries['InitiateCheckout']
    );
    $this->assertInstanceOf(
      InlineScriptDelivery::class,
      $deliveries['Purchase']
    );
    $this->assertInstanceOf(
      InlineScriptDelivery::class,
      $deliveries['ViewContent']
    );
  }

  /**
   * inject_pixel_code registers nothing when Facebook for WooCommerce owns
   * tracking.
   *
   * @return void
   */
  public function testInjectPixelCodeSkipsWhenWooActive() {
    $this->mockFacebookForWooCommerce( true );

    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressWooCommerce(
      $signals,
      new EventDataBuilder()
    );

    $signals->expects( $this->never() )->method( 'on' );

    $integration->inject_pixel_code();

    $this->assertConditionsMet();
  }

  /**
   * A dispatched Purchase tracks a Purchase server event with order data.
   *
   * @return void
   */
  public function testPurchaseEventWithoutInternalUser() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();
    $this->setupMocks();
    $this->setupCustomerBillingAddress();
    $this->mockRenderHelpers();

    list( $integration, $signals ) = $this->makeIntegration();

    $data = $integration->read_purchase( 1 );
    $signals->dispatch(
      'Purchase',
      $data,
      FacebookWordpressWooCommerce::TRACKING_NAME
    );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 1, $tracked_events );

    $event = $tracked_events[0];
    $this->assertEquals( 'Purchase', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
    $this->assertCustomerUserData( $event );
    $this->assertEquals( 'USD', $event->getCustomData()->getCurrency() );
    $this->assertEquals( 900, $event->getCustomData()->getValue() );
    $this->assertEquals(
      'wc_post_id_1',
      $event->getCustomData()->getContentIds()[0]
    );

    $contents = $event->getCustomData()->getContents();
    $this->assertCount( 1, $contents );
    $this->assertEquals( 'wc_post_id_1', $contents[0]->getProductId() );
    $this->assertEquals( 3, $contents[0]->getQuantity() );
    $this->assertEquals( 300, $contents[0]->getItemPrice() );

    $this->assertEquals(
      'woocommerce',
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
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();
    $this->setupMocks();
    $this->setupCustomerBillingAddress();
    $this->mockRenderHelpers();

    list( $integration, $signals ) = $this->makeIntegration();

    $data = $integration->read_initiate_checkout();
    $signals->dispatch(
      'InitiateCheckout',
      $data,
      FacebookWordpressWooCommerce::TRACKING_NAME
    );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 1, $tracked_events );

    $event = $tracked_events[0];
    $this->assertEquals( 'InitiateCheckout', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
    $this->assertCustomerUserData( $event );
    $this->assertEquals( 'USD', $event->getCustomData()->getCurrency() );
    $this->assertEquals( 900, $event->getCustomData()->getValue() );
    $this->assertEquals( 3, $event->getCustomData()->getNumItems() );
    $this->assertEquals(
      'wc_post_id_1',
      $event->getCustomData()->getContentIds()[0]
    );

    $contents = $event->getCustomData()->getContents();
    $this->assertCount( 1, $contents );
    $this->assertEquals( 'wc_post_id_1', $contents[0]->getProductId() );
    $this->assertEquals( 3, $contents[0]->getQuantity() );
    $this->assertEquals( 300, $contents[0]->getItemPrice() );

    $this->assertEquals(
      'woocommerce',
      $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
    );
  }

  /**
   * A dispatched AddToCart tracks an AddToCart server event with cart data.
   *
   * @return void
   */
  public function testAddToCartEventWithoutInternalUser() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();
    $this->setupMocks();
    $this->setupCustomerBillingAddress();
    $this->mockRenderHelpers();

    list( $integration, $signals ) = $this->makeIntegration();

    $data = $integration->read_add_to_cart( 1, 1, 3, null );
    $signals->dispatch(
      'AddToCart',
      $data,
      FacebookWordpressWooCommerce::TRACKING_NAME
    );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 1, $tracked_events );

    $event = $tracked_events[0];
    $this->assertEquals( 'AddToCart', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
    $this->assertCustomerUserData( $event );
    $this->assertEquals( 'USD', $event->getCustomData()->getCurrency() );
    $this->assertEquals( 900, $event->getCustomData()->getValue() );
    $this->assertEquals(
      'wc_post_id_1',
      $event->getCustomData()->getContentIds()[0]
    );

    $this->assertEquals(
      'woocommerce',
      $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
    );
  }

  /**
   * A dispatched ViewContent tracks a ViewContent server event for the
   * current product.
   *
   * @return void
   */
  public function testViewContentWithoutAdmin() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();
    $this->setupMocks();
    $this->setupCustomerBillingAddress();
    $this->mockRenderHelpers();

    $raw_post     = new \stdClass();
    $raw_post->ID = 1;
    global $post;
    $post = $raw_post;

    list( $integration, $signals ) = $this->makeIntegration();

    $data = $integration->read_view_content();
    $signals->dispatch(
      'ViewContent',
      $data,
      FacebookWordpressWooCommerce::TRACKING_NAME
    );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 1, $tracked_events );

    $event = $tracked_events[0];
    $this->assertEquals( 'ViewContent', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
    $this->assertCustomerUserData( $event );

    $custom_data = $event->getCustomData();
    $this->assertEquals( 10, $custom_data->getValue() );
    $this->assertEquals( 'wc_post_id_1', $custom_data->getContentIds()[0] );
    $this->assertEquals( 'Stegosaurus', $custom_data->getContentName() );
    $this->assertEquals( 'product', $custom_data->getContentType() );
    $this->assertEquals( 'USD', $custom_data->getCurrency() );
    $this->assertEquals( 'Dinosaurs', $custom_data->getContentCategory() );

    $this->assertEquals(
      'woocommerce',
      $custom_data->getCustomProperty( 'fb_integration_tracking' )
    );
  }

  /**
   * On an AJAX add-to-cart request the pixel code is delivered as a cart
   * fragment keyed to the container div — same behavior as the old
   * addPixelCodeToAddToCartFragment path.
   *
   * @return void
   */
  public function testAddToCartAjaxDeliversPixelFragment() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();
    $this->setupMocks();
    $this->setupCustomerBillingAddress();
    $this->mockRenderHelpers();
    \WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => true ) );

    list( $integration, $signals ) = $this->makeIntegration();

    $delivery = new CartFragmentDelivery(
      'woocommerce_add_to_cart_fragments',
      FacebookWordpressWooCommerce::DIV_ID_FOR_AJAX_PIXEL_EVENTS
    );
    $delivery->register( FacebookWordpressWooCommerce::TRACKING_NAME );

    $data  = $integration->read_add_to_cart( 1, 1, 3, null );
    $event = $signals->dispatch(
      'AddToCart',
      $data,
      FacebookWordpressWooCommerce::TRACKING_NAME
    );
    $delivery->queue( $event );

    $fragments = $delivery->inject_fragment( array() );

    $key = '#' . FacebookWordpressWooCommerce::DIV_ID_FOR_AJAX_PIXEL_EVENTS;
    $this->assertArrayHasKey( $key, $fragments );
    $this->assertMatchesRegularExpression(
      '/id=\'fb-pxl-ajax-code\'[\s\S]+woocommerce/',
      $fragments[ $key ]
    );
  }
}
