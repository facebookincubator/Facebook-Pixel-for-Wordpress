<?php
/**
 * Facebook Pixel Plugin FacebookWordpressWooCommerceTest class.
 *
 * This file contains the main logic for FacebookWordpressWooCommerceTest.
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

use FacebookPixelPlugin\Integration\FacebookWordpressWooCommerce;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Tests\Mocks\MockWC;
use FacebookPixelPlugin\Tests\Mocks\MockWCCart;
use FacebookPixelPlugin\Tests\Mocks\MockWCOrder;
use FacebookPixelPlugin\Tests\Mocks\MockWCProduct;

/**
 * FacebookWordpressWooCommerceTest class.
 *
 * Drives the public track_*_event() methods with a signals double and asserts
 * the event handed to CAPI/Pixel — the same event data (PII, currency, value,
 * content ids/contents, integration attribution) the legacy tests asserted.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressWooCommerceTest extends FacebookWordpressTestBase {

    /**
     * Builds the WooCommerce integration wired to the shared signals double.
     *
     * @return FacebookWordpressWooCommerce
     */
    private function make_integration() {
        return new FacebookWordpressWooCommerce( $this->make_signals() );
    }

    /**
     * Invokes the protected set_up_tracking() (the new equivalent of the legacy
     * static inject_pixel_code()).
     *
     * @param FacebookWordpressWooCommerce $obj The integration.
     * @return void
     */
    private function set_up_tracking( $obj ) {
        $method = new \ReflectionMethod( $obj, 'set_up_tracking' );
        if ( PHP_VERSION_ID < 80100 ) {
            $method->setAccessible( true );
        }
        $method->invoke( $obj );
    }

    /**
     * Mocks get_option('active_plugins') to control whether Facebook for
     * WooCommerce is considered active.
     *
     * @param bool $active Whether Facebook for WooCommerce is active.
     * @return void
     */
    private function mock_facebook_for_woocommerce( $active ) {
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
     * Mocks the current user + billing meta so get_user_info() resolves the same
     * PII the legacy tests asserted.
     *
     * @return void
     */
    private function mock_user_info() {
        $user                 = new \stdClass();
        $user->user_email     = 'pika.chu@s2s.com';
        $user->user_firstname = 'Pika';
        $user->user_lastname  = 'Chu';
        $user->ID             = 1;
        \WP_Mock::userFunction( 'wp_get_current_user', array( 'return' => $user ) );
        \WP_Mock::userFunction( 'get_current_user_id', array( 'return' => 1 ) );

        $billing = array(
            'billing_city'     => 'Springfield',
            'billing_postcode' => '12345',
            'billing_country'  => 'US',
            'billing_state'    => 'Ohio',
            'billing_phone'    => '2062062006',
        );
        foreach ( $billing as $key => $value ) {
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
     * Mocks the WooCommerce store functions (currency, cart, order, product,
     * category) used across the tracking methods.
     *
     * WP_Mock keeps the first registration for a given function, so tests that
     * need their own wc_get_order()/wc_get_product() doubles opt out here and
     * register them before calling the integration.
     *
     * @param bool $mock_order   Whether to register the default wc_get_order() double.
     * @param bool $mock_product Whether to register the default wc_get_product() double.
     * @return void
     */
    private function mock_woocommerce_store( $mock_order = true, $mock_product = true ) {
        \WP_Mock::userFunction( 'get_woocommerce_currency', array( 'return' => 'USD' ) );

        $cart = new MockWCCart();
        $cart->add_item( 1, 1, 3, 300 );
        \WP_Mock::userFunction( 'WC', array( 'return' => new MockWC( $cart ) ) );

        if ( $mock_order ) {
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
        }

        if ( $mock_product ) {
            \WP_Mock::userFunction(
                'wc_get_product',
                array( 'return' => new MockWCProduct( 1, 'single_product', 'Stegosaurus', 10 ) )
            );
        }

        $term       = new \stdClass();
        $term->name = 'Dinosaurs';
        \WP_Mock::userFunction( 'get_the_terms', array( 'return' => array( $term ) ) );
    }

    /**
     * Mocks the string/encoding functions the event serialization touches.
     *
     * @return void
     */
    private function mock_wp_functions() {
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
                'return' => function ( $data, $options = 0 ) {
                    return json_encode( $data );
                },
            )
        );
    }

    /**
     * Tests set_up_tracking wires the tracking hooks and plants the AJAX cart
     * container when Facebook for WooCommerce is not active.
     *
     * @return void
     */
    public function testSetUpTrackingWithWooNotActive() {
        self::mockIsInternalUser( false );
        $this->mock_facebook_for_woocommerce( false );
        \WP_Mock::userFunction( 'esc_attr', array( 'return' => function ( $v ) {
            return $v;
        } ) );

        $obj = $this->make_integration();
        \WP_Mock::expectActionAdded(
            'woocommerce_after_checkout_form',
            array( $obj, 'track_initiate_checkout_event' ),
            40
        );
        \WP_Mock::expectActionAdded(
            'woocommerce_add_to_cart',
            array( $obj, 'track_add_to_cart_event' ),
            40,
            4
        );
        \WP_Mock::expectActionAdded(
            'woocommerce_thankyou',
            array( $obj, 'track_purchase_event' ),
            40
        );
        \WP_Mock::expectActionAdded(
            'woocommerce_after_single_product',
            array( $obj, 'track_view_content_event' ),
            40
        );

        $this->set_up_tracking( $obj );

        $this->assertHooksAdded();
        $this->assertCount( 1, $this->registered_ajax_dom );
        // The container div registered on page load must use the same id the
        // cart-fragment update targets (see testAddPixelCodeToCartFragment).
        $this->assertStringContainsString(
            FacebookWordpressWooCommerce::AJAX_PIXEL_CONTAINER,
            $this->registered_ajax_dom[0]
        );
    }

    /**
     * Tests that no hooks are wired when Facebook for WooCommerce is active.
     *
     * @return void
     */
    public function testSetUpTrackingWithWooActive() {
        self::mockIsInternalUser( false );
        $this->mock_facebook_for_woocommerce( true );

        $obj = $this->make_integration();
        \WP_Mock::expectActionNotAdded(
            'woocommerce_after_checkout_form',
            array( $obj, 'track_initiate_checkout_event' )
        );

        $this->set_up_tracking( $obj );

        $this->assertEmpty( $this->registered_ajax_dom );
    }

    /**
     * Tests that no hooks are wired for an internal user.
     *
     * @return void
     */
    public function testSetUpTrackingSkipsForInternalUser() {
        self::mockIsInternalUser( true );

        $obj = $this->make_integration();
        \WP_Mock::expectActionNotAdded(
            'woocommerce_after_single_product',
            array( $obj, 'track_view_content_event' )
        );

        $this->set_up_tracking( $obj );

        $this->assertEmpty( $this->registered_ajax_dom );
    }

    /**
     * Tests that track_purchase_event() builds a Purchase event with the order's
     * billing PII, currency, value, content ids and contents.
     *
     * @return void
     */
    public function testPurchaseEvent() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_woocommerce_store();
        $this->mock_wp_functions();

        $this->make_integration()->track_purchase_event( 1 );

        $this->assertCount( 1, $this->captured_events );
        $event = $this->captured_events[0];

        $this->assertEquals( 'Purchase', $event->getEventName() );
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
     * Tests that track_purchase_event() skips order items whose product cannot be
     * resolved (wc_get_product() returns false) instead of failing.
     *
     * @return void
     */
    public function testPurchaseEventSkipsMissingProduct() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_woocommerce_store( false, false );
        $this->mock_wp_functions();

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
        $order->add_item( 404, 1, 100 );
        $order->add_item( 1, 3, 900 );
        \WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );

        \WP_Mock::userFunction(
            'wc_get_product',
            array(
                'return' => function ( $product_id ) {
                    if ( 404 === $product_id ) {
                        return false;
                    }

                    return new MockWCProduct( $product_id, 'single_product' );
                },
            )
        );

        $this->make_integration()->track_purchase_event( 1 );

        $this->assertCount( 1, $this->captured_events );
        $custom_data = $this->captured_events[0]->getCustomData();

        $this->assertEquals( array( 'wc_post_id_1' ), $custom_data->getContentIds() );
        $this->assertCount( 1, $custom_data->getContents() );
        $this->assertEquals(
            'wc_post_id_1',
            $custom_data->getContents()[0]->getProductId()
        );
    }

    /**
     * Tests that track_purchase_event() produces empty content arrays when none
     * of the order's products can be resolved.
     *
     * @return void
     */
    public function testPurchaseEventWithAllMissingProducts() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_woocommerce_store( false, false );
        $this->mock_wp_functions();

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
        $order->add_item( 404, 1, 100 );
        \WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
        \WP_Mock::userFunction( 'wc_get_product', array( 'return' => false ) );

        $this->make_integration()->track_purchase_event( 1 );

        $this->assertCount( 1, $this->captured_events );
        $custom_data = $this->captured_events[0]->getCustomData();

        $this->assertEmpty( $custom_data->getContentIds() );
        $this->assertEmpty( $custom_data->getContents() );
    }

    /**
     * Tests that track_add_to_cart_event() falls back to a direct product lookup
     * when the cart item key is not in WC()->cart (e.g. a private cart key from
     * another plugin such as a subscription cloning flow).
     *
     * @return void
     */
    public function testAddToCartEventWithMissingCartItemKey() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_user_info();
        $this->mock_woocommerce_store();
        $this->mock_wp_functions();
        \WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => false ) );

        $this->make_integration()->track_add_to_cart_event(
            'missing-cart-key',
            1,
            3,
            null
        );

        $this->assertCount( 1, $this->captured_events );
        $custom_data = $this->captured_events[0]->getCustomData();

        $this->assertEquals( 'USD', $custom_data->getCurrency() );
        $this->assertEquals( array( 'wc_post_id_1' ), $custom_data->getContentIds() );
        $this->assertEquals( 30.0, $custom_data->getValue() );
    }

    /**
     * Tests that track_add_to_cart_event() still emits partial event data when
     * both the cart item and the product lookup fail.
     *
     * @return void
     */
    public function testAddToCartEventWithMissingCartItemAndProduct() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_user_info();
        $this->mock_woocommerce_store( true, false );
        $this->mock_wp_functions();
        \WP_Mock::userFunction( 'wc_get_product', array( 'return' => false ) );
        \WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => false ) );

        $this->make_integration()->track_add_to_cart_event(
            'missing-cart-key',
            404,
            3,
            null
        );

        $this->assertCount( 1, $this->captured_events );
        $custom_data = $this->captured_events[0]->getCustomData();

        $this->assertEquals( 'USD', $custom_data->getCurrency() );
        $this->assertEmpty( $custom_data->getContentIds() );
        $this->assertEmpty( $custom_data->getValue() );
    }

    /**
     * Tests that track_initiate_checkout_event() builds an InitiateCheckout event
     * with user PII, currency, item count, value and cart contents.
     *
     * @return void
     */
    public function testInitiateCheckoutEvent() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_user_info();
        $this->mock_woocommerce_store();
        $this->mock_wp_functions();

        $this->make_integration()->track_initiate_checkout_event();

        $this->assertCount( 1, $this->captured_events );
        $event = $this->captured_events[0];

        $this->assertEquals( 'InitiateCheckout', $event->getEventName() );
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
     * Tests that a non-AJAX add-to-cart builds an AddToCart event and queues the
     * browser event for the footer render.
     *
     * @return void
     */
    public function testAddToCartEvent() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_user_info();
        $this->mock_woocommerce_store();
        $this->mock_wp_functions();
        \WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => false ) );

        $this->make_integration()->track_add_to_cart_event( 1, 1, 3, null );

        $this->assertCount( 1, $this->captured_events );
        $event = $this->captured_events[0];

        $this->assertEquals( 'AddToCart', $event->getEventName() );
        $this->assertEquals( 'pika.chu@s2s.com', $event->getUserData()->getEmail() );
        $this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
        $this->assertEquals( 'chu', $event->getUserData()->getLastName() );
        $this->assertEquals( 'USD', $event->getCustomData()->getCurrency() );
        $this->assertEquals( 900, $event->getCustomData()->getValue() );
        $this->assertEquals( 'wc_post_id_1', $event->getCustomData()->getContentIds()[0] );
        $this->assertEquals(
            'woocommerce',
            $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
        );

        // Non-AJAX add-to-cart: the browser event is queued for the footer render.
        $this->assertCount( 1, $this->enqueued_events );
        $this->assertEquals( 'AddToCart', $this->enqueued_events[0]->getEventName() );
    }

    /**
     * Tests that an AJAX add-to-cart registers the cart-fragment filter and still
     * sends the AddToCart event server-side.
     *
     * @return void
     */
    public function testAddToCartEventAjax() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_user_info();
        $this->mock_woocommerce_store();
        $this->mock_wp_functions();
        \WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => true ) );

        \WP_Mock::expectFilterAdded(
            'woocommerce_add_to_cart_fragments',
            \WP_Mock\Functions::type( 'callable' )
        );

        $this->make_integration()->track_add_to_cart_event( 1, 1, 3, null );

        $this->assertHooksAdded();
        $this->assertCount( 1, $this->captured_events );
        $this->assertEquals( 'AddToCart', $this->captured_events[0]->getEventName() );
        $this->assertEmpty( $this->enqueued_events );
    }

    /**
     * Tests that track_view_content_event() builds a ViewContent event with the
     * product's data + user PII.
     *
     * @return void
     */
    public function testViewContentEvent() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_user_info();
        $this->mock_woocommerce_store();
        $this->mock_wp_functions();

        $raw_post     = new \stdClass();
        $raw_post->ID = 1;
        global $post;
        $post = $raw_post;

        $this->make_integration()->track_view_content_event();

        $this->assertCount( 1, $this->captured_events );
        $event = $this->captured_events[0];

        $this->assertEquals( 'ViewContent', $event->getEventName() );
        $this->assertEquals( 'pika.chu@s2s.com', $event->getUserData()->getEmail() );
        $this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
        $this->assertEquals( 'chu', $event->getUserData()->getLastName() );
        $this->assertEquals( 10, $event->getCustomData()->getValue() );
        $this->assertEquals( 'wc_post_id_1', $event->getCustomData()->getContentIds()[0] );
        $this->assertEquals( 'Stegosaurus', $event->getCustomData()->getContentName() );
        $this->assertEquals( 'product', $event->getCustomData()->getContentType() );
        $this->assertEquals( 'USD', $event->getCustomData()->getCurrency() );
        $this->assertEquals( 'Dinosaurs', $event->getCustomData()->getContentCategory() );

        // contents must be a Content[] (not a JSON string) so the SDK can
        // normalize it for both the Pixel and CAPI transports.
        $contents = $event->getCustomData()->getContents();
        $this->assertCount( 1, $contents );
        $this->assertInstanceOf(
            \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Content::class,
            $contents[0]
        );
        $this->assertEquals( 'wc_post_id_1', $contents[0]->getProductId() );
        $this->assertEquals( 1, $contents[0]->getQuantity() );

        $this->assertEquals(
            'woocommerce',
            $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
        );
    }

    /**
     * Tests that add_js_tracking_code_to_cart_fragment() injects the wrapped pixel code
     * under the container selector (new equivalent of the legacy
     * addPixelCodeToAddToCartFragment test).
     *
     * @return void
     */
    public function testAddPixelCodeToCartFragment() {
        \WP_Mock::userFunction( 'esc_attr', array( 'return' => function ( $v ) {
            return $v;
        } ) );

        $container = FacebookWordpressWooCommerce::AJAX_PIXEL_CONTAINER;
        $fragments = $this->make_integration()->add_js_tracking_code_to_cart_fragment(
            array(),
            "fbq('track', 'AddToCart', {});",
            $container
        );

        $this->assertArrayHasKey( '#' . $container, $fragments );
        $this->assertMatchesRegularExpression(
            sprintf(
                "/id='%s'[\s\S]+fbq\('track'/",
                preg_quote( $container, '/' )
            ),
            $fragments[ '#' . $container ]
        );
    }
}
