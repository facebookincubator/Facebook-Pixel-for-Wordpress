<?php
/**
 * Facebook Pixel Plugin FacebookWordpressMailchimpForWp class.
 *
 * This file contains the main logic for FacebookWordpressMailchimpForWp.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressMailchimpForWp class.
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
 * FacebookWordpressMailchimpForWp class.
 */
class FacebookWordpressMailchimpForWp extends FacebookWordpressIntegrationBase {
    const PLUGIN_FILE   = 'mailchimp-for-wp/mailchimp-for-wp.php';
    const TRACKING_NAME = 'mailchimp-for-wp';

    /**
     * Injects Facebook Pixel events for the MailChimp for WP plugin.
     *
     * This method sets up WordPress actions to inject Facebook Pixel events
     * for different stages of the MailChimp for WP plugin process.
     *
     * @return void
     */
    public static function inject_pixel_code() {
    self::add_pixel_fire_for_hook(
        array(
            'hook_name'       => 'mc4wp_form_subscribed',
            'classname'       => __CLASS__,
            'inject_function' => 'injectLeadEvent',
        )
    );
    }

    /**
     * Injects Facebook Pixel events for the MailChimp for WP plugin.
     *
     * This method sets up WordPress actions to inject Facebook Pixel events
     * for different stages of the MailChimp for WP plugin process.
     *
     * @return void
     */
    public static function injectLeadEvent() {
        if ( FacebookPluginUtils::is_internal_user() ) {
            return;
        }

        $server_event = ServerEventFactory::safe_create_event(
            'Lead',
            array( __CLASS__, 'readFormData' ),
            array(),
            self::TRACKING_NAME,
            true
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
     * Reads form data from the $_POST global array.
     *
     * This function extracts user-related data
     * such as email, first name, last name,
     * phone number, and address details from the
     * $_POST array, commonly used in form
     * submissions. The extracted data includes:
     * - 'email': The user's email address.
     * - 'first_name': The user's first name.
     * - 'last_name': The user's last name.
     * - 'phone': The user's phone number.
     * - 'city', 'state', 'zip', 'country': Address details,
     * where the country must
     *   be specified using a 2-letter code.
     *
     * The function returns an associative array containing the extracted data.
     *
     * @return array An associative array of form data.
     */
    public static function readFormData() {
        $event_data = array();
        if ( ! empty( $_POST['EMAIL'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $event_data['email'] = sanitize_email( wp_unslash( $_POST['EMAIL'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }

        if ( ! empty( $_POST['FNAME'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $event_data['first_name'] = sanitize_text_field( wp_unslash( $_POST['FNAME'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }

        if ( ! empty( $_POST['LNAME'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $event_data['last_name'] = sanitize_text_field( wp_unslash( $_POST['LNAME'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }

        if ( ! empty( $_POST['PHONE'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $event_data['phone'] = sanitize_text_field( wp_unslash( $_POST['PHONE'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }

        if ( ! empty( $_POST['ADDRESS'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $address_data = sanitize_text_field( wp_unslash( $_POST['ADDRESS'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

            if ( ! empty( $address_data['city'] ) ) {
                $event_data['city'] = sanitize_text_field( $address_data['city'] );
            }

            if ( ! empty( $address_data['state'] ) ) {
                $event_data['state'] =
                sanitize_text_field( $address_data['state'] );
            }

            if ( ! empty( $address_data['zip'] ) ) {
                $event_data['zip'] = sanitize_text_field( $address_data['zip'] );
            }

            if (
            ! empty( $address_data['country'] )
            && strlen( $address_data['country'] ) === 2
            ) {
                $event_data['country'] = $address_data['country'];
            }
        }
        return $event_data;
    }
}
