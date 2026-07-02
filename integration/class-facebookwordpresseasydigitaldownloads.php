<?php
/**
 * Facebook Pixel Plugin FacebookWordpressEasyDigitalDownloads class.
 *
 * This file contains the main logic for FacebookWordpressEasyDigitalDownloads.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressEasyDigitalDownloads class.
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
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Core\EventIdGenerator;
use FacebookPixelPlugin\Core\FacebookSignalState;
use FacebookPixelPlugin\Core\FooterDelivery;

/**
 * FacebookWordpressEasyDigitalDownloads class.
 */
class FacebookWordpressEasyDigitalDownloads extends FacebookWordpressIntegrationBase {
    const PLUGIN_FILE   = 'easy-digital-downloads/easy-digital-downloads.php';
    const TRACKING_NAME = 'easy-digital-downloads';

    /**
     * Registers the Easy Digital Downloads events through Signals.
     *
     * - AddToCart: a hidden shared event id is printed onto the purchase form
     *   and a JS listener fires the browser pixel; the AJAX add-to-cart request
     *   dispatches the server event only (no browser delivery — the enqueued
     *   listener owns the browser side), reusing the shared id for dedup.
     * - InitiateCheckout: dispatched when the checkout cart renders.
     * - Purchase: dispatched from the payment receipt.
     * - ViewContent: dispatched on the download page.
     *
     * @return void
     */
    public function inject_pixel_code() {
        add_action(
            'edd_after_download_content',
            array( $this, 'inject_add_to_cart_listener' )
        );
        add_action(
            'edd_downloads_list_after',
            array( $this, 'inject_add_to_cart_listener' )
        );
        add_action(
            'edd_purchase_link_top',
            array( $this, 'inject_add_to_cart_event_id' )
        );

        $this->signals->on(
            'wp_ajax_edd_add_to_cart',
            'AddToCart',
            array( $this, 'read_add_to_cart' ),
            null,
            self::TRACKING_NAME,
            5,
            0
        );
        $this->signals->on(
            'wp_ajax_nopriv_edd_add_to_cart',
            'AddToCart',
            array( $this, 'read_add_to_cart' ),
            null,
            self::TRACKING_NAME,
            5,
            0
        );

        $this->signals->on(
            'edd_after_checkout_cart',
            'InitiateCheckout',
            array( $this, 'read_initiate_checkout' ),
            new FooterDelivery(),
            self::TRACKING_NAME,
            10,
            0
        );

        $this->signals->on(
            'edd_payment_receipt_after',
            'Purchase',
            array( $this, 'read_purchase' ),
            new FooterDelivery(),
            self::TRACKING_NAME,
            10,
            2
        );

        $this->signals->on(
            'edd_after_download_content',
            'ViewContent',
            array( $this, 'read_view_content' ),
            new FooterDelivery(),
            self::TRACKING_NAME,
            40,
            1
        );
    }

    /**
     * Injects a hidden field with a unique event ID into the AddToCart form.
     *
     * The shared event ID lets the browser AddToCart pixel (fired by the JS
     * listener) deduplicate against the server event dispatched on the AJAX
     * add-to-cart request.
     *
     * @return void
     */
    public function inject_add_to_cart_event_id() {
        if ( FacebookPluginUtils::is_internal_user() ) {
            return;
        }
        $event_id = EventIdGenerator::guidv4();
        printf(
            '<input type="hidden" name="facebook_event_id" value="%s">',
            esc_attr( $event_id )
        );
    }

