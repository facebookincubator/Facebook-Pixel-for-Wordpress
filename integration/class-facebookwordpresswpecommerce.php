<?php
/**
 * Facebook Pixel Plugin FacebookWordpressWPECommerce class.
 *
 * This file contains the main logic for FacebookWordpressWPECommerce.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressWPECommerce class.
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

namespace FacebookPixelPlugin\Integration;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

use FacebookPixelPlugin\Core\FacebookPixel;
use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\PixelRenderer;

/**
 * FacebookWordpressWPECommerce class.
 */
class FacebookWordpressWPECommerce extends FacebookWordpressIntegrationBase {
    const PLUGIN_FILE   = 'wp-e-commerce/wp-e-commerce.php';
    const TRACKING_NAME = 'wp-e-commerce';

    /**
     * Injects Facebook Pixel events for WP eCommerce.
     *
     * This method sets up WordPress actions to inject Facebook Pixel events
     * for different stages of the WP eCommerce process:
     *
     * - AddToCart: Hooks into the JSON response
     * after an item is added to the cart.
     * - InitiateCheckout: Fires a pixel event before
     * the shopping cart page is displayed.
     * - Purchase: Triggers a pixel event after
     * the transaction results are processed.
     *
     * Hooks are added with specific priorities
     * to ensure correct execution order.
     */
    public static function inject_pixel_code() {
        add_action(
            'wpsc_add_to_cart_json_response',
            array( __CLASS__, 'injectAddToCartEvent' ),
            11
        );

        self::add_pixel_fire_for_hook(
            array(
                'hook_name'       => 'wpsc_before_shopping_cart_page',
                'classname'       => __CLASS__,
                'inject_function' => 'injectInitiateCheckoutEvent',
            )
        );

        add_action(
            'wpsc_transaction_results_shutdown',
            array( __CLASS__, 'injectPurchaseEvent' ),
            11,
            3
        );
    }

    /**
     * Injects Facebook Pixel code for add to cart events.
     *
     * This method is called from the
     * `wpsc_add_to_cart_json_response` action hook.
     * It creates an "AddToCart" event and tracks
     * it using the Facebook server-side
     * API. It then injects the Facebook Pixel code into the response.
     *
     * @param array $response The JSON response
     * after an item is added to the cart.
     * @return array The modified response with the Facebook Pixel code added.
     */
    public static function injectAddToCartEvent( $response ) {
        if ( FacebookPluginUtils::is_internal_user() ) {
            return $response;
        }

        $product_id   = $response['product_id'];
        $server_event = ServerEventFactory::safe_create_event(
            'AddToCart',
            array( __CLASS__, 'createAddToCartEvent' ),
            array( $product_id ),
            self::TRACKING_NAME
        );
            FacebookServerSideEvent::get_instance()->track( $server_event );

            $code                   = PixelRenderer::render(
                array( $server_event ),
                self::TRACKING_NAME
            );
        $code                       = sprintf(
            '
        <!-- Meta Pixel Event Code -->
        %s
        <!-- End Meta Pixel Event Code -->
            ',
            $code
        );
        $response['widget_output'] .= $code;
        return $response;
    }

