<?php
/**
 * Facebook Pixel Plugin FacebookWordpressWPForms class.
 *
 * This file contains the main logic for FacebookWordpressWPForms.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressWPForms class.
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
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\FacebookWordPressOptions;
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\PixelRenderer;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\UserData;

/**
 * FacebookWordpressWPForms class.
 */
class FacebookWordpressWPForms extends FacebookWordpressIntegrationBase {
    const PLUGIN_FILE   = 'wpforms-lite/wpforms.php';
    const TRACKING_NAME = 'wpforms-lite';

    /**
     * Hooks into WPForms to inject the Pixel code.
     *
     * This method adds an action to the 'wpforms_process_before' hook,
     * which will trigger the 'trackEvent' method. It ensures that
     * the Pixel code is injected during the form processing stage.
     */
    public static function inject_pixel_code() {
        add_action(
            'wpforms_process_before',
            array( __CLASS__, 'trackEvent' ),
            20,
            2
        );
    }

    /**
     * Tracks a server-side event for a form submission in WPForms.
     *
     * This method is hooked into the 'wpforms_process_before' action, which is
     * fired by WPForms before a form is processed.
     * It then calls the track method
     * on the FacebookServerSideEvent instance, which generates a lead event for
     * the form submission.
     *
     * If the user is an internal user, the method returns without tracking
     * any event.
     *
     * @param array $entry The form entry data.
     * @param array $form_data The form data.
     *
     * @return void
     */
    public static function trackEvent( $entry, $form_data ) {
        if ( FacebookPluginUtils::is_internal_user() ) {
            return;
        }

        $server_event = ServerEventFactory::safe_create_event(
            'Lead',
            array( __CLASS__, 'readFormData' ),
            array( $entry, $form_data ),
            self::TRACKING_NAME,
            true
        );
        FacebookServerSideEvent::get_instance()->track( $server_event );

        add_action(
            'wp_footer',
            array( __CLASS__, 'injectLeadEvent' ),
            20
        );
    }

