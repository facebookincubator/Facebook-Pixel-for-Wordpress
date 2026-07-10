<?php
/**
 * Facebook Pixel Plugin WooCommerceIntegrationHelper class.
 *
 * This file contains helper methods that wrap WooCommerce functions used by the
 * tracking integration.
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

namespace FacebookPixelPlugin\Integration\Helpers;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Helper methods that wrap WooCommerce functions used by the tracking
 * integration.
 */
class WooCommerceIntegrationHelper {
    /**
     * Retrieves the current WooCommerce cart.
     *
     * @return \WC_Cart|null The cart object, or null if WooCommerce is unavailable.
     */
    public static function get_cart() {
        if ( ! function_exists( 'WC' ) ) {
            return null;
        }
        return WC()->cart;
    }

    /**
     * Retrieves the WooCommerce order for the given ID.
     *
     * @param int $id The WooCommerce order ID.
     * @return \WC_Order|null The order object, or null if WooCommerce is unavailable.
     */
    public static function get_order( $id ) {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return null;
        }
        return wc_get_order( $id );
    }

    /**
     * Retrieves the active WooCommerce store currency code.
     *
     * @return string The currency code.
     */
    public static function get_currency() {
        return get_woocommerce_currency();
    }

    /**
     * Retrieves the WooCommerce product for the given ID.
     *
     * @param int $id The WooCommerce product ID.
     * @return \WC_Product|false The product object, or false if not found.
     */
    public static function get_product( $id ) {
        return wc_get_product( $id );
    }

    /**
     * Retrieves a single cart item from the current cart by its key.
     *
     * @param string $cart_item_key The cart item key.
     * @return array|null The cart item, or null if the cart or item is unavailable.
     */
    public static function get_cart_item( $cart_item_key ) {
        $cart = self::get_cart();
        if ( empty( $cart ) ) {
            return null;
        }
        $cart_items = $cart->get_cart();
            if ( ! empty( $cart_items ) && ! empty( $cart_items[ $cart_item_key ] ) ) {
            return $cart_items[ $cart_item_key ];
    }
        return null;
    }
}