    /**
     * Injects a Meta Pixel InitiateCheckout event.
     *
     * This method is called from the
     * `wpsc_before_shopping_cart_page` action hook.
     * It injects a Meta Pixel InitiateCheckout
     * event into the page whenever a shopping cart is rendered.
     *
     * @since 1.0.0
     */
    public static function injectInitiateCheckoutEvent() {
        if ( FacebookPluginUtils::is_internal_user() ) {
            return;
        }

        $server_event = ServerEventFactory::safe_create_event(
            'InitiateCheckout',
            array( __CLASS__, 'createInitiateCheckoutEvent' ),
            array(),
            self::TRACKING_NAME
        );
            FacebookServerSideEvent::get_instance()->track( $server_event );

            $code = PixelRenderer::render(
                array(
                    $server_event,
                ),
                self::TRACKING_NAME
            );
        printf(
            '
    <!-- Meta Pixel Event Code -->
    %s
    <!-- End Meta Pixel Event Code -->
          ',
            $code // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        );
    }

    /**
     * Injects a Meta Pixel Purchase event.
     *
     * This method is triggered by the
     * `wpsc_transaction_results_shutdown` action hook.
     * It creates and tracks a "Purchase"
     * event using the Facebook server-side API,
     * injecting the Facebook Pixel code
     * into the page if the user is not internal and
     * the display_to_screen flag is true.
     *
     * @param object $purchase_log_object The
     * purchase log object containing transaction details.
     * @param mixed  $session_id The session
     * ID for the current user session.
     * @param bool   $display_to_screen Flag
     * indicating whether to display the Pixel code on screen.
     *
     * @since 1.0.0
     */
    public static function injectPurchaseEvent(
        $purchase_log_object,
        $session_id,
        $display_to_screen
    ) {
        if ( FacebookPluginUtils::is_internal_user() || ! $display_to_screen ) {
            return;
        }

        $server_event = ServerEventFactory::safe_create_event(
            'Purchase',
            array( __CLASS__, 'createPurchaseEvent' ),
            array( $purchase_log_object ),
            self::TRACKING_NAME
        );
        FacebookServerSideEvent::get_instance()->track( $server_event );

        $code = PixelRenderer::render(
            array(
                $server_event,
            ),
            self::TRACKING_NAME
        );

        printf(
            '
    <!-- Meta Pixel Event Code -->
    %s
    <!-- End Meta Pixel Event Code -->
        ',
            $code // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        );
    }

    /**
     * Generates a Meta Pixel Purchase event data.
     *
     * The Purchase event is fired when a customer completes a purchase.
     * It is typically sent when a customer submits an order.
     *
     * The method loops through the items in the order and creates a
     * Meta Pixel Content object for each item. The method then sets the
     * content_type, currency, value, content_ids and contents fields in
     * the event data.
     *
     * @param object $purchase_log_object The
     * purchase log object containing transaction details.
     *
     * @return array The event data.
     *
     * @since 1.0.0
     */
    public static function createPurchaseEvent( $purchase_log_object ) {
        $event_data = FacebookPluginUtils::get_logged_in_user_info();

        $cart_items  = $purchase_log_object->get_items();
        $total_price = $purchase_log_object->get_total();
        $currency    = function_exists( '\wpsc_get_currency_code' ) ?
        \wpsc_get_currency_code() : '';

        $item_ids = array();
        foreach ( $cart_items as $item ) {
            $item_array = (array) $item;
            $item_ids[] = $item_array['prodid'];
        }

        $event_data['content_ids']  = $item_ids;
        $event_data['content_type'] = 'product';
        $event_data['currency']     = $currency;
        $event_data['value']        = $total_price;

        return $event_data;
    }

    /**
     * Generates a Meta Pixel AddToCart event data.
     *
     * The AddToCart event is fired when a customer adds a product to their
     * cart. It is typically sent when a customer adds a product to their
     * cart.
     *
     * The method loops through the items in the cart and creates a Meta Pixel
     * Content object for the product that was added to the cart. The method
     * then sets the content_type, currency, value, content_ids and contents
     * fields in the event data.
     *
     * @param int $product_id The product ID.
     *
     * @return array The event data.
     *
     * @since 1.0.0
     */
    public static function createAddToCartEvent( $product_id ) {
        $event_data = FacebookPluginUtils::get_logged_in_user_info();

        global $wpsc_cart;
        $cart_items = $wpsc_cart->get_items();
        foreach ( $cart_items as $item ) {
            if ( $item->product_id === $product_id ) {
            $unit_price = $item->unit_price;
            break;
            }
        }

        $event_data['content_ids']  = array( $product_id );
        $event_data['content_type'] = 'product';
        $event_data['currency']     =
        function_exists( '\wpsc_get_currency_code' ) ?
        \wpsc_get_currency_code() : '';
        $event_data['value']        = $unit_price;

        return $event_data;
    }

    /**
     * Generates a Meta Pixel InitiateCheckout event data.
     *
     * The InitiateCheckout event is fired when a customer initiates a checkout.
     * It is typically sent when a customer clicks a "checkout" button
     * or submits an order.
     *
     * The method loops through the items in the cart and creates a Meta Pixel
     * Content object for each item. The method then sets the content_type,
     * currency, value, content_ids and contents fields in the event data.
     *
     * @return array The event data.
     *
     * @since 1.0.0
     */
    public static function createInitiateCheckoutEvent() {
        $event_data  = FacebookPluginUtils::get_logged_in_user_info();
        $content_ids = array();

        $value = 0;
        global $wpsc_cart;
        $cart_items = $wpsc_cart->get_items();
        foreach ( $cart_items as $item ) {
            $content_ids[] = $item->product_id;
            $value        += $item->unit_price;
        }

        $event_data['currency']    =
        function_exists( '\wpsc_get_currency_code' ) ?
        \wpsc_get_currency_code() : '';
        $event_data['value']       = $value;
        $event_data['content_ids'] = $content_ids;

        return $event_data;
    }
}
