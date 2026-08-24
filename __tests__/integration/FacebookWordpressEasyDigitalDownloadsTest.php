<?php
/**
 * Facebook Pixel Plugin FacebookWordpressEasyDigitalDownloadsTest class.
 *
 * This file contains the main logic for FacebookWordpressEasyDigitalDownloadsTest.
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

use FacebookPixelPlugin\Integration\FacebookWordpressEasyDigitalDownloads;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressEasyDigitalDownloadsTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressEasyDigitalDownloadsTest extends FacebookWordpressTestBase {

    /**
     * Builds the integration wired to the shared signals double.
     *
     * @return FacebookWordpressEasyDigitalDownloads
     */
    private function make_integration() {
        return new FacebookWordpressEasyDigitalDownloads( $this->make_signals() );
    }

    /**
     * Invokes the protected set_up_tracking() (the new equivalent of the legacy
     * static inject_pixel_code()).
     *
     * @param FacebookWordpressEasyDigitalDownloads $obj The integration.
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
     * Mocks the current user + billing meta so get_user_info() resolves the same
     * PII the legacy tests asserted (email/first/last).
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
        \WP_Mock::userFunction( 'get_user_meta', array( 'return' => '' ) );
    }

    /**
     * Mocks the Easy Digital Downloads store functions (EDD instance, currency,
     * payment meta, download, price meta).
     *
     * @return void
     */
    private function mock_edd_store() {
        $cart = new class() {
            /**
             * Returns the cart total.
             *
             * @return int The cart total.
             */
            public function get_total() {
                return 300;
            }
        };
        $edd       = new \stdClass();
        $edd->cart = $cart;
        \WP_Mock::userFunction( 'EDD', array( 'return' => $edd ) );
        \WP_Mock::userFunction( 'edd_get_currency', array( 'return' => 'USD' ) );

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

        $download             = new \stdClass();
        $download->post_title = 'Encarta';
        \WP_Mock::userFunction( 'edd_get_download', array( 'return' => $download ) );
        \WP_Mock::userFunction(
            'get_post_meta',
            array( 'return' => array( array( 'amount' => 50 ) ) )
        );
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
     * Tests set_up_tracking wires the tracking hooks for a non-internal user.
     *
     * @return void
     */
    public function testSetUpTrackingAddsHooks() {
        self::mockIsInternalUser( false );

        $obj = $this->make_integration();
        \WP_Mock::expectActionAdded(
            'edd_after_checkout_cart',
            array( $obj, 'track_initiate_checkout_event' ),
            11
        );
        \WP_Mock::expectActionAdded(
            'edd_payment_receipt_after',
            array( $obj, 'track_purchase_event' ),
            10,
            2
        );
        \WP_Mock::expectActionAdded(
            'edd_after_download_content',
            array( $obj, 'track_view_content_event' ),
            40
        );
        \WP_Mock::expectActionAdded(
            'wp_ajax_edd_add_to_cart',
            array( $obj, 'inject_ajax_add_to_cart_listener' ),
            5
        );

        $this->set_up_tracking( $obj );

        $this->assertHooksAdded();
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
            'edd_after_checkout_cart',
            array( $obj, 'track_initiate_checkout_event' )
        );

        $this->set_up_tracking( $obj );

        $this->assertConditionsMet();
    }

    /**
     * Tests that track_initiate_checkout_event() builds an InitiateCheckout event
     * with user PII, currency and cart total.
     *
     * @return void
     */
    public function testInitiateCheckoutEvent() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_user_info();
        $this->mock_edd_store();
        $this->mock_wp_functions();

        $this->make_integration()->track_initiate_checkout_event();

        $this->assertCount( 1, $this->captured_events );
        $event = $this->captured_events[0];

        $this->assertEquals( 'InitiateCheckout', $event->getEventName() );
        $this->assertEquals( 'pika.chu@s2s.com', $event->getUserData()->getEmail() );
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
     * Tests that track_purchase_event() builds a Purchase event from the payment
     * meta (PII, currency, summed value, content ids).
     *
     * @return void
     */
    public function testPurchaseEvent() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_edd_store();
        $this->mock_wp_functions();

        $payment     = new \stdClass();
        $payment->ID = 1;

        $this->make_integration()->track_purchase_event( $payment, null );

        $this->assertCount( 1, $this->captured_events );
        $event = $this->captured_events[0];

        $this->assertEquals( 'Purchase', $event->getEventName() );
        $this->assertEquals( 'pika.chu@s2s.com', $event->getUserData()->getEmail() );
        $this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
        $this->assertEquals( 'chu', $event->getUserData()->getLastName() );
        $this->assertEquals( 'USD', $event->getCustomData()->getCurrency() );
        $this->assertEquals( 700, $event->getCustomData()->getValue() );
        $this->assertEquals( 'product', $event->getCustomData()->getContentType() );
        $this->assertEquals( array( 99, 999 ), $event->getCustomData()->getContentIds() );
        $this->assertEquals(
            'easy-digital-downloads',
            $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
        );
    }

    /**
     * Tests that track_view_content_event() builds a ViewContent event for the
     * given download.
     *
     * @return void
     */
    public function testViewContentEvent() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_user_info();
        $this->mock_edd_store();
        $this->mock_wp_functions();

        $this->make_integration()->track_view_content_event( 1234 );

        $this->assertCount( 1, $this->captured_events );
        $event       = $this->captured_events[0];
        $custom_data = $event->getCustomData();

        $this->assertEquals( 'ViewContent', $event->getEventName() );
        $this->assertEquals( 'pika.chu@s2s.com', $event->getUserData()->getEmail() );
        $this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
        $this->assertEquals( 'chu', $event->getUserData()->getLastName() );
        $this->assertEquals( array( '1234' ), $custom_data->getContentIds() );
        $this->assertEquals( 'product', $custom_data->getContentType() );
        $this->assertEquals( 'USD', $custom_data->getCurrency() );
        $this->assertEquals( 'Encarta', $custom_data->getContentName() );
        $this->assertEquals( 50, $custom_data->getValue() );
    }

    /**
     * Tests that the AJAX add-to-cart handler sends a deduplicated (event-id
     * carrying) server-side AddToCart event.
     *
     * @return void
     */
    public function testAjaxAddToCart() {
        self::mockIsInternalUser( false );
        self::mockFacebookWordpressOptions();
        $this->mock_user_info();
        $this->mock_edd_store();
        $this->mock_wp_functions();

        $_POST['nonce']       = '54321';
        $_POST['download_id'] = '1234';
        $_POST['post_data']   = 'facebook_event_id=abc-123';

        \WP_Mock::userFunction( 'absint', array( 'return' => 1234 ) );
        \WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );

        $this->make_integration()->inject_ajax_add_to_cart_listener();

        $this->assertCount( 1, $this->captured_events );
        $event       = $this->captured_events[0];
        $custom_data = $event->getCustomData();

        $this->assertEquals( 'abc-123', $event->getEventId() );
        $this->assertEquals( 'AddToCart', $event->getEventName() );
        $this->assertEquals( 'pika.chu@s2s.com', $event->getUserData()->getEmail() );
        $this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
        $this->assertEquals( 'chu', $event->getUserData()->getLastName() );
        $this->assertEquals( array( '1234' ), $custom_data->getContentIds() );
        $this->assertEquals( 'product', $custom_data->getContentType() );
        $this->assertEquals( 'USD', $custom_data->getCurrency() );
        $this->assertEquals( 'Encarta', $custom_data->getContentName() );
        $this->assertEquals( 50, $custom_data->getValue() );

        // Server-only delivery: nothing enqueued to the browser Pixel.
        $this->assertEmpty( $this->enqueued_events );
    }

    /**
     * Tests that inject_add_to_cart_event_id() outputs the hidden dedup input.
     *
     * @return void
     */
    public function testInjectAddToCartEventId() {
        \WP_Mock::userFunction(
            'esc_attr',
            array(
                'return' => function ( $v ) {
                    return $v;
                },
            )
        );

        $this->make_integration()->inject_add_to_cart_event_id();

        $this->expectOutputRegex( '/name="facebook_event_id"/' );
    }
}
