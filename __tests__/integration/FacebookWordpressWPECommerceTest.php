<?php
/**
 * Facebook Pixel Plugin FacebookWordpressWPECommerceTest class.
 *
 * This file contains the main logic for FacebookWordpressWPECommerceTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressWPECommerceTest class.
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

use FacebookPixelPlugin\Integration\FacebookWordpressWPECommerce;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;

/**
 * FacebookWordpressWPECommerceTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressWPECommerceTest extends FacebookWordpressTestBase {
	/**
	 * Tests that the inject_pixel_code method correctly sets up the
	 * necessary WordPress hooks for the Facebook Pixel events in
	 * the WP eCommerce integration.
	 *
	 * This test verifies that the appropriate hooks are added for
	 * the 'injectAddToCartEvent', 'injectInitiateCheckoutEvent', and
	 * 'injectPurchaseEvent' events with the expected hook names.
	 *
	 * Utilizes the Mockery library to mock the base integration class
	 * and validate that the add_pixel_fire_for_hook method is called with
	 * the correct parameters.
	 */
	public function testInjectPixelCode() {
		\WP_Mock::expectActionAdded(
			'wpsc_add_to_cart_json_response',
			array(
                FacebookWordpressWPECommerce::class,
				'injectAddToCartEvent',
            ),
			11
		);

		$mocked_base =
        \Mockery::mock(
            'alias:FacebookPixelPlugin\Integration\FacebookWordpressIntegrationBase'
        );
		$mocked_base->shouldReceive( 'add_pixel_fire_for_hook' )
		->with(
			array(
				'hook_name'       => 'wpsc_before_shopping_cart_page',
				'classname'       => FacebookWordpressWPECommerce::class,
				'inject_function' => 'injectInitiateCheckoutEvent',
			)
		)
		->once();
		\WP_Mock::expectActionAdded(
			'wpsc_transaction_results_shutdown',
			array( FacebookWordpressWPECommerce::class, 'injectPurchaseEvent' ),
			11,
			3
		);

		FacebookWordpressWPECommerce::inject_pixel_code();

		$this->assertHooksAdded();
	}

	/**
	 * Tests that the injectAddToCartEvent method injects the correct
	 * Pixel code when the user is not an internal user.
	 *
	 * This test verifies that the output contains the expected Meta Pixel
	 * Event Code for WP eCommerce and that the server-side event tracking
	 * records the 'AddToCart' event with the correct user and custom data
	 * attributes.
	 */
	public function testInjectAddToCartEventWithoutInternalUser() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$parameter = array(
			'product_id'    => 1,
			'widget_output' => '',
		);

		$this->setupMocks();

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

		$response =
        FacebookWordpressWPECommerce::injectAddToCartEvent( $parameter );

		$this->assertArrayHasKey( 'widget_output', $response );
		$code = $response['widget_output'];
		$this->assertMatchesRegularExpression(
			'/wp-e-commerce[\s\S]+End Meta Pixel Event Code/',
			$code
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
			$event->getCustomData()
            ->getCustomProperty( 'fb_integration_tracking' )
		);
	}

	/**
	 * Tests that the injectAddToCartEvent method does not inject any Pixel code
	 * when the user is an internal user.
	 *
	 * This test verifies that the output does not contain any Meta Pixel
	 * Event Code when the injectAddToCartEvent method is called by an
	 * internal user.
	 */
	public function testInjectAddToCartEventWithInternalUser() {
		self::mockIsInternalUser( true );
		$parameter = array(
			'product_id'    => 1,
			'widget_output' => '',
		);

		$response =
        FacebookWordpressWPECommerce::injectAddToCartEvent( $parameter );

		$this->assertArrayHasKey( 'widget_output', $response );
		$code = $response['widget_output'];
		$this->assertEquals( '', $code );
	}

	/**
	 * Tests that the injectInitiateCheckoutEvent
     * method correctly injects the Pixel code
	 * for the 'InitiateCheckout' event when the user is not an internal user.
	 *
	 * This test verifies that the output contains
     * the expected Meta Pixel Event Code for
	 * WP e-Commerce and that the server-side event
     * tracking records the 'InitiateCheckout'
	 * event with the correct user and custom data attributes.
	 */
	public function testInitiateCheckoutEventWithoutInternalUser() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$this->setupMocks();

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

        \WP_Mock::userFunction(
            'sanitize_text_field',
            array(
				'args'   => array( \Mockery::any() ),
				'return' => function ( $input ) {
					return $input;
				},
            )
        );

		FacebookWordpressWPECommerce::injectInitiateCheckoutEvent();
		$this->expectOutputRegex(
			'/wp-e-commerce[\s\S]+End Meta Pixel Event Code/'
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
			'wp-e-commerce',
			$event->getCustomData()
            ->getCustomProperty( 'fb_integration_tracking' )
		);
	}

	/**
	 * Tests that the injectInitiateCheckoutEvent method does not inject
	 * any Pixel code when the user is an internal user.
	 *
	 * This test verifies that the output does not contain any Meta Pixel
	 * Event Code when the injectInitiateCheckoutEvent method is called by an
	 * internal user.
	 */
	public function testInitiateCheckoutEventWithInternalUser() {
		self::mockIsInternalUser( true );

		FacebookWordpressWPECommerce::injectInitiateCheckoutEvent();
		$this->expectOutputString( '' );
	}

	/**
	 * Tests that the injectPurchaseEvent method
     * correctly injects the Pixel code
	 * for the 'Purchase' event when the user is not an internal user.
	 *
	 * This test verifies that the output contains
     * the expected Meta Pixel Event Code
	 * for WP e-Commerce and that the server-side
     * event tracking records the 'Purchase'
	 * event with the correct user and custom data attributes.
	 */
	public function testInjectPurchaseEventWithoutInternalUser() {
		self::mockIsInternalUser( false );
		self::mockFacebookWordpressOptions();

		$this->setupMocks();

		$mock_purchase_log_object = \Mockery::mock();
		$purchase_log_object      = $mock_purchase_log_object;
		$session_id               = null;
		$display_to_screen        = true;

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

        \WP_Mock::userFunction(
            'sanitize_text_field',
            array(
				'args'   => array( \Mockery::any() ),
				'return' => function ( $input ) {
					return $input;
				},
            )
        );

		$mock_purchase_log_object->shouldReceive( 'get_items' )
		->andReturn( array( 0 => (object) array( 'prodid' => '1' ) ) );
		$mock_purchase_log_object->shouldReceive( 'get_total' )
		->andReturn( 999 );

		FacebookWordpressWPECommerce::injectPurchaseEvent(
			$purchase_log_object,
			$session_id,
			$display_to_screen
		);

		$this->expectOutputRegex(
			'/wp-e-commerce[\s\S]+End Meta Pixel Event Code/'
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
			'wp-e-commerce',
			$event->getCustomData()
            ->getCustomProperty( 'fb_integration_tracking' )
		);
	}

	/**
	 * Tests that the injectPurchaseEvent method does not inject any Pixel code
	 * when the user is an internal user.
	 *
	 * This test verifies that the output does not contain any Meta Pixel
	 * Event Code when the injectPurchaseEvent method is called by an
	 * internal user, even if the display_to_screen flag is true.
	 */
	public function testInjectPurchaseEventWithInternalUser() {
		self::mockIsInternalUser( true );

		$mock_purchase_log_object = \Mockery::mock();
		$purchase_log_object      = $mock_purchase_log_object;
		$session_id               = null;
		$display_to_screen        = true;

		$mock_purchase_log_object->shouldReceive( 'get_items' )
		->andReturn( array( 0 => (object) array( 'prodid' => '1' ) ) );
		$mock_purchase_log_object->shouldReceive( 'get_total' )
		->andReturn( 999 );

		FacebookWordpressWPECommerce::injectPurchaseEvent(
			$purchase_log_object,
			$session_id,
			$display_to_screen
		);
		$this->expectOutputString( '' );
	}

	/**
	 * Sets up various mock objects and functions for
     * the WP e-Commerce integration tests.
	 *
	 * This method uses Mockery to create a mock cart and
     * define the behavior of the
	 * get_items method to return a predefined set of cart
     * items. It also sets up a global
	 * variable for the cart and mocks the getLoggedInUserInfo
     * method to return specific
	 * user details. Additionally, it mocks the
     * wpsc_get_currency_code function to return
	 * a fixed currency code. These mocks are used to
     * simulate the environment and behavior
	 * required for testing the Facebook Pixel integration with WP e-Commerce.
	 *
	 * @return void
	 */
	private function setupMocks() {
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

		$this->mocked_fbpixel->shouldReceive( 'get_logged_in_user_info' )
		->andReturn(
			array(
				'email'      => 'pika.chu@s2s.com',
				'first_name' => 'Pika',
				'last_name'  => 'Chu',
			)
		);

		\WP_Mock::userFunction(
			'wpsc_get_currency_code',
			array(
				'return' => 'USD',
			)
		);
	}
}
