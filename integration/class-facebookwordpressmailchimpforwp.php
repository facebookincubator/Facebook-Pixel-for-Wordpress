<?php
/**
 * Facebook Pixel Plugin FacebookWordpressMailchimpForWp class.
 *
 * This file contains the tracking integration for Mailchimp for WordPress.
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

/**
 * Tracking integration for the Mailchimp for WordPress plugin.
 */
class FacebookWordpressMailchimpForWp extends TrackableLeadFormIntegrationBase {

    const PLUGIN_FILE      = 'mailchimp-for-wp/mailchimp-for-wp.php';
    const INTEGRATION_NAME = 'mailchimp-for-wp';

    /**
     * Initialize the integration.
     *
     * @return void
     */
    protected function set_up_tracking() {
        if ( FacebookPluginUtils::is_internal_user() ) {
            return;
        }
        add_action( 'mc4wp_form_subscribed', array( $this, 'capture_submitted_form' ), 11, 1 );
    }

    /**
     * Captures a submitted form and sends the Lead event via CAPI and Pixel.
     *
     * @param mixed ...$args The Mailchimp for WP submit-hook arguments.
     * @return void
     */
    public function capture_submitted_form( ...$args ) {
        $event = $this->signals->generate_event(
            self::EVENT_NAME,
            $this->extract_lead_data( ...$args ),
            self::INTEGRATION_NAME
        );

        $this->signals->capi->send( array( $event ) );

        $this->signals->pixel->enqueue( $event );
    }

    /**
     * Reads the form id from the plugin submit-hook arguments.
     *
     * @param mixed ...$args The Mailchimp for WP submit-hook arguments.
     * @return mixed The form identifier.
     */
    protected function get_form_id( ...$args ) {
        return $args[0];
    }

    /**
     * Yields the submitted form parameters as normalized rows.
     *
     * @param mixed ...$args The Mailchimp for WP submit-hook arguments.
     * @return \Generator Rows of ['name'=>, 'value'=>].
     */
    protected function get_form_param_iterator( ...$args ) {
        $form = $args[0];
        foreach ( $form->data as $field_name => $field_value ) {
            if ( empty( $field_name ) || empty( $field_value ) ) {
                continue;
            }
            yield self::get_iterator_yield_output( $field_name, null, $field_value );
        }
    }

    /**
     * Hard-coded extraction used when no mapping is configured for the form.
     *
     * @param iterable $form_param_iterator Rows of ['name'=>, 'value'=>].
     * @return array Normalized lead data keyed by Lead parameter name.
     */
    protected function extract_lead_data_fallback( $form_param_iterator ) {
        $result = array();
        foreach ( $form_param_iterator as $item ) {
            $name  = $item['name'];
            $value = $item['value'];
            $key   = '';
            switch ( $name ) {
                case 'EMAIL':
                    $key = 'email';
                    break;
                case 'FNAME':
                    $key = 'first_name';
                    break;
                case 'LNAME':
                    $key = 'last_name';
                    break;
                case 'PHONE':
                    $key = 'phone';
                    break;
                case 'ADDRESS':
                    $result = array_merge( $result, $this->get_address_param_breakdown( $value ) );
                    break;
                default:
                    break;
            }
            if ( empty( $key ) ) {
                continue;
            }
            $result[ $key ] = $value;
        }
        return $result;
    }

    /**
     * Breaks an address field value into individual city/state/zip/country
     * lead parameters.
     *
     * @param array $address_field_value The address field value, keyed by
     *                                   address component.
     * @return array Normalized address lead data keyed by Lead parameter name.
     */
    private function get_address_param_breakdown( $address_field_value ) {
        $result = array();
        $fn     = function ( $param_name ) use ( $address_field_value, &$result ) {
            if ( isset( $address_field_value[ $param_name ] ) ) {
                $result[ $param_name ] = sanitize_text_field( $address_field_value[ $param_name ] );
            }
        };
        $fn( 'city' );
        $fn( 'state' );
        $fn( 'zip' );
        if (
            ! empty( $address_field_value['country'] )
            && strlen( $address_field_value['country'] ) === 2
        ) {
            $result['country'] = $address_field_value['country'];
        }

        return $result;
    }
}
