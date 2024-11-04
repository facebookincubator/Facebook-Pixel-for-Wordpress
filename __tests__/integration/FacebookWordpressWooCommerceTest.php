<?php
/**
 * Facebook Pixel Plugin FacebookWordpressWooCommerceTest class.
 *
 * This file contains the main logic for FacebookWordpressWooCommerceTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressWooCommerceTest class.
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

use FacebookPixelPlugin\Integration\FacebookWordpressWooCommerce;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Tests\Mocks\MockWC;
use FacebookPixelPlugin\Tests\Mocks\MockWCCart;
use FacebookPixelPlugin\Tests\Mocks\MockWCOrder;
use FacebookPixelPlugin\Tests\Mocks\MockWCProduct;
use FacebookAds\Object\ServerSide\Event;

/**
 * FacebookWordpressWooCommerceTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressWooCommerceTest extends FacebookWordpressTestBase {

	/**
	 * Tests the inject_pixel_code method when the Facebook for WooCommerce plugin is not active.
	 *
	 * This test verifies that the appropriate WordPress action hooks are added,
	 * specifically checking that the 'woocommerce_after_checkout_form' hook is
	 * registered with the 'trackInitiateCheckout' method from the
	 * FacebookWordpressWooCommerce class.
	 *
	 * @return void
	 */
	public function testInjectPixelCodeWithWooNotActive() {
		$this->mockFacebookForWooCommerce( false );

		\WP_Mock::expectActionAdded(
			'woocommerce_after_checkout_form',
			array(
				FacebookWordpressWooCommerce::class,
				'trackInitiateCheckout',
			),
			40
		);

		FacebookWordpressWooCommerce::inject_pixel_code();
	}

	/**
	 * Tests the inject_pixel_code method when the Facebook for WooCommerce plugin is active.
	 *
	 * This test verifies that the 'woocommerce_after_checkout_form' action hook
	 * is not added when the plugin is active, ensuring that the 'trackInitiateCheckout'
	 * method from the FacebookWordpressWooCommerce class is not registered.
	 *
	 * @return void
	 */
	public function testInjectPixelCodeWithWooActive() {
		$this->mockFacebookForWooCommerce( true );

		\WP_Mock::expectActionNotAdded(
			'woocommerce_after_checkout_form',
			array(
				FacebookWordpressWooCommerce::class,
				'trackInitiateCheckout',
			),
			40
		);

		FacebookWordpressWooCommerce::inject_pixel_code();
	}

	/**
	 * Tests that the trackPurchaseEvent method correctly records a 'Purchase' event
	 * when the user is not an internal user.
	 *
	 * This test verifies that the server-side event tracking records the
	 * 'Purchase' event with the correct user and custom data attributes.
	 *
	 * @return void
	 */
	public function testPurchaseEventWithoutInternalUser() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$this->setupMocks();

		FacebookWordpressWooCommerce::trackPurchaseEvent( 1 );
		$tracked_events =
		FacebookServerSideEvent::get_instance()->get_tracked_events();

		$this->assertCount( 1, $tracked_events );

		$event = $tracked_events[0];
		$this->assertEquals( 'Purchase', $event->getEventName() );
		$this->assertNotNull( $event->getEventTime() );
		$this->assertEquals( 'pika.chu@s2s.com', $event->getUserData()->getEmail() );
		$this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
		$this->assertEquals( 'chu', $event->getUserData()->getLastName() );
		$this->assertEquals( '2062062006', $event->getUserData()->getPhone() );
		$this->assertEquals( 'springfield', $event->getUserData()->getCity() );
		$this->assertEquals( 'ohio', $event->getUserData()->getState() );
		$this->assertEquals( 'us', $event->getUserData()->getCountryCode() );
		$this->assertEquals( '12345', $event->getUserData()->getZipCode() );
		$this->assertEquals( 'USD', $event->getCustomData()->getCurrency() );
		$this->assertEquals( 900, $event->getCustomData()->getValue() );
		$this->assertEquals( 'wc_post_id_1', $event->getCustomData()->getContentIds()[0] );

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
	 * Tests that the trackInitiateCheckout method correctly records an
	 * 'InitiateCheckout' event when the user is not an internal user.
	 *
	 * This test verifies that the server-side event tracking records the
	 * 'InitiateCheckout' event with the correct user and custom data
	 * attributes.
	 *
	 * @return void
	 */
	public function testInitiateCheckoutEventWithoutInternalUser() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$this->setupMocks();
		$this->setupCustomerBillingAddress();

		FacebookWordpressWooCommerce::trackInitiateCheckout();
		$tracked_events =
		FacebookServerSideEvent::get_instance()->get_tracked_events();

		$this->assertCount( 1, $tracked_events );

		$event = $tracked_events[0];

		$this->assertEquals( 'InitiateCheckout', $event->getEventName() );
		$this->assertNotNull( $event->getEventTime() );
		$this->assertEquals( 'pika.chu@s2s.com', $event->getUserData()->getEmail() );
		$this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
		$this->assertEquals( 'chu', $event->getUserData()->getLastName() );
		$this->assertEquals( '2062062006', $event->getUserData()->getPhone() );
		$this->assertEquals( 'springfield', $event->getUserData()->getCity() );
		$this->assertEquals( 'ohio', $event->getUserData()->getState() );
		$this->assertEquals( 'us', $event->getUserData()->getCountryCode() );
		$this->assertEquals( '12345', $event->getUserData()->getZipCode() );
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
	 * Tests that the trackAddToCartEvent method correctly records an
	 * 'AddToCart' event when the user is not an internal user.
	 *
	 * This test verifies that the server-side event tracking records the
	 * 'AddToCart' event with the correct user and custom data attributes.
	 *
	 * @return void
	 */
	public function testAddToCartEventWithoutInternalUser() {
		\WP_Mock::userFunction(
			'wp_doing_ajax',
			array( 'return' => false )
		);
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$this->setupMocks();
		$this->setupCustomerBillingAddress();

		FacebookWordpressWooCommerce::trackAddToCartEvent( 1, 1, 3, null );
		$tracked_events =
		FacebookServerSideEvent::get_instance()->get_tracked_events();

		$this->assertCount( 1, $tracked_events );

		$event = $tracked_events[0];

		$this->assertEquals( 'AddToCart', $event->getEventName() );
		$this->assertNotNull( $event->getEventTime() );
		$this->assertEquals( 'pika.chu@s2s.com', $event->getUserData()->getEmail() );
		$this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
		$this->assertEquals( 'chu', $event->getUserData()->getLastName() );
		$this->assertEquals( '2062062006', $event->getUserData()->getPhone() );
		$this->assertEquals( 'springfield', $event->getUserData()->getCity() );
		$this->assertEquals( 'ohio', $event->getUserData()->getState() );
		$this->assertEquals( 'us', $event->getUserData()->getCountryCode() );
		$this->assertEquals( '12345', $event->getUserData()->getZipCode() );
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
	 * Tests the trackAddToCartEvent method when the user is not an internal user
	 * and the request is an AJAX request.
	 *
	 * This test verifies that the "woocommerce_add_to_cart_fragments" filter is
	 * added and that the server-side event is tracked with the correct parameters
	 * when the user is not an internal user and the request is an AJAX request.
	 */
	public function testAddToCartEventAjaxWithoutInternalUser() {
		\WP_Mock::userFunction(
			'wp_doing_ajax',
			array( 'return' => true )
		);
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$this->setupMocks();
		$this->setupCustomerBillingAddress();

		\WP_Mock::expectFilterAdded(
			'woocommerce_add_to_cart_fragments',
			array(
				FacebookWordpressWooCommerce::class,
				'addPixelCodeToAddToCartFragment',
			)
		);

		FacebookWordpressWooCommerce::trackAddToCartEvent( 1, 1, 3, null );

		$tracked_events =
		FacebookServerSideEvent::get_instance()->get_tracked_events();

		$this->assertCount( 1, $tracked_events );

		$event = $tracked_events[0];

		$this->assertEquals( 'AddToCart', $event->getEventName() );
		$this->assertNotNull( $event->getEventTime() );
		$this->assertEquals( 'pika.chu@s2s.com', $event->getUserData()->getEmail() );
		$this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
		$this->assertEquals( 'chu', $event->getUserData()->getLastName() );
		$this->assertEquals( '2062062006', $event->getUserData()->getPhone() );
		$this->assertEquals( 'springfield', $event->getUserData()->getCity() );
		$this->assertEquals( 'ohio', $event->getUserData()->getState() );
		$this->assertEquals( 'us', $event->getUserData()->getCountryCode() );
		$this->assertEquals( '12345', $event->getUserData()->getZipCode() );
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
	 * Tests the trackViewContentEvent method when the user is not an internal user.
	 *
	 * This test verifies that the Pixel code is correctly injected into the HTML
	 * output and that the server-side event is tracked with the correct parameters
	 * when the user is not an internal user. It asserts that the output HTML matches
	 * the expected pattern for the "ViewContent" event.
	 *
	 * @return void
	 */
	public function testViewContentWithoutAdmin() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$this->setupMocks();
		$this->setupCustomerBillingAddress();

		$raw_post     = new \stdClass();
		$raw_post->ID = 1;
		global $post;
		$post = $raw_post;

		FacebookWordpressWooCommerce::trackViewContentEvent();

		$tracked_events =
		FacebookServerSideEvent::get_instance()->get_tracked_events();

		$this->assertCount( 1, $tracked_events );

		$event = $tracked_events[0];

		$this->assertNotNull( $event->getEventTime() );
		$this->assertEquals( 'pika.chu@s2s.com', $event->getUserData()->getEmail() );
		$this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
		$this->assertEquals( 'chu', $event->getUserData()->getLastName() );
		$this->assertEquals( '2062062006', $event->getUserData()->getPhone() );
		$this->assertEquals( 'springfield', $event->getUserData()->getCity() );
		$this->assertEquals( 'ohio', $event->getUserData()->getState() );
		$this->assertEquals( 'us', $event->getUserData()->getCountryCode() );
		$this->assertEquals( '12345', $event->getUserData()->getZipCode() );

		$this->assertEquals( 10, $event->getCustomData()->getValue() );
		$this->assertEquals(
			'wc_post_id_1',
			$event->getCustomData()->getContentIds()[0]
		);
		$this->assertEquals(
			'Stegosaurus',
			$event->getCustomData()->getContentName()
		);
		$this->assertEquals(
			'product',
			$event->getCustomData()->getContentType()
		);
		$this->assertEquals(
			'USD',
			$event->getCustomData()->getCurrency()
		);
		$this->assertEquals(
			'Dinosaurs',
			$event->getCustomData()->getContentCategory()
		);

		$this->assertEquals(
			'woocommerce',
			$event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
		);
	}

	/**
	 * Test that the enqueuePixelCode method correctly enqueues the
	 * appropriate Pixel code for WooCommerce events when the user
	 * is not an internal user.
	 *
	 * This test verifies that the output contains the expected Meta Pixel
	 * Event Code for WooCommerce and that the server-side event tracking
	 * records the event with the correct user and custom data attributes.
	 */
	public function testEnqueuePixelEvent() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$this->setupMocks();
		$server_event = new Event();
		$pixel_code   = FacebookWordpressWooCommerce::enqueuePixelCode( $server_event );
		$this->assertMatchesRegularExpression(
			'/woocommerce[\s\S]+End Meta Pixel Event Code/',
			$pixel_code
		);
	}

	/**
	 * Tests that the addPixelCodeToAddToCartFragment method correctly adds
	 * the Pixel code to the AJAX response fragments.
	 *
	 * This test verifies that the Pixel code is included in the AJAX fragments
	 * returned by the addPixelCodeToAddToCartFragment method when the
	 * Facebook for WooCommerce integration is triggered. It ensures that the
	 * appropriate HTML element ID is present in the fragments array and that
	 * the Pixel code matches the expected pattern for WooCommerce.
	 *
	 * @return void
	 */
	public function testAddPixelCodeToAddToCartFragment() {
		self::mockFacebookWordpressOptions();

		$server_event = new Event();
		FacebookServerSideEvent::get_instance()->set_pending_pixel_event(
			'addPixelCodeToAddToCartFragment',
			$server_event
		);

		$fragments =
		FacebookWordpressWooCommerce::addPixelCodeToAddToCartFragment( array() );

		$this->assertArrayHasKey(
			'#' . FacebookWordpressWooCommerce::DIV_ID_FOR_AJAX_PIXEL_EVENTS,
			$fragments
		);
		$pxl_div_code =
		$fragments[ '#' . FacebookWordpressWooCommerce::DIV_ID_FOR_AJAX_PIXEL_EVENTS ];
		$this->assertMatchesRegularExpression(
			'/id=\'fb-pxl-ajax-code\'[\s\S]+woocommerce/',
			$pxl_div_code
		);
	}

	/**
	 * Mocks the presence of the Facebook for WooCommerce plugin.
	 *
	 * This function simulates the activation status of the Facebook for WooCommerce
	 * plugin by mocking the 'get_option' function. It returns a specific plugin
	 * identifier if the plugin is active, otherwise returns an empty array.
	 *
	 * @param bool $active Determines if the plugin is considered active.
	 *                     If true, the plugin is mocked as active.
	 *                     If false, the plugin is mocked as inactive.
	 *
	 * @return void
	 */
	private function mockFacebookForWooCommerce( $active ) {
		\WP_Mock::userFunction(
			'get_option',
			array(
				'return' => $active ?
				array( 'facebook-for-woocommerce/facebook-for-woocommerce.php' )
				: array(),
			)
		);
	}

	/**
	 * Sets up customer billing address mocks.
	 *
	 * This method is used to simulate the presence of customer billing address
	 * data. It uses WP_Mock to define the results of the get_user_meta function
	 * when requesting the billing city, state, postcode, country, and phone.
	 *
	 * @return void
	 */
	private function setupCustomerBillingAddress() {
		\WP_Mock::userFunction(
			'get_user_meta',
			array(
				'times'  => 1,
				'args'   => array( \WP_Mock\Functions::type( 'int' ), 'billing_city', true ),
				'return' => 'Springfield',
			)
		);
		\WP_Mock::userFunction(
			'get_user_meta',
			array(
				'times'  => 1,
				'args'   => array( \WP_Mock\Functions::type( 'int' ), 'billing_state', true ),
				'return' => 'Ohio',
			)
		);
		\WP_Mock::userFunction(
			'get_user_meta',
			array(
				'times'  => 1,
				'args'   => array(
					\WP_Mock\Functions::type( 'int' ),
					'billing_postcode',
					true,
				),
				'return' => '12345',
			)
		);
		\WP_Mock::userFunction(
			'get_user_meta',
			array(
				'times'  => 1,
				'args'   => array(
					\WP_Mock\Functions::type( 'int' ),
					'billing_country',
					true,
				),
				'return' => 'US',
			)
		);
		\WP_Mock::userFunction(
			'get_user_meta',
			array(
				'times'  => 1,
				'args'   => array( \WP_Mock\Functions::type( 'int' ), 'billing_phone', true ),
				'return' => '2062062006',
			)
		);
	}

	/**
	 * Sets up various mocks for the WooCommerce integration tests.
	 *
	 * This method uses WP_Mock to define the results of various functions that are
	 * used in the WooCommerce integration code. It sets up the following mocks:
	 *
	 * - WC(): Returns a MockWC object.
	 * - wc_get_order(): Returns a MockWCOrder object.
	 * - wc_get_product(): Returns a MockWCProduct object.
	 * - get_current_user_id(): Returns 1.
	 * - get_the_terms(): Returns an array containing a stdClass object with a name
	 *                    property set to 'Dinosaurs'.
	 * - wc_enqueue_js(): Does nothing.
	 *
	 * Additionally, it sets up the MockWC object to return a MockWCCart object when
	 * calling WC()->cart.
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
			array(
				'return' => 'USD',
			)
		);

		$cart = new MockWCCart();
		$cart->add_item( 1, 1, 3, 300 );

		\WP_Mock::userFunction(
			'WC',
			array(
				'return' => new MockWC( $cart ),
			)
		);

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

		\WP_Mock::userFunction(
			'wc_get_order',
			array(
				'return' => $order,
			)
		);

		\WP_Mock::userFunction(
			'wc_get_product',
			array(
				'return' => new MockWCProduct( 1, 'single_product', 'Stegosaurus', 10 ),
			)
		);

		\WP_Mock::userFunction(
			'get_current_user_id',
			array(
				'return' => 1,
			)
		);
		$term       = new \stdClass();
		$term->name = 'Dinosaurs';
		\WP_Mock::userFunction(
			'get_the_terms',
			array(
				'return' => array( $term ),
			)
		);

		\WP_Mock::userFunction( 'wc_enqueue_js', array() );
	}
}
