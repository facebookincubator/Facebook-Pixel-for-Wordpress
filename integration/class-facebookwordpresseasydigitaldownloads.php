<?php
/**
 * Facebook Pixel Plugin FacebookWordpressEasyDigitalDownloads class.
 *
 * This file contains the tracking integration for Easy Digital Downloads.
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

use FacebookPixelPlugin\Core\EventIdGenerator;
use FacebookPixelPlugin\Integration\Helpers\EasyDigitalDownloadsIntegrationHelper;
use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Utils\WordPressUtils;

/**
 * Tracking integration for the Easy Digital Downloads plugin.
 */
class FacebookWordpressEasyDigitalDownloads extends TrackableIntegrationBase {

    const PLUGIN_FILE      = 'easy-digital-downloads/easy-digital-downloads.php';
    const INTEGRATION_NAME = 'easy-digital-downloads';

    /**
     * Registers the WordPress action hooks used to track Easy Digital
     * Downloads events (add to cart, view content, initiate checkout and
     * purchase). Internal users are excluded from tracking.
     *
     * @return void
     */
    protected function set_up_tracking() {
        if ( FacebookPluginUtils::is_internal_user() ) {
            return;
        }
        add_action(
            'edd_after_download_content',
            array( $this, 'inject_add_to_cart_listener' )
        );
        add_action(
            'edd_after_download_content',
            array( $this, 'track_view_content_event' ),
            40
        );

        add_action(
            'edd_downloads_list_after',
            array( $this, 'inject_add_to_cart_listener' )
        );
        add_action(
            'wp_ajax_edd_add_to_cart',
            array( $this, 'inject_ajax_add_to_cart_listener' ),
            5
        );
        add_action(
            'wp_ajax_nopriv_edd_add_to_cart',
            array( $this, 'inject_ajax_add_to_cart_listener' ),
            5
        );

        add_action(
            'edd_purchase_link_top',
            array( $this, 'inject_add_to_cart_event_id' )
        );

        add_action(
            'edd_after_checkout_cart',
            array( $this, 'track_initiate_checkout_event' ),
            11
        );

        add_action(
            'edd_payment_receipt_after',
            array( $this, 'track_purchase_event' ),
            10,
            2
        );
    }

    /**
     * Generates and sends an InitiateCheckout event through both the
     * Conversions API and the Pixel when Easy Digital Downloads is available.
     *
     * @return void
     */
    public function track_initiate_checkout_event() {
        if ( ! function_exists( 'EDD' ) ) {
            return;
        }

        $event = $this->generate_event( 'InitiateCheckout', $this->get_initiate_checkout_event_data(), false );
        $this->deliver( $event, self::BROWSER_INLINE );
    }

    /**
     * Generates and sends a ViewContent event for the given download through
     * both the Conversions API and the Pixel.
     *
     * @param int $download_id The Easy Digital Downloads download ID.
     * @return void
     */
    public function track_view_content_event( $download_id ) {
        if ( empty( $download_id ) ) {
            return;
        }

        $event = $this->generate_event( 'ViewContent', $this->get_view_content_event_data( $download_id ), false );
        $this->deliver( $event, self::BROWSER_INLINE );
    }

    /**
     * Generates and sends a Purchase event for a completed payment through
     * both the Conversions API and the Pixel.
     *
     * @param object $payment             The Easy Digital Downloads payment object.
     * @param mixed  $edd_payment_receipt The payment receipt data passed by the hook.
     * @return void
     */
    public function track_purchase_event( $payment, $edd_payment_receipt ) {
        if ( empty( $payment->ID ) ) {
            return;
        }

        $event = $this->generate_event( 'Purchase', $this->get_purchase_event_data( $payment ), false );
        $this->deliver( $event, self::BROWSER_INLINE );
    }

