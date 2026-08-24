<?php
/**
 * Facebook Pixel Plugin FacebookWordpressWooCommerce class.
 *
 * This file contains the tracking integration for WooCommerce.
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

namespace FacebookPixelPlugin\Integration;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Utils\WordPressUtils;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Content;
use FacebookPixelPlugin\Integration\Helpers\WooCommerceIntegrationHelper;
use Override;

/**
 * Tracking integration for the WooCommerce plugin.
 */
class FacebookWordpressWooCommerce extends TrackableIntegrationBase {

    const PLUGIN_FILE      = 'woocommerce/woocommerce.php';
    const INTEGRATION_NAME = 'woocommerce';

    const FB_ID_PREFIX         = 'wc_post_id_';
    const AJAX_PIXEL_CONTAINER = 'fb-pxl-ajax-code';
    /**
     * Registers the WordPress action hooks used to track WooCommerce events
     * (initiate checkout, add to cart, purchase and view content). Tracking is
     * skipped for internal users and when Facebook for WooCommerce is active.
     *
     * @return void
     */
    protected function set_up_tracking() {
        if ( FacebookPluginUtils::is_internal_user() ) {
            return;
        }

        if ( self::is_facebook_for_woocommerce_active() ) {
            return;
        }

        $this->register_ajax_dom_element( sprintf( "<div id='%s'></div>", esc_attr( self::AJAX_PIXEL_CONTAINER ) ) );

        // InitiateCheckout events for classic checkout.
        add_action(
            'woocommerce_after_checkout_form',
            array( $this, 'track_initiate_checkout_event' ),
            40
        );
        // InitiateCheckout events for block-based checkout.
        add_action(
            'woocommerce_blocks_checkout_enqueue_data',
            array( $this, 'track_initiate_checkout_event' )
        );

        add_action(
            'woocommerce_add_to_cart',
            array( $this, 'track_add_to_cart_event' ),
            40,
            4
        );

        add_action(
            'woocommerce_thankyou',
            array( $this, 'track_purchase_event' ),
            40
        );

        add_action(
            'woocommerce_payment_complete',
            array( $this, 'track_purchase_event' ),
            40
        );

        add_action(
            'woocommerce_after_single_product',
            array( $this, 'track_view_content_event' ),
            40
        );
    }

    /**
     * Generates and sends a ViewContent event for the current product page
     * through both the Conversions API and the Pixel.
     *
     * @return void
     */
    public function track_view_content_event() {
        global $post;
        if ( ! isset( $post->ID ) ) {
            return;
        }

        $product = WooCommerceIntegrationHelper::get_product( $post->ID );
        if ( ! $product ) {
            return;
        }

        // Page-render event: use the current (product page) URL, not the referrer.
        $event = $this->generate_event( 'ViewContent', $this->get_view_content_event_data( $product ), false );
        $this->deliver( $event, self::BROWSER_INLINE );
    }

    /**
     * Generates and sends a Purchase event for the given order through both the
     * Conversions API and the Pixel.
     *
     * @param int $order_id The WooCommerce order ID.
     * @return void
     */
    public function track_purchase_event( $order_id ) {
        // Page-render event: use the current (thank-you page) URL, not the referrer.
        $event = $this->generate_event( 'Purchase', $this->get_purchase_event_data( $order_id ), false );
        $this->deliver( $event, self::BROWSER_INLINE );
    }

