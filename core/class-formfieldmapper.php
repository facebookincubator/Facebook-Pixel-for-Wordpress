<?php
/**
 * Facebook Pixel Plugin FormFieldMapper class.
 *
 * Persists and resolves user-defined mappings between a form's fields and
 * the standard CAPI/Pixel fields. One mapping configuration is stored per
 * form, keyed by integration tracking name and native form id.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FormFieldMapper class.
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

namespace FacebookPixelPlugin\Core;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class FormFieldMapper
 */
class FormFieldMapper {

    /**
     * Returns the full mapping store.
     *
     * Shape:
     * [
     *   '<tracking_name>' => [
     *     '<form_id>' => [
     *       'form_title' => '<title>',
     *       'mappings'   => [ '<field_id>' => '<standard_field>' ],
     *     ],
     *   ],
     * ]
     *
     * @return array The full mapping store.
     */
    public static function get_all() {
        $stored = \get_option(
            FacebookPluginConfig::FORM_FIELD_MAPPINGS_KEY,
            array()
        );
        return is_array( $stored ) ? $stored : array();
    }

    /**
     * Returns the stored entry (form_title + mappings) for a single form.
     *
     * @param string $tracking_name The integration tracking name.
     * @param string $form_id       The native form id.
     * @return array|null The entry, or null if none is stored.
     */
    public static function get_form_entry( $tracking_name, $form_id ) {
        $all     = self::get_all();
        $form_id = (string) $form_id;
        if ( isset( $all[ $tracking_name ][ $form_id ] ) ) {
            return $all[ $tracking_name ][ $form_id ];
        }
        return null;
    }

    /**
     * Returns the field_id => standard_field mappings for a single form.
     *
     * @param string $tracking_name The integration tracking name.
     * @param string $form_id       The native form id.
     * @return array<string,string> The mappings, or an empty array.
     */
    public static function get_mappings( $tracking_name, $form_id ) {
        $entry = self::get_form_entry( $tracking_name, $form_id );
        if ( ! empty( $entry['mappings'] ) && is_array( $entry['mappings'] ) ) {
            return $entry['mappings'];
        }
        return array();
    }

    /**
     * Returns a flat list of all stored mappings for UI listing.
     *
     * Each row: tracking_name, form_id, form_title, mapped_count.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_flat_list() {
        $rows = array();
        foreach ( self::get_all() as $tracking_name => $forms ) {
            if ( ! is_array( $forms ) ) {
                continue;
            }
            foreach ( $forms as $form_id => $entry ) {
                $mappings = isset( $entry['mappings'] )
                    && is_array( $entry['mappings'] )
                    ? $entry['mappings'] : array();
                $rows[]   = array(
                    'tracking_name' => (string) $tracking_name,
                    'form_id'       => (string) $form_id,
                    'form_title'    => isset( $entry['form_title'] )
                        ? (string) $entry['form_title'] : '',
                    'mapped_count'  => count( $mappings ),
                );
            }
        }
        return $rows;
    }

    /**
     * Upserts the mapping for a single form. Any existing entry for the same
     * form is fully replaced, guaranteeing one mapping per form.
     *
     * Invalid target fields are dropped. If the resulting mapping is empty,
     * the form entry is removed instead of stored.
     *
     * @param string               $tracking_name The integration tracking name.
     * @param string               $form_id       The native form id.
     * @param string               $form_title    The human form title.
     * @param array<string,string> $mappings      field_id => standard_field.
     * @return bool True on success.
     */
    public static function save_form_mapping(
        $tracking_name,
        $form_id,
        $form_title,
        $mappings
    ) {
        $tracking_name = (string) $tracking_name;
        $form_id       = (string) $form_id;

        $clean = array();
        if ( is_array( $mappings ) ) {
            foreach ( $mappings as $field_id => $standard ) {
                $field_id = (string) $field_id;
                $standard = (string) $standard;
                if ( '' === $field_id ) {
                    continue;
                }
                if ( ! FormFieldMappingConfig::is_valid_target( $standard ) ) {
                    continue;
                }
                $clean[ $field_id ] = $standard;
            }
        }

        $all = self::get_all();

        if ( empty( $clean ) ) {
            // Nothing valid to store — remove any existing entry.
            if ( isset( $all[ $tracking_name ][ $form_id ] ) ) {
                unset( $all[ $tracking_name ][ $form_id ] );
                if ( empty( $all[ $tracking_name ] ) ) {
                    unset( $all[ $tracking_name ] );
                }
            }
        } else {
            if ( ! isset( $all[ $tracking_name ] )
                || ! is_array( $all[ $tracking_name ] ) ) {
                $all[ $tracking_name ] = array();
            }
            $all[ $tracking_name ][ $form_id ] = array(
                'form_title' => (string) $form_title,
                'mappings'   => $clean,
            );
        }

        return \update_option(
            FacebookPluginConfig::FORM_FIELD_MAPPINGS_KEY,
            $all
        );
    }

    /**
     * Deletes the mapping entry for a single form.
     *
     * @param string $tracking_name The integration tracking name.
     * @param string $form_id       The native form id.
     * @return bool True on success (including when nothing existed).
     */
    public static function delete_form_mapping( $tracking_name, $form_id ) {
        $tracking_name = (string) $tracking_name;
        $form_id       = (string) $form_id;
        $all           = self::get_all();

        if ( ! isset( $all[ $tracking_name ][ $form_id ] ) ) {
            return true;
        }

        unset( $all[ $tracking_name ][ $form_id ] );
        if ( empty( $all[ $tracking_name ] ) ) {
            unset( $all[ $tracking_name ] );
        }

        return \update_option(
            FacebookPluginConfig::FORM_FIELD_MAPPINGS_KEY,
            $all
        );
    }

    /**
     * Resolves the saved mapping for a form into a standard-keyed data array
     * suitable for merging into an integration's readFormData() output.
     *
     * For each saved field_id => standard_field pair, $value_getter is invoked
     * with the native field id and must return the submitted value (or null).
     * Empty/null values are skipped. A `full_name` target is split into
     * first_name and last_name.
     *
     * @param string   $tracking_name The integration tracking name.
     * @param string   $form_id       The native form id.
     * @param callable $value_getter  fn( string $field_id ): mixed.
     * @return array<string,mixed> Standard-keyed resolved data.
     */
    public static function resolve( $tracking_name, $form_id, $value_getter ) {
        $mappings = self::get_mappings( $tracking_name, $form_id );
        $result   = array();

        foreach ( $mappings as $field_id => $standard ) {
            $value = call_user_func( $value_getter, $field_id );
            if ( null === $value || '' === $value ) {
                continue;
            }

            if ( FormFieldMappingConfig::FULL_NAME === $standard ) {
                $name = ServerEventFactory::split_name( $value );
                if ( ! empty( $name[0] ) ) {
                    $result['first_name'] = $name[0];
                }
                if ( ! empty( $name[1] ) ) {
                    $result['last_name'] = $name[1];
                }
                continue;
            }

            $result[ $standard ] = $value;
        }

        return $result;
    }
}
