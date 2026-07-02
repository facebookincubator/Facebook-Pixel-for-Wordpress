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

use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\CompositeDelivery;
use FacebookPixelPlugin\Core\FooterDelivery;
use FacebookPixelPlugin\Core\AjaxFilterDelivery;

/**
 * FacebookWordpressWPForms class.
 */
class FacebookWordpressWPForms extends FacebookWordpressIntegrationBase {
    const PLUGIN_FILE   = 'wpforms-lite/wpforms.php';
    const TRACKING_NAME = 'wpforms-lite';

    /**
     * Registers the WPForms hooks.
     *
     * A Lead event is dispatched through Signals when a submission is
     * processed. The browser pixel is delivered both in the footer (classic
     * submit) and in the AJAX success/redirect responses (AJAX submit); a
     * front-end listener evaluates the AJAX-delivered code.
     *
     * @return void
     */
    public function set_up_tracking() {
        $this->signals->on(
            'wpforms_process_before',
            'Lead',
            array( $this, 'read_form_data' ),
            new CompositeDelivery(
                array(
                    new FooterDelivery(),
                    new AjaxFilterDelivery(
                        'wpforms_ajax_submit_success_response',
                        'fb_pxl_code'
                    ),
                    new AjaxFilterDelivery(
                        'wpforms_ajax_submit_redirect',
                        'fb_pxl_code'
                    ),
                )
            ),
            self::TRACKING_NAME,
            20,
            2
        );

        add_action(
            'wp_footer',
            array( $this, 'inject_ajax_listener' ),
            9
        );
    }

    /**
     * Outputs a JS listener that evaluates fb_pxl_code from WPForms AJAX
     * responses (the AJAX path does not re-render wp_footer).
     *
     * @return void
     */
    public function inject_ajax_listener() {
        ?>
        <!-- Meta Pixel Event Code -->
        <script type='text/javascript'>
        (function ( $ ) {
            if ( ! $ || typeof document === 'undefined' ) {
                return;
            }
            // WPForms triggers this jQuery event and passes the AJAX response object.
            $( document ).on( 'wpformsAjaxSubmitSuccess', function ( event, data ) {
                if ( data && data.data && data.data.fb_pxl_code ) {
                    try {
                        new Function( data.data.fb_pxl_code )();
                    } catch ( e ) {
                        console && console.warn && console.warn( 'Meta Pixel eval failed', e );
                    }
                }
            } );
        })( window.jQuery );
        </script>
        <!-- End Meta Pixel Event Code -->
        <?php
    }

    /**
     * Extracts the Lead event data from a WPForms submission.
     *
     * @param array $entry     The form entry data.
     * @param array $form_data The form schema data.
     * @return \FacebookPixelPlugin\Core\EventData|null
     */
    public function read_form_data( $entry, $form_data = array() ) {
        if ( empty( $entry ) || empty( $form_data ) ) {
            return null;
        }

        $name = self::getName( $entry, $form_data );

        return $this->event_builder->build(
            array_merge(
                array(
                    'email'      => self::getEmail( $entry, $form_data ),
                    'first_name' => ! empty( $name ) ? $name[0] : null,
                    'last_name'  => ! empty( $name ) ? $name[1] : null,
                    'phone'      => self::getPhone( $entry, $form_data ),
                ),
                self::getAddress( $entry, $form_data )
            )
        );
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
        $phone = self::getField( $entry, $form_data, 'phone' );
        if ( ! is_null( $phone ) && '' !== $phone ) {
            return $phone;
        }

        return self::getTextFieldByLabel(
            $entry,
            $form_data,
            array( 'phone', 'tel', 'telephone', 'mobile' )
        );
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
            // Fall back to individual text fields when the Address fancy field
            // is not available in WPForms Lite.
            return self::getAddressFromTextFields( $entry, $form_data );
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
     * Retrieves a text field value by matching its label.
     *
     * WPForms Lite users often rely on generic "text" fields instead of
     * the premium/fancy types. This helper lets us recover values for
     * phone/address-like fields when their labels match expected names.
     *
     * @param array    $entry   The form entry data.
     * @param array    $form_data The form schema data.
     * @param string[] $labels Candidate labels (case-insensitive).
     * @return string|null
     */
    private static function getTextFieldByLabel( $entry, $form_data, $labels ) {
        if ( empty( $form_data['fields'] ) || empty( $entry['fields'] ) ) {
            return null;
        }

        $normalized_labels = array_map( array( self::class, 'normalizeLabel' ), $labels );

        foreach ( $form_data['fields'] as $field ) {
            if ( 'text' !== $field['type'] || empty( $field['label'] ) ) {
                continue;
            }

            $label = self::normalizeLabel( $field['label'] );
            if ( in_array( $label, $normalized_labels, true ) ) {
                $value = isset( $entry['fields'][ $field['id'] ] )
                    ? $entry['fields'][ $field['id'] ]
                    : null;

                return '' !== $value ? $value : null;
            }
        }

        return null;
    }

    /**
     * Builds address data from individual text fields when the Address field
     * isn't present.
     *
     * @param array $entry     The form entry data.
     * @param array $form_data The form schema data.
     *
     * @return array
     */
    private static function getAddressFromTextFields( $entry, $form_data ) {
        $address_data = array();

        $address_data['city'] = self::getTextFieldByLabel(
            $entry,
            $form_data,
            array( 'city', 'town' )
        );

        $address_data['state'] = self::getTextFieldByLabel(
            $entry,
            $form_data,
            array( 'state', 'province', 'region', 'county' )
        );

        $address_data['country'] = self::getTextFieldByLabel(
            $entry,
            $form_data,
            array( 'country', 'country/region' )
        );

        $address_data['zip'] = self::getTextFieldByLabel(
            $entry,
            $form_data,
            array( 'zip', 'postal', 'postcode', 'zip code' )
        );

        // Remove null/empty values so we don't send sparse keys.
        return array_filter(
            $address_data,
            function ( $value ) {
                return ! is_null( $value ) && '' !== $value;
            }
        );
    }

    /**
     * Normalizes labels for case-insensitive comparison.
     *
     * @param string $label The label to normalize.
     * @return string
     */
    private static function normalizeLabel( $label ) {
        return strtolower( trim( $label ) );
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
