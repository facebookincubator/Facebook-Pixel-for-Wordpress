<?php
/**
 * Facebook Pixel Plugin FacebookWordpressWooCommerce class.
 *
 * This file contains the main logic for FacebookWordpressWooCommerce.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressWooCommerce class.
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
use FacebookPixelPlugin\Core\InlineScriptDelivery;
use FacebookPixelPlugin\Core\CartFragmentDelivery;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Content;

/**
 * FacebookWordpressWooCommerce class.
 */
class FacebookWordpressWooCommerce extends FacebookWordpressIntegrationBase {
    const PLUGIN_FILE   =
    'facebook-for-woocommerce/facebook-for-woocommerce.php';
    const TRACKING_NAME = 'woocommerce';

    const FB_ID_PREFIX = 'wc_post_id_';

    const DIV_ID_FOR_AJAX_PIXEL_EVENTS = 'fb-pxl-ajax-code';

    /**
     * Registers the WooCommerce events through Signals, unless the Facebook for
     * WooCommerce plugin owns tracking.
     *
     * All events use the inline-script delivery except AddToCart, which uses a
     * cart-fragment delivery so the pixel rides the AJAX add-to-cart response.
     * InitiateCheckout and Purchase register on two hooks each to cover the
     * classic/block and thankyou/payment-complete flows.
     *
     * @return void
     */
    public function set_up_tracking() {
        if ( self::isFacebookForWooCommerceActive() ) {
            return;
        }

        // InitiateCheckout: classic checkout form + block-based checkout.
        $this->signals->on(
            'woocommerce_after_checkout_form',
            'InitiateCheckout',
            array( $this, 'read_initiate_checkout' ),
            new InlineScriptDelivery(),
            self::TRACKING_NAME,
            40,
            0
        );
        $this->signals->on(
            'woocommerce_blocks_checkout_enqueue_data',
            'InitiateCheckout',
            array( $this, 'read_initiate_checkout' ),
            new InlineScriptDelivery(),
            self::TRACKING_NAME,
            10,
            0
        );

        $this->signals->on(
            'woocommerce_add_to_cart',
            'AddToCart',
            array( $this, 'read_add_to_cart' ),
            new CartFragmentDelivery(
                'woocommerce_add_to_cart_fragments',
                self::DIV_ID_FOR_AJAX_PIXEL_EVENTS
            ),
            self::TRACKING_NAME,
            40,
            4
        );

        // Purchase: thankyou page + payment completion.
        $this->signals->on(
            'woocommerce_thankyou',
            'Purchase',
            array( $this, 'read_purchase' ),
            new InlineScriptDelivery(),
            self::TRACKING_NAME,
            40,
            1
        );
        $this->signals->on(
            'woocommerce_payment_complete',
            'Purchase',
            array( $this, 'read_purchase' ),
            new InlineScriptDelivery(),
            self::TRACKING_NAME,
            40,
            1
        );

        $this->signals->on(
            'woocommerce_after_single_product',
            'ViewContent',
            array( $this, 'read_view_content' ),
            new InlineScriptDelivery(),
            self::TRACKING_NAME,
            40,
            0
        );
    }

    /**
     * Extracts the ViewContent event data for the current product page.
     *
     * @return \FacebookPixelPlugin\Core\EventData|null
     */
    public function read_view_content() {
        global $post;
        if ( ! isset( $post->ID ) ) {
            return null;
        }

        $product = wc_get_product( $post->ID );
        if ( ! $product ) {
            return null;
        }

        return $this->event_builder->build(
            self::createViewContentEvent( $product )
        );
    }

    /**
     * Extracts the Purchase event data for a completed order.
     *
     * @param int $order_id The order ID.
     * @return \FacebookPixelPlugin\Core\EventData|null
     */
    public function read_purchase( $order_id = 0 ) {
        if ( empty( $order_id ) ) {
            return null;
        }

        return $this->event_builder->build(
            self::createPurchaseEvent( $order_id )
        );
    }