    /**
     * Builds the event data array for a Purchase event from a payment's meta,
     * including customer PII, currency, value, content IDs and content type.
     *
     * @param object $payment The Easy Digital Downloads payment object.
     * @return array The Purchase event data.
     */
    private function get_purchase_event_data( $payment ) {
        $event_data = array();

        $payment_meta = EasyDigitalDownloadsIntegrationHelper::get_payment_meta( $payment->ID );
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
     * Registers, localizes and enqueues the JavaScript that listens for add to
     * cart interactions and forwards the required Pixel configuration.
     *
     * @return void
     */
    public function inject_add_to_cart_listener() {
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
                'fbIntegrationKey' => $this->signals::FB_INTEGRATION_TRACKING_KEY,
                'trackingName'     => self::INTEGRATION_NAME,
                'agentString'      => $this->signals->agent_string,
                'pixelId'          => $this->signals->pixel_id,
                'held'             => $this->signals->is_held,
            )
        );

        wp_enqueue_script( 'facebook-pixel-add-to-cart' );
    }

    /**
     * Outputs a hidden input field containing a freshly generated event ID so
     * the add to cart event can be deduplicated between the Pixel and the
     * Conversions API.
     *
     * @return void
     */
    public function inject_add_to_cart_event_id() {
        $event_id = EventIdGenerator::guidv4();
        printf(
            '<input type="hidden" name="facebook_event_id" value="%s">',
            esc_attr( $event_id )
        );
    }

    /**
     * Handles the AJAX add to cart request, verifies the nonce and, when a
     * Facebook event ID is present, sends a deduplicated AddToCart event
     * through the Conversions API.
     *
     * @return void
     */
    public function inject_ajax_add_to_cart_listener() {
        $download_id = WordPressUtils::get_from_post( 'download_id' );
        $nonce       = WordPressUtils::get_from_post( 'nonce' );
        $data        = WordPressUtils::get_from_post( 'post_data' );
        if ( empty( $download_id ) || empty( $nonce ) || empty( $data ) ) {
            return;
        }

        $download_id = absint( $download_id );
        if ( false === wp_verify_nonce( $nonce, 'edd-add-to-cart-' . $download_id ) ) {
            return;
        }

        parse_str( $data, $post_data ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        if ( ! isset( $post_data['facebook_event_id'] ) ) {
            return;
        }

        // Server-only: the browser AddToCart pixel is fired client-side by
        // facebook_pixel_add_to_cart.js, deduplicated against this via event id.
        $event = $this->generate_event( 'AddToCart', $this->get_add_to_cart_event_data( $download_id ), false );
        $event->setEventId( $post_data['facebook_event_id'] );
        $this->deliver( $event, self::BROWSER_NONE );
    }

    /**
     * Builds the event data array for an InitiateCheckout event, including user
     * information, the cart currency and the cart total.
     *
     * @return array The InitiateCheckout event data.
     */
    public function get_initiate_checkout_event_data() {
        $event_data             = WordPressUtils::get_user_info();
        $event_data['currency'] = EasyDigitalDownloadsIntegrationHelper::get_currency();
        $event_data['value']    = EasyDigitalDownloadsIntegrationHelper::get_cart_total();

        return $event_data;
    }

    /**
     * Builds the event data array for a ViewContent event for the given
     * download, including user information, currency, price value, content IDs,
     * content type and content name.
     *
     * @param int $download_id The Easy Digital Downloads download ID.
     * @return array The ViewContent event data.
     */
    public function get_view_content_event_data( $download_id ) {
        $event_data = WordPressUtils::get_user_info();
        $currency   = EasyDigitalDownloadsIntegrationHelper::get_currency();
        $download   = EasyDigitalDownloadsIntegrationHelper::get_download( $download_id );
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
     * Builds the event data array for an AddToCart event for the given
     * download, including user information, currency, price value, content IDs,
     * content type and content name.
     *
     * @param int $download_id The Easy Digital Downloads download ID.
     * @return array The AddToCart event data.
     */
    public function get_add_to_cart_event_data( $download_id ) {
        $event_data = WordPressUtils::get_user_info();
        $currency   = EasyDigitalDownloadsIntegrationHelper::get_currency();
        $download   = EasyDigitalDownloadsIntegrationHelper::get_download( $download_id );
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
