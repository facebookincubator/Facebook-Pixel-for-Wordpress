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
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\PixelRenderer;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\UserData;

/**
 * FacebookWordpressCalderaForm class.
 */
class FacebookWordpressCalderaForm extends FacebookWordpressIntegrationBase {
    const PLUGIN_FILE   = 'caldera-forms/caldera-core.php';
    const TRACKING_NAME = 'caldera-forms';

    /**
     * Whether this integration supports the admin Field Mapping screen.
     *
     * @return bool
     */
    public static function supports_field_mapping() {
        return true;
    }

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
     * Lists all Caldera forms.
     *
     * @return array<int,array<string,string>>
     */
    public static function get_forms() {
        if ( ! self::is_available() ) {
            return array();
        }

        $forms = array();
        foreach ( \Caldera_Forms_Forms::get_forms( true, true ) as $form ) {
            if ( empty( $form['ID'] ) ) {
                continue;
            }
            $forms[] = array(
                'id'    => (string) $form['ID'],
                'title' => isset( $form['name'] ) ? (string) $form['name'] : '',
            );
        }

        return $forms;
    }

    /**
     * Lists the fields of a single Caldera form.
     *
     * The field id is the Caldera field ID, the same key used to read the
     * submitted value from $_POST at event time.
     *
     * @param string $form_id The Caldera form id.
     * @return array<int,array<string,string>>
     */
    public static function get_form_fields( $form_id ) {
        if ( ! self::is_available() ) {
            return array();
        }

        $form = \Caldera_Forms_Forms::get_form( $form_id );
        if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
            return array();
        }

        $fields = array();
        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['ID'] ) ) {
                continue;
            }
            $type = isset( $field['type'] ) ? (string) $field['type'] : '';
            if ( in_array( $type, array( 'button', 'html', 'section_break' ), true ) ) {
                continue;
            }
            $label    = isset( $field['label'] ) && '' !== $field['label']
                ? (string) $field['label']
                : ( isset( $field['slug'] ) ? (string) $field['slug'] : (string) $field['ID'] );
            $fields[] = array(
                'id'    => (string) $field['ID'],
                'label' => $label,
                'type'  => $type,
            );
        }

        return $fields;
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
        return array(
            'email'      => self::getEmail( $form ),
            'first_name' => self::getFirstName( $form ),
            'last_name'  => self::getLastName( $form ),
            'phone'      => self::getPhone( $form ),
            'state'      => self::getState( $form ),
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