    /**
     * Injects lead event code into the footer.
     *
     * This method retrieves tracked events from the FacebookServerSideEvent
     * instance and renders them into pixel code using the PixelRenderer.
     * The resulting code is printed into the footer section of the page.
     * If the user is an internal user, the method returns without injecting
     * any code.
     *
     * @return void
     */
    public static function injectLeadEvent() {
        if ( FacebookPluginUtils::is_internal_user() ) {
            return;
        }

        $events     =
            FacebookServerSideEvent::get_instance()->get_tracked_events();
        $pixel_code = PixelRenderer::render( $events, self::TRACKING_NAME );

        printf(
            '
    <!-- Meta Pixel Event Code -->
    %s
    <!-- End Meta Pixel Event Code -->
          ',
            $pixel_code // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        );
    }

    /**
     * Reads the form submission data and extracts user information.
     *
     * This method processes the form entry and form
     * data to extract user-related
     * information such as email, first name, last name,
     * and phone number. It also
     * retrieves the address data, including city,
     * state, country, and postal code.
     *
     * If either the form entry or form data is
     * empty, an empty array is returned.
     *
     * @param array $entry The form entry data.
     * @param array $form_data The form schema data.
     *
     * @return array An associative array
     *               containing user and address information
     *               extracted from the form entry.
     */
    public static function readFormData( $entry, $form_data ) {
        if ( empty( $entry ) || empty( $form_data ) ) {
            return array();
        }

        $name = self::getName( $entry, $form_data );

        $event_data = array(
            'email'      => self::getEmail( $entry, $form_data ),
            'first_name' => ! empty( $name ) ? $name[0] : null,
            'last_name'  => ! empty( $name ) ? $name[1] : null,
            'phone'      => self::getPhone( $entry, $form_data ),
        );

        $event_data = array_merge(
            $event_data,
            self::getAddress( $entry, $form_data )
        );

        return $event_data;
    }

    /**
     * Retrieves the phone number from the form data.
     *
     * This method extracts the phone number field from the provided form entry
     * and form data.
     *
     * @param array $entry The form entry data.
     * @param array $form_data The form schema data.
     *
     * @return string|null The phone number, or null if no phone field is found.
     */
    private static function getPhone( $entry, $form_data ) {
        return self::getField( $entry, $form_data, 'phone' );
    }

    /**
     * Retrieves the email address from the form data.
     *
     * This method extracts the email address field from the provided form entry
     * and form data.
     *
     * @param array $entry The form entry data.
     * @param array $form_data The form schema data.
     *
     * @return string|null The email address, or null
     *                     if no email field is found.
     */
    private static function getEmail( $entry, $form_data ) {
        return self::getField( $entry, $form_data, 'email' );
    }

    /**
     * Retrieves the address data from the form data.
     *
     * This method extracts the address data (city, state, country, and zip)
     * from the provided form entry
     * and form data. The country is sent in ISO format.
     *
     * Note that if the address scheme is 'us' and country
     * is not present, 'US' is used as the country.
     *
     * @param array $entry The form entry data.
     * @param array $form_data The form schema data.
     *
     * @return array The address data.
     */
    private static function getAddress( $entry, $form_data ) {
        $address_field_data = self::getField( $entry, $form_data, 'address' );
        if ( is_null( $address_field_data ) ) {
            return array();
        }
        $address_data = array();
        if ( isset( $address_field_data['city'] ) ) {
            $address_data['city'] = $address_field_data['city'];
        }
        if ( isset( $address_field_data['state'] ) ) {
            $address_data['state'] = $address_field_data['state'];
        }

        if ( isset( $address_field_data['country'] ) ) {
            $address_data['country'] = $address_field_data['country'];
        } else {
            $address_scheme = self::getAddressScheme( $form_data );
            if ( 'us' === $address_scheme ) {
            $address_data['country'] = 'US';
            }
        }
        if ( isset( $address_field_data['postal'] ) ) {
            $address_data['zip'] = $address_field_data['postal'];
        }
        return $address_data;
    }

    /**
     * Retrieves the user's name from the form data.
     *
     * This method extracts the name field from the provided form entry
     * and form data. It supports two formats:
     * - 'simple': where the name is a single string,
     * split into first and last name.
     * - 'first-last': where the name is provided as separate
     * 'first' and 'last' fields.
     *
     * @param array $entry The form entry data.
     * @param array $form_data The form schema data.
     *
     * @return array|null An array containing the first and
     *                    last name, or null if no name field is found.
     */
    private static function getName( $entry, $form_data ) {
        if ( empty( $form_data['fields'] ) || empty( $entry['fields'] ) ) {
            return null;
        }

        $entries = $entry['fields'];
        foreach ( $form_data['fields'] as $field ) {
            if ( 'name' === $field['type'] ) {
            if ( 'simple' === $field['format'] ) {
                return ServerEventFactory::split_name(
                    $entries[ $field['id'] ]
                );
            } elseif ( 'first-last' === $field['format'] ) {
                return array(
            $entries[ $field['id'] ]['first'],
            $entries[ $field['id'] ]['last'],
                );
            }
            }
        }

        return null;
    }

    /**
     * Retrieves the value of a specific field type from the form entry data.
     *
     * This method searches through the form schema data to find a field of
     * the specified type and returns the corresponding value from the form
     * entry data.
     *
     * @param array  $entry The form entry data.
     * @param array  $form_data The form schema data.
     * @param string $type The type of the field to retrieve.
     *
     * @return mixed|null The value of the field, or null if no
     *                    field of the specified type is found.
     */
    private static function getField( $entry, $form_data, $type ) {
        if ( empty( $form_data['fields'] ) || empty( $entry['fields'] ) ) {
            return null;
        }

        foreach ( $form_data['fields'] as $field ) {
            if ( $field['type'] === $type ) {
            return $entry['fields'][ $field['id'] ];
            }
        }

        return null;
    }

    /**
     * Retrieves the address scheme from the form data.
     *
     * This method searches through the form schema data to find the first
     * 'address' field and returns its 'scheme' value, which is either 'us' or
     * 'international'. If no address field is found, or if the address field
     * does not have a scheme, this method returns null.
     *
     * @param array $form_data The form schema data.
     *
     * @return string|null The address scheme, or
     *                     null if no address field is found.
     */
    private static function getAddressScheme( $form_data ) {
        foreach ( $form_data['fields'] as $field ) {
            if ( 'address' === $field['type'] ) {
            if ( isset( $field['scheme'] ) ) {
                return $field['scheme'];
            }
            }
        }
        return null;
    }
}
