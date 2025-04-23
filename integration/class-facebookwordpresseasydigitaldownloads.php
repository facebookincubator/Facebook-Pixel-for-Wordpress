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
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\PixelRenderer;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Core\EventIdGenerator;

/**
 * FacebookWordpressEasyDigitalDownloads class.
 */
class FacebookWordpressEasyDigitalDownloads extends FacebookWordpressIntegrationBase {
    const PLUGIN_FILE   = 'easy-digital-downloads/easy-digital-downloads.php';
    const TRACKING_NAME = 'easy-digital-downloads';

    /**
     * Injects various Facebook Pixel events for Easy Digital Downloads.
     *
     * This method sets up WordPress actions to inject Facebook Pixel events
     * for different stages of the Easy Digital Downloads process:
     *
     * - AddToCart: Adds JavaScript listeners and hooks for AJAX requests,
     *   and injects a hidden field with an event ID.
     * - InitiateCheckout: Fires a pixel event after the checkout cart is
     *   displayed.
     * - Purchase: Tracks purchase events after the payment receipt.
     * - ViewContent: Injects view content events after download content.
     */
    public static function inject_pixel_code() {
        add_action(
            'edd_after_download_content',
            array( __CLASS__, 'injectAddToCartListener' )
        );
        add_action(
            'edd_downloads_list_after',
            array( __CLASS__, 'injectAddToCartListener' )
        );

        add_action(
            'wp_ajax_edd_add_to_cart',
            array( __CLASS__, 'injectAddToCartEventAjax' ),
            5
        );

        add_action(
            'wp_ajax_nopriv_edd_add_to_cart',
            array( __CLASS__, 'injectAddToCartEventAjax' ),
            5
        );

        add_action(
            'edd_purchase_link_top',
            array( __CLASS__, 'injectAddToCartEventId' )
        );

        self::add_pixel_fire_for_hook(
            array(
                'hook_name'       => 'edd_after_checkout_cart',
                'classname'       => __CLASS__,
                'inject_function' => 'injectInitiateCheckoutEvent',
            )
        );

        add_action(
            'edd_payment_receipt_after',
            array( __CLASS__, 'trackPurchaseEvent' ),
            10,
            2
        );

        add_action(
            'edd_after_download_content',
            array( __CLASS__, 'injectViewContentEvent' ),
            40,
            1
        );
    }

    /**
     * Injects a hidden field with a unique event ID into the AddToCart form.
     *
     * The event ID is used to identify the AddToCart event
     * for a given download.
     *
     * @return void
     */
    public static function injectAddToCartEventId() {
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
     * Triggers the AddToCart event for Easy Digital Downloads.
     *
     * The `edd-add-to-cart` nonce check is performed to ensure that the request
     * comes from a valid EDD form submission. The event
     * ID is verified to ensure that
     * it is a valid Event ID.
     *
     * @since 1.0.0
     */
    public static function injectAddToCartEventAjax() {
        if ( isset( $_POST['nonce'] ) && isset( $_POST['download_id'] )
            && isset( $_POST['post_data'] ) ) {
            $download_id = absint( $_POST['download_id'] );
            $nonce       = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
            if ( wp_verify_nonce( $nonce, 'edd-add-to-cart-' . $download_id )
            === false ) {
                return;
            }
            parse_str( $_POST['post_data'], $post_data ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
            if ( isset( $post_data['facebook_event_id'] ) ) {
                $event_id = $post_data['facebook_event_id'];
            $server_event = ServerEventFactory::safe_create_event(
                'AddToCart',
                array( __CLASS__, 'createAddToCartEvent' ),
                array( $download_id ),
                self::TRACKING_NAME
            );
                $server_event->setEventId( $event_id );
                FacebookServerSideEvent::get_instance()->track( $server_event );
            }
        }
        parse_str( $_POST['post_data'], $post_data ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        if ( isset( $post_data['facebook_event_id'] ) ) {
            $event_id     = $post_data['facebook_event_id'];
            $server_event = ServerEventFactory::safe_create_event(
                'AddToCart',
                array( __CLASS__, 'createAddToCartEvent' ),
                array( $download_id ),
                self::TRACKING_NAME
            );
            $server_event->setEventId( $event_id );
            FacebookServerSideEvent::get_instance()->track( $server_event );
        }
    }

    /**
     * Injects a JavaScript listener for the AddToCart event
     * for Easy Digital Downloads.
     *
     * This method enqueues a JavaScript file that listens for
     * the `edd_add_to_cart`
     * event, and sends a server-side event to Facebook
     * for the AddToCart pixel event.
     *
     * @param int $download_id The ID of the download item.
     *
     * @since 1.0.0
     */
    public static function injectAddToCartListener( $download_id ) {
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
                'pixelId'          => FacebookWordpressOptions::get_pixel_id(),
            )
        );

        wp_enqueue_script( 'facebook-pixel-add-to-cart' );
    }

    /**
     * Injects a Meta Pixel InitiateCheckout event.
     *
     * This method is a callback for the `edd_purchase_link_top` action hook.
     * It injects a Meta Pixel InitiateCheckout event into
     * the page whenever a purchase link is rendered.
     *
     * @since 1.0.0
     */
    public static function injectInitiateCheckoutEvent() {
        if ( FacebookPluginUtils::is_internal_user() ||
        ! function_exists( 'EDD' ) ) {
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
            array( $server_event ),
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
     * Tracks a Meta Pixel Purchase event.
     *
     * This method is a callback for the `edd_complete_purchase` action hook.
     * It tracks a Meta Pixel Purchase event whenever a purchase is completed.
     *
     * @param object $payment The payment object.
     * @param array  $edd_receipt_args The receipt arguments.
     *
     * @since 1.0.0
     */
    public static function trackPurchaseEvent( $payment, $edd_receipt_args ) {
        if ( FacebookPluginUtils::is_internal_user() || empty( $payment->ID ) ) {
            return;
        }

        $server_event = ServerEventFactory::safe_create_event(
            'Purchase',
            array( __CLASS__, 'createPurchaseEvent' ),
            array( $payment ),
            self::TRACKING_NAME
        );
            FacebookServerSideEvent::get_instance()->track( $server_event );

        add_action(
            'wp_footer',
            array( __CLASS__, 'injectPurchaseEvent' ),
            20
        );
    }

    /**
     * Injects a Meta Pixel Purchase event.
     *
     * This method is a callback for the `wp_footer` action hook.
     * It injects a Meta Pixel Purchase event
     * into the page whenever a purchase is completed.
     *
     * @since 1.0.0
     */
    public static function injectPurchaseEvent() {
        $events = FacebookServerSideEvent::get_instance()->get_tracked_events();
        $code   = PixelRenderer::render( $events, self::TRACKING_NAME );

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
     * Injects a Meta Pixel ViewContent event.
     *
     * This method is a callback for the
     * `edd_download_before_content` action hook.
     * It injects a Meta Pixel ViewContent event
     * into the page whenever a download
     * item is viewed.
     *
     * @param int $download_id The ID of the download item.
     *
     * @since 1.0.0
     */
    public static function injectViewContentEvent( $download_id ) {
        if ( FacebookPluginUtils::is_internal_user() || empty( $download_id ) ) {
            return;
        }

        $server_event = ServerEventFactory::safe_create_event(
            'ViewContent',
            array( __CLASS__, 'createViewContentEvent' ),
            array( $download_id ),
            self::TRACKING_NAME
        );

        FacebookServerSideEvent::get_instance()->track( $server_event );

        $code = PixelRenderer::render( array( $server_event ), self::TRACKING_NAME );
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