    /**
     * Extracts the AddToCart event data for a cart addition.
     *
     * @param string $cart_item_key The cart item key.
     * @param int    $product_id    The product ID.
     * @param int    $quantity      The quantity.
     * @param int    $variation_id  The variation ID (unused).
     * @return \FacebookPixelPlugin\Core\EventData|null
     */
    public function read_add_to_cart(
        $cart_item_key = '',
        $product_id = 0,
        $quantity = 0,
        $variation_id = 0
    ) {
        return $this->event_builder->build(
            self::createAddToCartEvent( $cart_item_key, $product_id, $quantity )
        );
    }

    /**
     * Extracts the InitiateCheckout event data from the WooCommerce session.
     *
     * @return \FacebookPixelPlugin\Core\EventData|null
     */
    public function read_initiate_checkout() {
        if ( null === WC()->cart
            || 0 === WC()->cart->get_cart_contents_count() ) {
            return null;
        }

        return $this->event_builder->build(
            self::createInitiateCheckoutEvent()
        );
    }

    /**
     * Generates a ViewContent event data.
     *
     * The ViewContent event is generated by
     * setting fields such as content_type,
     * currency, value, content_ids, content_name and content_category.
     *
     * @param WC_Product $product Product object.
     * @return array The event data.
     */
    public static function createViewContentEvent( $product ) {
        $event_data = self::getPIIFromSession();

        $product_id   = self::getProductId( $product );
        $content_type = $product->is_type( 'variable' ) ?
        'product_group' : 'product';

        $event_data['content_type']     = $content_type;
        $event_data['currency']         = \get_woocommerce_currency();
        $event_data['value']            = $product->get_price();
        $event_data['content_ids']      = array( $product_id );
        $event_data['content_name']     = $product->get_title();
        $event_data['content_category'] = self::getProductCategory(
            $product->get_id()
        );

        return array_filter( $event_data );
    }

