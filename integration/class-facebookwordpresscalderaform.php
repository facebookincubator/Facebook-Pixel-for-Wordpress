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

use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\FacebookWordPressOptions;
use FacebookPixelPlugin\Core\FormFieldMapper;
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\PixelRenderer;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\UserData;

/**
 * FacebookWordpressCalderaForm class.
 */
class FacebookWordpressCalderaForm extends FacebookWordpressFormIntegrationBase {
    const PLUGIN_FILE   = 'caldera-forms/caldera-core.php';
    const TRACKING_NAME = 'caldera-forms';

    /**
     * Whether Caldera Forms is active.
     *
     * @return bool
     */
    public static function is_available() {
        return class_exists( '\Caldera_Forms_Forms' );
    }

    /**
     * Human-readable label for the mapping UI.
     *
     * @return string
     */
    public static function get_integration_label() {
        return 'Caldera Forms';
    }

    /**
     * Returns all Caldera form config arrays.
     *
     * @return iterable
     */
    protected static function fetch_forms() {
        return \Caldera_Forms_Forms::get_forms( true, true );
    }

    /**
     * Maps a Caldera form config to id + title.
     *
     * @param mixed $raw_form A Caldera form config array.
     * @return array<string,mixed>
     */
    protected static function map_form( $raw_form ) {
        return array(
            'id'    => isset( $raw_form['ID'] ) ? $raw_form['ID'] : '',
            'title' => isset( $raw_form['name'] ) ? $raw_form['name'] : '',
        );
    }

    /**
     * Returns the field config arrays for a single Caldera form.
     *
     * @param string $form_id The Caldera form id.
     * @return iterable
     */
    protected static function fetch_form_fields( $form_id ) {
        $form = \Caldera_Forms_Forms::get_form( $form_id );
        return ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) )
            ? array()
            : $form['fields'];
    }

    /**
     * Maps a Caldera field config to a field, skipping non-input fields.
     *
     * The field id is the Caldera field ID, the same key used to read the
     * submitted value from $_POST at event time.
     *
     * @param mixed $raw_field A Caldera field config array.
     * @return array<string,mixed>|null
     */
    protected static function map_field( $raw_field ) {
        if ( empty( $raw_field['ID'] ) ) {
            return null;
        }
        $type = isset( $raw_field['type'] ) ? (string) $raw_field['type'] : '';
        if ( in_array(
            $type,
            array( 'button', 'html', 'section_break' ),
            true
        ) ) {
            return null;
        }
        $label = isset( $raw_field['label'] ) && '' !== $raw_field['label']
            ? (string) $raw_field['label']
            : ( isset( $raw_field['slug'] ) ? (string) $raw_field['slug'] : '' );
        return array(
            'id'    => $raw_field['ID'],
            'label' => $label,
            'type'  => $type,
        );
    }

    /**
     * Hook into Caldera Forms to inject the Pixel code.
     *
     * Hooks into the `caldera_forms_ajax_return`
     * action and calls the `injectLeadEvent` method.
     *
     * @since 0.9.0
     */
    public static function inject_pixel_code() {
        add_action(
            'caldera_forms_ajax_return',
            array( __CLASS__, 'injectLeadEvent' ),
            10,
            2
        );
    }

    /**
     * Injects the Pixel code into the Caldera Forms response.
     *
     * Hooks into the `caldera_forms_ajax_return` action and checks if the form
     * is submitted successfully and if the user is not an internal user.
     * If conditions are met, it creates a `Lead` event and tracks it using
     * the `FacebookServerSideEvent` class.
     * Then it renders the Pixel code using the `PixelRenderer` class and
     * appends the code to the form response.
     *
     * @param array $out The Caldera Forms response.
     * @param array $form The form data.
     *
     * @return array The modified Caldera Forms response.
     */
    public static function injectLeadEvent( $out, $form ) {
        if (
        FacebookPluginUtils::is_internal_user() ||
        'complete' !== $out['status']
        ) {
            return $out;
        }

        $server_event = ServerEventFactory::safe_create_event(
            'Lead',
            array( __CLASS__, 'readFormData' ),
            array( $form ),
            self::TRACKING_NAME,
            true
        );
        FacebookServerSideEvent::get_instance()->track( $server_event );

        $code = PixelRenderer::render(
            array( $server_event ),
            self::TRACKING_NAME
        );
        $code = sprintf(
            '
        <!-- Meta Pixel Event Code -->
        %s
        <!-- End Meta Pixel Event Code -->
            ',
            $code
        );

        $out['html'] .= $code;
        return $out;
    }

    /**
     * Reads the form data from the Caldera Forms submission.
     *
     * @param array $form The form data.
     *
     * @return array The form data in the format
     * expected by the `FacebookServerSideEvent` class.
     */
    public static function readFormData( $form ) {
        if ( empty( $form ) ) {
            return array();
        }
        $data = array(
            'email'      => self::getEmail( $form ),
            'first_name' => self::getFirstName( $form ),
            'last_name'  => self::getLastName( $form ),
            'phone'      => self::getPhone( $form ),
            'state'      => self::getState( $form ),
        );

        $form_id = isset( $form['ID'] ) ? (string) $form['ID'] : '';

        return self::applyFieldMapping( $form_id, $data );
    }

    /**
     * Merges any saved field mapping for the form over the heuristic data.
     *
     * Mapped values (read from $_POST by Caldera field ID) take priority;
     * unmapped standard fields fall back to the auto-detected values.
     *
     * @param string $form_id The Caldera form id.
     * @param array  $data     The heuristically extracted data.
     * @return array The merged data.
     */
    private static function applyFieldMapping( $form_id, $data ) {
        if ( '' === $form_id ) {
            return $data;
        }

        $mapped = ( new FormFieldMapper() )->resolve(
            self::TRACKING_NAME,
            $form_id,
            function ( $field_id ) {
                if ( ! isset( $_POST[ $field_id ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    return null;
                }
                return sanitize_text_field(
                    wp_unslash( $_POST[ $field_id ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
                );
            }
        );

        return array_merge( $data, $mapped );
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
