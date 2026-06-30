<?php
/**
 * Facebook Pixel Plugin FacebookWordpressFormIntegrationBase class.
 *
 * Shared base for form-builder integrations that support the admin Field
 * Mapping screen. It implements the common discovery skeleton (availability
 * guard, iteration, output shape, de-duplication, label fallback) and defers
 * the plugin-specific extraction to small template methods.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressFormIntegrationBase class.
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

/**
 * FacebookWordpressFormIntegrationBase class.
 */
abstract class FacebookWordpressFormIntegrationBase extends FacebookWordpressIntegrationBase {

    /**
     * Form-builder integrations always support the Field Mapping screen.
     *
     * @return bool
     */
    public static function supports_field_mapping() {
        return true;
    }

    /**
     * Lists the forms managed by this integration's plugin.
     *
     * Skeleton: guards on availability, then maps each raw form returned by
     * fetch_forms() into the { id, title } shape. Entries without an id are
     * skipped.
     *
     * @return array<int,array<string,string>>
     */
    public static function get_forms() {
        if ( ! static::is_available() ) {
            return array();
        }

        $forms = array();
        foreach ( static::fetch_forms() as $raw_form ) {
            $form = static::map_form( $raw_form );
            if ( empty( $form['id'] ) ) {
                continue;
            }
            $forms[] = array(
                'id'    => (string) $form['id'],
                'title' => isset( $form['title'] ) ? (string) $form['title'] : '',
            );
        }

        return $forms;
    }

    /**
     * Lists the input fields of a single form.
     *
     * Skeleton: guards on availability, maps each raw field via map_field()
     * (which returns null to skip a field), de-duplicates by id, and falls
     * back to the id when no label is provided.
     *
     * @param string $form_id The native form id.
     * @return array<int,array<string,string>>
     */
    public static function get_form_fields( $form_id ) {
        if ( ! static::is_available() ) {
            return array();
        }

        $fields = array();
        $seen   = array();
        foreach ( static::fetch_form_fields( $form_id ) as $raw_field ) {
            $field = static::map_field( $raw_field );
            if ( null === $field || empty( $field['id'] ) ) {
                continue;
            }
            $id = (string) $field['id'];
            if ( isset( $seen[ $id ] ) ) {
                continue;
            }
            $seen[ $id ] = true;

            $label    = isset( $field['label'] ) ? (string) $field['label'] : '';
            $fields[] = array(
                'id'    => $id,
                'label' => '' !== $label ? $label : $id,
                'type'  => isset( $field['type'] ) ? (string) $field['type'] : '',
            );
        }

        return $fields;
    }

    /**
     * Returns the raw form objects/arrays from the plugin.
     *
     * Subclasses must also override is_available() and
     * get_integration_label() (declared with safe defaults on the parent
     * FacebookWordpressIntegrationBase, so they cannot be redeclared abstract
     * here).
     *
     * @return iterable
     */
    abstract protected static function fetch_forms();

    /**
     * Maps a raw form into an array with at least an 'id' (and optional
     * 'title').
     *
     * @param mixed $raw_form A single raw form from fetch_forms().
     * @return array<string,mixed>
     */
    abstract protected static function map_form( $raw_form );

    /**
     * Returns the raw field objects/arrays for a form from the plugin.
     *
     * @param string $form_id The native form id.
     * @return iterable
     */
    abstract protected static function fetch_form_fields( $form_id );

    /**
     * Maps a raw field into an array with 'id' (and optional 'label', 'type'),
     * or returns null to skip the field.
     *
     * @param mixed $raw_field A single raw field from fetch_form_fields().
     * @return array<string,mixed>|null
     */
    abstract protected static function map_field( $raw_field );
}
