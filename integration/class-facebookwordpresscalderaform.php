<?php
/**
 * Facebook Pixel Plugin FacebookWordpressCalderaForm class.
 *
 * This file contains the main logic for FacebookWordpressCalderaForm.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressCalderaForm class.
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

use FacebookPixelPlugin\Core\AjaxHtmlDelivery;

/**
 * FacebookWordpressCalderaForm class.
 */
class FacebookWordpressCalderaForm extends FacebookWordpressIntegrationBase {
    const PLUGIN_FILE   = 'caldera-forms/caldera-core.php';
    const TRACKING_NAME = 'caldera-forms';

    /**
     * Registers the Caldera hook: a Lead event dispatched through Signals when
     * the AJAX submission completes, with the browser pixel appended to the
     * response HTML (same caldera_forms_ajax_return filter).
     *
     * @return void
     */
    public function inject_pixel_code() {
        $this->signals->on(
            'caldera_forms_ajax_return',
            'Lead',
            array( $this, 'read_form_data' ),
            new AjaxHtmlDelivery( 'caldera_forms_ajax_return' ),
            self::TRACKING_NAME,
            10,
            2
        );
    }

    /**
     * Extracts the Lead event data from a completed Caldera submission.
     *
     * @param array $out  The Caldera Forms AJAX response.
     * @param array $form The form data.
     * @return \FacebookPixelPlugin\Core\EventData|null
     */
    public function read_form_data( $out, $form = array() ) {
        if ( ! is_array( $out ) || ! isset( $out['status'] )
            || 'complete' !== $out['status'] ) {
            return null;
        }
        if ( empty( $form ) ) {
            return null;
        }

        return $this->event_builder->build(
            array(
                'email'      => self::getEmail( $form ),
                'first_name' => self::getFirstName( $form ),
                'last_name'  => self::getLastName( $form ),
                'phone'      => self::getPhone( $form ),
                'state'      => self::getState( $form ),
            )
        );
    }

    /**
     * Get the email address from the form data.
     *
     * @param array $form The form data.
     *
     * @return string The email address.
     */
    private static function getEmail( $form ) {
        return self::getFieldValue( $form, 'type', 'email' );
    }

    /**
     * Get the first name from the form data.
     *
     * @param array $form The form data.
     *
     * @return string The first name.
     */
    private static function getFirstName( $form ) {
        return self::getFieldValue( $form, 'slug', 'first_name' );
    }

    /**
     * Get the last name from the form data.
     *
     * @param array $form The form data.
     *
     * @return string The last name.
     */
    private static function getLastName( $form ) {
        return self::getFieldValue( $form, 'slug', 'last_name' );
    }

    /**
     * Get the state from the form data.
     *
     * @param array $form The form data.
     *
     * @return string|null The state, or null if not found.
     */
    private static function getState( $form ) {
        return self::getFieldValue( $form, 'type', 'states' );
    }

    /**
     * Get the phone number from the form data.
     *
     * Attempts to extract the phone number using the 'phone_better' type first.
     * If not found, falls back to using the 'phone' type.
     *
     * @param array $form The form data.
     *
     * @return string|null The phone number, or null if not found.
     */
    private static function getPhone( $form ) {
        $phone = self::getFieldValue( $form, 'type', 'phone_better' );
        return empty( $phone ) ?
        self::getFieldValue( $form, 'type', 'phone' ) : $phone;
    }

    /**
     * Retrieves the value of a field from the form data.
     *
     * Searches through the form's fields to find a field with the specified
     * attribute and attribute value. If a match is found, returns the value
     * from the $_POST array using the field's ID.
     *
     * @param array  $form The form data containing fields.
     * @param string $attr The attribute to match against in the field.
     * @param string $attr_value The value of the attribute to look for.
     *
     * @return mixed|null The value of the field from $_POST, or null
     *  if not found.
     */
    private static function getFieldValue( $form, $attr, $attr_value ) {
        if ( empty( $form['fields'] ) ) {
            return null;
        }

        foreach ( $form['fields'] as $field ) {
            if ( isset( $field[ $attr ] ) && $field[ $attr ] === $attr_value ) {
            return sanitize_text_field(
                wp_unslash(
                    $_POST[ $field['ID'] ] ?? ''  // phpcs:ignore WordPress.Security.NonceVerification.Missing
                )
            );
            }
        }

        return null;
    }
}
