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

use FacebookPixelPlugin\Core\FooterDelivery;

/**
 * FacebookWordpressMailchimpForWp class.
 */
class FacebookWordpressMailchimpForWp extends FacebookWordpressIntegrationBase {
    const PLUGIN_FILE   = 'mailchimp-for-wp/mailchimp-for-wp.php';
    const TRACKING_NAME = 'mailchimp-for-wp';

    /**
     * Registers the Mailchimp for WP hook: a Lead event dispatched through
     * Signals on a successful subscribe, with the pixel emitted in the footer.
     *
     * @return void
     */
    public function inject_pixel_code() {
        $this->signals->on(
            'mc4wp_form_subscribed',
            'Lead',
            array( $this, 'read_form_data' ),
            new FooterDelivery(),
            self::TRACKING_NAME,
            10,
            1
        );
    }

    /**
     * Extracts the Lead event data from the Mailchimp submission ($_POST).
     *
     * @param mixed $form The mc4wp form (unused; data is read from $_POST).
     * @return \FacebookPixelPlugin\Core\EventData
     */
    public function read_form_data( $form = null ) {
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

        return $this->event_builder->build( $event_data );
    }
}