    /**
     * Builds the event data array for a ViewContent event from a product,
     * including user information, content type, currency, value, content IDs,
     * content name, contents and content category.
     *
     * @param \WC_Product $product The WooCommerce product object.
     * @return array The filtered ViewContent event data.
     */
    private function get_view_content_event_data( $product ) {
        $event_data = WordPressUtils::get_user_info();

        $product_id   = self::get_product_id( $product );
        $content_type = $product->is_type( 'variable' ) ?
        'product_group' : 'product';

        $content = new Content();
        $content->setProductId( $product_id );
        $content->setQuantity( 1 );
        $content->setItemPrice( $product->get_price() );
        $content->setTitle( $product->get_title() );

        $event_data['content_type']     = $content_type;
        $event_data['currency']         = WooCommerceIntegrationHelper::get_currency();
        $event_data['value']            = $product->get_price();
        $event_data['content_name']     = $product->get_title();
        $event_data['content_ids']      = array( $product_id );
        $event_data['contents']         = array( $content );
        $event_data['content_category'] = self::getProductCategory( $product_id );

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
     * Generates and sends an AddToCart event for the added item. The event is
     * enqueued directly for AJAX requests, or injected into the cart fragments
     * otherwise, and is always sent through the Conversions API.
     *
     * @param string $cart_item_key The generated cart item key.
     * @param int    $product_id    The added product ID.
     * @param int    $quantity      The quantity added to the cart.
     * @param int    $variation_id  The variation ID, if applicable.
     * @return void
     */
    public function track_add_to_cart_event(
        $cart_item_key,
        $product_id,
        $quantity,
        $variation_id
    ) {
        $event = $this->generate_event(
            'AddToCart',
            $this->get_add_to_cart_event_data( $cart_item_key, $product_id, $quantity, $variation_id )
        );
        // woocommerce_add_to_cart fires for both AJAX and non-AJAX add-to-cart:
        // AJAX delivers via the cart-fragment channel, non-AJAX via the footer flush.
        $is_ajax = wp_doing_ajax();
        if ( $is_ajax ) {
            $this->deliver( $event, self::BROWSER_AJAX, self::SERVER_SYNC, array( $event ) );
        } else {
            $this->deliver( $event );
        }
    }

    /**
     * Injects the AddToCart pixel code into the WooCommerce cart fragments so it
     * is executed during the AJAX cart update.
     *
     * @param array $args Arguments from deliver(); $args[0] is the AddToCart event.
     * @throws \Exception When the request is not AJAX or no event is provided.
     * @return void
     */
    #[Override]
    protected function deliver_ajax_browser_event( $args ) {
        $is_ajax = wp_doing_ajax();
        if ( ! $is_ajax ) {
            throw new \Exception( 'This request is not AJAX.' );
        }
        if ( empty( $args ) ) {
            throw new \Exception( '$args cannot be empty.' );
        }
        $event        = $args[0];
        $pixel_code   = $this->generate_pixel_script_for_ajax( $event, true );
        $div_selector = self::AJAX_PIXEL_CONTAINER;
        add_filter(
            'woocommerce_add_to_cart_fragments',
            function ( $response ) use ( $pixel_code, $div_selector ) {
                return $this->add_js_tracking_code_to_cart_fragment( $response, $pixel_code, $div_selector );
            }
        );
    }

    /**
     * Adds the AddToCart pixel code to the WooCommerce cart fragments, wrapped in
     * the container div the footer listener swaps in. A non-array response or an
     * empty pixel code leaves the fragments untouched.
     *
     * @param mixed  $response     The cart fragments.
     * @param string $pixel_code   The pixel script to inject.
     * @param string $div_selector The container element id.
     * @return mixed The (possibly enriched) fragments.
     */
    public function add_js_tracking_code_to_cart_fragment( $response, $pixel_code, $div_selector ) {
        if ( is_array( $response ) && ! empty( $pixel_code ) ) {
            $response[ '#' . $div_selector ] = sprintf(
                "<div id='%s'>%s</div>",
                esc_attr( $div_selector ),
                $pixel_code
            );
        }
        return $response;
    }

    /**
     * Builds the event data array for a Purchase event from the given order,
     * including billing PII, content type, currency, value, content IDs and the
     * list of purchased contents.
     *
     * @param int $order_id The WooCommerce order ID.
     * @return array The Purchase event data.
     */
    private function get_purchase_event_data( $order_id ) {
        $order = WooCommerceIntegrationHelper::get_order( $order_id );

        $content_type = 'product';
        $product_ids  = array();
        $contents     = array();

        foreach ( $order->get_items() as $item ) {

            $product = WooCommerceIntegrationHelper::get_product( $item->get_product_id() );

            if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
                continue;
            }

            if ( 'product_group' !== $content_type && $product->is_type( 'variable' ) ) {
                $content_type = 'product_group';
            }

            $quantity   = $item->get_quantity();
            $product_id = self::get_product_id( $product );

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
        $event_data['currency']     = WooCommerceIntegrationHelper::get_currency();
        $event_data['value']        = $order->get_total();
        $event_data['content_ids']  = $product_ids;
        $event_data['contents']     = $contents;

        return $event_data;
    }

    /**
     * Builds the event data array for an AddToCart event, including user
     * information, currency, content type, content IDs and value derived from
     * the cart item.
     *
     * @param string $cart_item_key The generated cart item key.
     * @param int    $product_id    The added product ID.
     * @param int    $quantity      The quantity added to the cart.
     * @param int    $variation_id  The variation ID, if applicable.
     * @return array The AddToCart event data.
     */
    private function get_add_to_cart_event_data(
        $cart_item_key,
        $product_id,
        $quantity,
        $variation_id
    ) {
        $event_data                 = WordPressUtils::get_user_info();
        $event_data['currency']     = WooCommerceIntegrationHelper::get_currency();
        $event_data['content_type'] = 'product';

        $cart_item = empty( $cart_item_key ) ? null :
        WooCommerceIntegrationHelper::get_cart_item( $cart_item_key );

        if ( ! empty( $cart_item ) && ! empty( $cart_item['data'] ) ) {
            $event_data['content_ids'] = array(
                self::get_product_id( $cart_item['data'] ),
            );
            $event_data['value']       = $quantity * ( $cart_item['line_total'] / $cart_item['quantity'] );

            return $event_data;
        }

        // Fallback for integrations that call Woo add_to_cart() on a
        // temporary/private cart (e.g. subscription cloning flows).
        $product_lookup_id = ! empty( $variation_id ) ? $variation_id : $product_id;
        $product           = WooCommerceIntegrationHelper::get_product( $product_lookup_id );
        $product_fb_id     = self::get_product_id( $product );

        if ( ! empty( $product_fb_id ) ) {
            $event_data['content_ids'] = array( $product_fb_id );

            if ( method_exists( $product, 'get_price' ) ) {
                $event_data['value'] = (float) $quantity * (float) $product->get_price();
            }
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
     * @param \WC_Order $order The WooCommerce
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
     * Generates and sends an InitiateCheckout event through both the
     * Conversions API and the Pixel when the cart is not empty.
     *
     * @return void
     */
    public function track_initiate_checkout_event() {
        if ( null === WooCommerceIntegrationHelper::get_cart() || 0 === WooCommerceIntegrationHelper::get_cart()->get_cart_contents_count() ) {
            return;
        }

        // Page-render event: use the current (checkout page) URL, not the referrer.
        $event = $this->generate_event( 'InitiateCheckout', $this->get_initiate_checkout_event_data(), false );
        $this->deliver( $event, self::BROWSER_INLINE );
    }

    /**
     * Builds the event data array for an InitiateCheckout event, including user
     * information, content type, currency and, when a cart is present, the item
     * count, value, content IDs and contents.
     *
     * @return array The InitiateCheckout event data.
     */
    private function get_initiate_checkout_event_data() {
        $event_data                 = WordPressUtils::get_user_info();
        $event_data['content_type'] = 'product';
        $event_data['currency']     = WooCommerceIntegrationHelper::get_currency();

        $cart = WooCommerceIntegrationHelper::get_cart();
        if ( $cart ) {

            $event_data['num_items']   = $cart->get_cart_contents_count();
            $event_data['value']       = $cart->total;
            $event_data['content_ids'] = self::get_content_ids( $cart );
            $event_data['contents']    = self::get_contents( $cart );
        }

        return $event_data;
    }

    /**
     * This integration should not track, if Meta for WooCommerce is also installed and active
     *
     * @return bool True if Facebook for WooCommerce is active, false otherwise.
     *
     * @since 1.0.0
     */
    private static function is_facebook_for_woocommerce_active() {
        $is_active = in_array(
            'facebook-for-woocommerce/facebook-for-woocommerce.php',
            get_option( 'active_plugins' ),
            true
        );

        return $is_active;
        // TODO: What to do about ajax calls?
        // TODO: Do we need to check if fb-for-woo is actually connected, or just check if active?
        // if ( ! $is_active ) {
        // return false;
        // }
        // if ( ! function_exists( 'facebook_for_woocommerce' ) ) {
        // We'll need to activate it?
        // } else {
        // return facebook_for_woocommerce()->get_connection_handler()->is_connected();.
    }

    /**
     * Builds a list of Content objects from the items in the given cart, each
     * with its product ID, quantity and per-item price.
     *
     * @param \WC_Cart $cart The WooCommerce cart object.
     * @return Content[] The list of cart contents.
     */
    private static function get_contents( $cart ) {
        $contents = array();
        foreach ( $cart->get_cart() as $item ) {
            if ( ! empty( $item['data'] ) && ! empty( $item['quantity'] ) ) {
            $content = new Content();
            $content->setProductId( self::get_product_id( $item['data'] ) );
            $content->setQuantity( $item['quantity'] );
            $content->setItemPrice( $item['line_total'] / $item['quantity'] );

            $contents[] = $content;
            }
        }

        return $contents;
    }

    /**
     * Builds a list of unique product IDs from the items in the given cart.
     *
     * @param \WC_Cart $cart The WooCommerce cart object.
     * @return array The list of product IDs.
     */
    private static function get_content_ids( $cart ) {
        $product_ids = array();
        foreach ( $cart->get_cart() as $item ) {
            if ( ! empty( $item['data'] ) ) {
            $product_ids[] = self::get_product_id( $item['data'] );
            }
        }

        return $product_ids;
    }

    /**
     * Retrieves a unique product ID from the given WooCommerce product object.
     *
     * If the product has a SKU, the ID is in the format of "sku_woo_id".
     * Otherwise, the ID is in the format of
     * "fb_woo_id" where "fb_" is a prefix.
     *
     * @param \WC_Product $product The WooCommerce product object.
     *
     * @return string|null The unique product ID, or null if the product is invalid.
     *
     * @since 1.0.0
     */
    private static function get_product_id( $product ) {
        if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
            return null;
        }

        $woo_id = $product->get_id();

        return $product->get_sku() ? $product->get_sku() . '_' .
        $woo_id : self::FB_ID_PREFIX . $woo_id;
    }
}