    /**
     * Returns the first category name of a given product ID.
     *
     * This method gets all the categories associated with the given product ID
     * and returns the first category name. If no categories are associated with
     * the product, the method returns null.
     *
     * @param int $product_id Product ID.
     * @return string|null First category name
     * associated with the product, or null.
     */
    private static function getProductCategory( $product_id ) {
        $categories = get_the_terms(
            $product_id,
            'product_cat'
        );
        return ! empty( $categories ) && count( $categories ) > 0 ? $categories[0]->name : null;
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
     * @param int $order_id The order ID.
     *
     * @return array The event data.
     *
     * @since 1.0.0
     */
    public static function createPurchaseEvent( $order_id ) {
        $order = wc_get_order( $order_id );

        $content_type = 'product';
        $product_ids  = array();
        $contents     = array();

        foreach ( $order->get_items() as $item ) {
            $product = wc_get_product( $item->get_product_id() );
            if ( 'product_group' !== $content_type
            && $product->is_type( 'variable' ) ) {
            $content_type = 'product_group';
            }

            $quantity   = $item->get_quantity();
            $product_id = self::getProductId( $product );

            $content = new Content();
            $content->setProductId( $product_id );
            $content->setQuantity( $quantity );
            $content->setItemPrice( $item->get_total() / $quantity );

            $contents[]    = $content;
            $product_ids[] = $product_id;
        }

        $event_data                 = self::getPiiFromBillingInformation(
            $order
        );
        $event_data['content_type'] = $content_type;
        $event_data['currency']     = \get_woocommerce_currency();
        $event_data['value']        = $order->get_total();
        $event_data['content_ids']  = $product_ids;
        $event_data['contents']     = $contents;

        return $event_data;
    }

    /**
     * Creates a Meta Pixel AddToCart event data.
     *
     * The AddToCart event is fired when a customer adds a product to their
     * cart. It is typically sent when a customer adds a product to their
     * cart.
     *
     * @param string $cart_item_key The cart item key.
     * @param int    $product_id    The product ID.
     * @param int    $quantity      The quantity.
     *
     * @return array The event data.
     *
     * @since 1.0.0
     */
    public static function createAddToCartEvent(
        $cart_item_key,
        $product_id,
        $quantity
    ) {
        $event_data                 = self::getPIIFromSession();
        $event_data['content_type'] = 'product';
        $event_data['currency']     = \get_woocommerce_currency();

        $cart_item = self::getCartItem( $cart_item_key );
        if ( ! empty( $cart_item_key ) ) {
            $event_data['content_ids'] = array(
                self::getProductId(
                    $cart_item['data']
                ),
            );
            $event_data['value']       = self::getAddToCartValue(
                $cart_item,
                $quantity
            );
        }

        return $event_data;
    }

    /**
     * Creates a Meta Pixel InitiateCheckout event data.
     *
     * The InitiateCheckout event is triggered when
     * a customer initiates the checkout process.
     * This method gathers personal identifiable
     * information (PII) from the session and
     * retrieves cart details such as the number
     * of items, total value, content IDs,
     * and contents of the cart. The event data
     * is then returned for tracking purposes.
     *
     * @return array The event data including
     * user PII, cart details, and currency information.
     *
     * @since 1.0.0
     */
    public static function createInitiateCheckoutEvent() {
        $event_data                 = self::getPIIFromSession();
        $event_data['content_type'] = 'product';
        $event_data['currency']     = \get_woocommerce_currency();

        if ( WC()->cart ) {
            $cart = WC()->cart;

            $event_data['num_items']   = $cart->get_cart_contents_count();
            $event_data['value']       = $cart->total;
            $event_data['content_ids'] = self::getContentIds( $cart );
            $event_data['contents']    = self::getContents( $cart );
        }

        return $event_data;
    }

    /**
     * Retrieves personally identifiable
     * information (PII) from a WooCommerce order.
     *
     * This method extracts billing details
     * from the given WooCommerce order object,
     * including the first name, last name,
     * email, postal code, state, country, city,
     * and phone number. The PII is
     * returned as an associative array.
     *
     * @param WC_Order $order The WooCommerce
     * order object containing billing information.
     *
     * @return array An associative array containing the extracted PII.
     *
     * @since 1.0.0
     */
    private static function getPiiFromBillingInformation( $order ) {
        $pii = array();

        $pii['first_name'] = $order->get_billing_first_name();
        $pii['last_name']  = $order->get_billing_last_name();
        $pii['email']      = $order->get_billing_email();
        $pii['zip']        = $order->get_billing_postcode();
        $pii['state']      = $order->get_billing_state();
        $pii['country']    = $order->get_billing_country();
        $pii['city']       = $order->get_billing_city();
        $pii['phone']      = $order->get_billing_phone();

        return $pii;
    }

    /**
     * Calculates the total value for adding a specified
     * quantity of a cart item to the cart.
     *
     * This method computes the total cost based on the
     * line total and quantity of the provided
     * cart item, and multiplies it by the specified quantity.
     *
     * @param array $cart_item An associative array
     *                          representing the cart item, containing
     *                         'line_total' and 'quantity' keys among others.
     * @param int   $quantity  The quantity of
     *              the item to calculate the total value for.
     *
     * @return float|null The calculated total value for
     *                    the specified quantity of the item,
     *                    or null if the cart item is empty.
     */
    private static function getAddToCartValue( $cart_item, $quantity ) {
        if ( ! empty( $cart_item ) ) {
            $price = $cart_item['line_total'] / $cart_item['quantity'];
            return $quantity * $price;
        }

        return null;
    }

    /**
     * Retrieves a cart item from the WooCommerce cart by its key.
     *
     * This method accesses the current WooCommerce cart and returns the
     * cart item associated with the provided cart item key. If the cart
     * or the specified cart item is not found, the method returns null.
     *
     * @param string $cart_item_key The key for identifying the cart item.
     *
     * @return array|null An associative array representing the cart item,
     *                    or null if the cart item is not found.
     */
    private static function getCartItem( $cart_item_key ) {
        if ( WC()->cart ) {
            $cart = WC()->cart->get_cart();
            if ( ! empty( $cart ) && ! empty( $cart[ $cart_item_key ] ) ) {
            return $cart[ $cart_item_key ];
            }
        }

        return null;
    }

    /**
     * Retrieves an array of product IDs from the given WooCommerce cart object.
     *
     * @param WC_Cart $cart The WooCommerce cart object.
     *
     * @return array An array of product IDs.
     *
     * @since 1.0.0
     */
    private static function getContentIds( $cart ) {
        $product_ids = array();
        foreach ( $cart->get_cart() as $item ) {
            if ( ! empty( $item['data'] ) ) {
            $product_ids[] = self::getProductId( $item['data'] );
            }
        }

        return $product_ids;
    }

    /**
     * Retrieves an array of Content objects from
     * the given WooCommerce cart object.
     *
     * Each Content object represents an item in the cart and includes
     * the product ID, quantity, and item price.
     *
     * @param WC_Cart $cart The WooCommerce cart object.
     *
     * @return Content[] An array of Content
     * objects representing the items in the cart.
     *
     * @since 1.0.0
     */
    private static function getContents( $cart ) {
        $contents = array();
        foreach ( $cart->get_cart() as $item ) {
            if ( ! empty( $item['data'] ) && ! empty( $item['quantity'] ) ) {
            $content = new Content();
            $content->setProductId( self::getProductId( $item['data'] ) );
            $content->setQuantity( $item['quantity'] );
            $content->setItemPrice( $item['line_total'] / $item['quantity'] );

            $contents[] = $content;
            }
        }

        return $contents;
    }

    /**
     * Retrieves a unique product ID from the given WooCommerce product object.
     *
     * If the product has a SKU, the ID is in the format of "sku_woo_id".
     * Otherwise, the ID is in the format of
     * "fb_woo_id" where "fb_" is a prefix.
     *
     * @param WC_Product $product The WooCommerce product object.
     *
     * @return string The unique product ID.
     *
     * @since 1.0.0
     */
    private static function getProductId( $product ) {
        $woo_id = $product->get_id();

        return $product->get_sku() ? $product->get_sku() . '_' .
        $woo_id : self::FB_ID_PREFIX . $woo_id;
    }

    /**
     * Retrieves PII from the logged in user's session.
     *
     * @return array The user's PII data.
     *
     * @since 1.0.0
     */
    private static function getPIIFromSession() {
        $event_data = FacebookPluginUtils::get_logged_in_user_info();
        $user_id    = get_current_user_id();
        if ( 0 !== $user_id ) {
            $event_data['city']    = get_user_meta(
                $user_id,
                'billing_city',
                true
            );
            $event_data['zip']     = get_user_meta(
                $user_id,
                'billing_postcode',
                true
            );
            $event_data['country'] = get_user_meta(
                $user_id,
                'billing_country',
                true
            );
            $event_data['state']   = get_user_meta(
                $user_id,
                'billing_state',
                true
            );
            $event_data['phone']   = get_user_meta(
                $user_id,
                'billing_phone',
                true
            );
        }
        return array_filter( $event_data );
    }

    /**
     * Checks if Facebook for WooCommerce plugin is active.
     *
     * @return bool True if Facebook for WooCommerce is active, false otherwise.
     *
     * @since 1.0.0
     */
    private static function isFacebookForWooCommerceActive() {
        return in_array(
            'facebook-for-woocommerce/facebook-for-woocommerce.php',
            get_option( 'active_plugins' ),
            true
        );
    }
}