    /**
     * Extracts the AddToCart event data from the AJAX add-to-cart request.
     *
     * Verifies the EDD add-to-cart nonce and reuses the shared event id carried
     * in the posted form data so the server event deduplicates against the
     * browser event fired by the JS listener.
     *
     * @return \FacebookPixelPlugin\Core\EventData|null
     */
    public function read_add_to_cart() {
        if ( ! isset( $_POST['nonce'], $_POST['download_id'], $_POST['post_data'] ) ) {
            return null;
        }

        $download_id = absint( wp_unslash( $_POST['download_id'] ) );
        $nonce       = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
        if ( false === wp_verify_nonce( $nonce, 'edd-add-to-cart-' . $download_id ) ) {
            return null;
        }

        parse_str( $_POST['post_data'], $post_data ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $event_id = isset( $post_data['facebook_event_id'] )
            ? sanitize_text_field( $post_data['facebook_event_id'] )
            : null;

        return $this->event_builder->build(
            self::createAddToCartEvent( $download_id ),
            $event_id
        );
    }

    /**
     * Injects a JavaScript listener for the AddToCart event
     * for Easy Digital Downloads.
     *
     * This method enqueues a JavaScript file that listens for
     * the `edd_add_to_cart` event and fires the browser AddToCart pixel.
     *
     * @param int $download_id The ID of the download item.
     *
     * @return void
     */
    public function inject_add_to_cart_listener( $download_id ) {
        if ( FacebookPluginUtils::is_internal_user() ) {
            return;
        }

        wp_register_script(
            'facebook-pixel-add-to-cart',
            plugins_url( '../js/facebook_pixel_add_to_cart.js', __FILE__ ),
            array( 'jquery' ),
            '1.0.0',
            false
        );

        wp_localize_script(
            'facebook-pixel-add-to-cart',
            'facebookPixelData',
            array(
                'fbIntegrationKey' => FacebookPixel::FB_INTEGRATION_TRACKING_KEY,
                'trackingName'     => self::TRACKING_NAME,
                'agentString'      => FacebookWordpressOptions::get_agent_string(),
                'pixelId'          => FacebookWordpressOptions::get_active_pixel_id(),
                'held'             => FacebookSignalState::is_held(),
            )
        );

        wp_enqueue_script( 'facebook-pixel-add-to-cart' );
    }

    /**
     * Extracts the InitiateCheckout event data when the checkout cart renders.
     *
     * @return \FacebookPixelPlugin\Core\EventData|null
     */
    public function read_initiate_checkout() {
        if ( ! function_exists( 'EDD' ) ) {
            return null;
        }

        return $this->event_builder->build(
            self::createInitiateCheckoutEvent()
        );
    }

    /**
     * Extracts the Purchase event data from the payment receipt.
     *
     * @param object $payment          The payment object.
     * @param array  $edd_receipt_args The receipt arguments (unused).
     * @return \FacebookPixelPlugin\Core\EventData|null
     */
    public function read_purchase( $payment, $edd_receipt_args = array() ) {
        if ( empty( $payment->ID ) ) {
            return null;
        }

        return $this->event_builder->build(
            self::createPurchaseEvent( $payment )
        );
    }

    /**
     * Extracts the ViewContent event data for a viewed download.
     *
     * @param int $download_id The ID of the download item.
     * @return \FacebookPixelPlugin\Core\EventData|null
     */
    public function read_view_content( $download_id = 0 ) {
        if ( empty( $download_id ) ) {
            return null;
        }

        return $this->event_builder->build(
            self::createViewContentEvent( $download_id )
        );
    }

    /**
     * Creates a Meta Pixel InitiateCheckout event data.
     *
     * The InitiateCheckout event is fired when a customer initiates a checkout.
     * It is typically sent when a customer clicks a "checkout" button
     * or submits an order.
     *
     * @return array The event data.
     *
     * @since 1.0.0
     */
    public static function createInitiateCheckoutEvent() {
        $event_data             =
        FacebookPluginUtils::get_logged_in_user_info();
        $event_data['currency'] = EDDUtils::get_currency();
        $event_data['value']    = EDDUtils::get_cart_total();

        return $event_data;
    }

    /**
     * Creates a Meta Pixel Purchase event data.
     *
     * The Purchase event is fired when a customer completes a purchase.
     * It is typically sent when a customer submits an order.
     *
     * @param \EDD_Payment $payment The payment object.
     *
     * @return array The event data.
     *
     * @since 1.0.0
     */
    public static function createPurchaseEvent( $payment ) {
        $event_data = array();

        $payment_meta = \edd_get_payment_meta( $payment->ID );
        if ( empty( $payment_meta ) ) {
            return $event_data;
        }

        $event_data['email']      = $payment_meta['email'];
        $event_data['first_name'] = $payment_meta['user_info']['first_name'];
        $event_data['last_name']  = $payment_meta['user_info']['last_name'];

        $content_ids = array();
        $value       = 0;
        foreach ( $payment_meta['cart_details'] as $item ) {
            $content_ids[] = $item['id'];
            $value        += $item['price'];
        }

        $event_data['currency']     = $payment_meta['currency'];
        $event_data['value']        = $value;
        $event_data['content_ids']  = $content_ids;
        $event_data['content_type'] = 'product';

        return $event_data;
    }

    /**
     * Creates a Meta Pixel ViewContent event data.
     *
     * The ViewContent event is fired when a customer views a product.
     * It is typically sent when a customer views a product page.
     *
     * @param int $download_id The download ID.
     *
     * @return array The event data.
     *
     * @since 1.0.0
     */
    public static function createViewContentEvent( $download_id ) {
        $event_data = FacebookPluginUtils::get_logged_in_user_info();
        $currency   = EDDUtils::get_currency();
        $download   = edd_get_download( $download_id );
        $title      = $download ? $download->post_title : '';

        if ( get_post_meta( $download_id, '_variable_pricing', true ) ) {
            $prices = get_post_meta( $download_id, 'edd_variable_prices', true );
            $price  = array_shift( $prices );
            $value  = $price['amount'];
        } else {
            $value = get_post_meta( $download_id, 'edd_price', true );
        }
        if ( ! $value ) {
            $value = 0;
        }
        $event_data['content_ids']  = array( (string) $download_id );
        $event_data['content_type'] = 'product';
        $event_data['currency']     = $currency;
        $event_data['value']        = floatval( $value );
        $event_data['content_name'] = $title;
        return $event_data;
    }

    /**
     * Creates a Meta Pixel AddToCart event data.
     *
     * The AddToCart event is fired when a customer adds a product to their
     * cart. It is typically sent when a customer adds a product to their
     * cart.
     *
     * @param int $download_id The download ID.
     *
     * @return array The event data.
     *
     * @since 1.0.0
     */
    public static function createAddToCartEvent( $download_id ) {
        $event_data = FacebookPluginUtils::get_logged_in_user_info();
        $currency   = EDDUtils::get_currency();
        $download   = edd_get_download( $download_id );
        $title      = $download ? $download->post_title : '';
        if ( get_post_meta( $download_id, '_variable_pricing', true ) ) {
            $prices = get_post_meta( $download_id, 'edd_variable_prices', true );
            $price  = array_shift( $prices );
            $value  = $price['amount'];
        } else {
            $value = get_post_meta( $download_id, 'edd_price', true );
        }
        if ( ! $value ) {
            $value = 0;
        }
        $event_data['content_ids']  = array( (string) $download_id );
        $event_data['content_type'] = 'product';
        $event_data['currency']     = $currency;
        $event_data['value']        = $value;
        $event_data['content_name'] = $title;
        return $event_data;
    }
}
