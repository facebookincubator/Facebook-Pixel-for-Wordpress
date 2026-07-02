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

use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Core\FooterDelivery;
use FacebookPixelPlugin\Core\AjaxHtmlDelivery;

/**
 * FacebookWordpressWPECommerce class.
 */
class FacebookWordpressWPECommerce extends FacebookWordpressIntegrationBase {
    const PLUGIN_FILE   = 'wp-e-commerce/wp-e-commerce.php';
    const TRACKING_NAME = 'wp-e-commerce';

    /**
     * Registers the WP eCommerce events through Signals:
     *  - AddToCart: on the add-to-cart JSON response (appended to
     *    widget_output).
     *  - InitiateCheckout: in the footer of the shopping cart page.
     *  - Purchase: in the footer after a transaction completes.
     *
     * @return void
     */
    public function inject_pixel_code() {
        $this->signals->on(
            'wpsc_add_to_cart_json_response',
            'AddToCart',
            array( $this, 'read_add_to_cart' ),
            new AjaxHtmlDelivery(
                'wpsc_add_to_cart_json_response',
                'widget_output'
            ),
            self::TRACKING_NAME,
            11,
            1
        );

        $this->signals->on(
            'wpsc_before_shopping_cart_page',
            'InitiateCheckout',
            array( $this, 'read_initiate_checkout' ),
            new FooterDelivery(),
            self::TRACKING_NAME,
            10,
            1
        );

        $this->signals->on(
            'wpsc_transaction_results_shutdown',
            'Purchase',
            array( $this, 'read_purchase' ),
            new FooterDelivery(),
            self::TRACKING_NAME,
            11,
            3
        );
    }

    /**
     * Builds the AddToCart EventData from the add-to-cart JSON response.
     *
     * @param array $response The add-to-cart JSON response.
     * @return \FacebookPixelPlugin\Core\EventData|null
     */
    public function read_add_to_cart( $response ) {
        if ( empty( $response['product_id'] ) ) {
            return null;
        }
        return $this->event_builder->build(
            self::createAddToCartEvent( $response['product_id'] )
        );
    }

    /**
     * Builds the InitiateCheckout EventData for the shopping cart page.
     *
     * @return \FacebookPixelPlugin\Core\EventData
     */
    public function read_initiate_checkout() {
        return $this->event_builder->build(
            self::createInitiateCheckoutEvent()
        );
    }

    /**
     * Builds the Purchase EventData from a completed transaction.
     *
     * @param object $purchase_log_object The purchase log object.
     * @param mixed  $session_id          The session id (unused).
     * @param bool   $display_to_screen   Whether the results are shown.
     * @return \FacebookPixelPlugin\Core\EventData|null
     */
    public function read_purchase(
        $purchase_log_object,
        $session_id = null,
        $display_to_screen = false
    ) {
        if ( ! $display_to_screen ) {
            return null;
        }
        return $this->event_builder->build(
            self::createPurchaseEvent( $purchase_log_object )
        );
    }

    /**
     * Generates a Meta Pixel Purchase event data.
     *
     * @param object $purchase_log_object The purchase log object containing
     *                                    transaction details.
     * @return array The event data.
     */
    private static function createPurchaseEvent( $purchase_log_object ) {
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
     * @param int $product_id The product ID.
     * @return array The event data.
     */
    private static function createAddToCartEvent( $product_id ) {
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
     * @return array The event data.
     */
    private static function createInitiateCheckoutEvent() {
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
