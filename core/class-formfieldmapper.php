<?php
/**
 * Facebook Pixel Plugin FormFieldMapper class.
 *
 * Stores per-form mappings from a form plugin's native field ids to Meta Lead
 * parameters (e.g. 'your-email' => 'email').
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

namespace FacebookPixelPlugin\Core;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class FormFieldMapper
 */
class FormFieldMapper {
    /**
     * Mapping targets that are not Lead parameters themselves but expand into
     * several of them when a form is submitted: compound target => the Lead
     * parameters it produces. Only the expanded parameters reach the event.
     *
     * TODO: an 'address' compound (city/state/zip/country) needs each form
     * plugin's own breakdown of its address value, so it is not offered yet.
     * See TrackableLeadFormMappingTest::testCompositeAddressMappingIsExpanded.
     *
     * @var array<string,string[]>
     */
    const COMPOUND_TARGETS = array(
        'full_name' => array( 'first_name', 'last_name' ),
    );

    /**
     * Storage backend for the mappings.
     *
     * @var FormFieldMappingStore
     */
    private $store;

    /**
     * Constructor.
     *
     * @param FormFieldMappingStore|null $store Optional storage backend. Defaults
     *                                          to the WordPress option store.
     */
    public function __construct( $store = null ) {
        $this->store = $store instanceof FormFieldMappingStore
            ? $store
            : new OptionFormFieldMappingStore();
    }

    /**
     * The targets a form field may be mapped to: every Lead parameter (the
     * user-data fields ServerEventFactory hashes for matching) plus the
     * compound targets.
     *
     * @return string[]
     */
    public static function get_valid_targets() {
        return array_merge(
            ServerEventFactory::get_user_data_field_names(),
            array_keys( self::COMPOUND_TARGETS )
        );
    }

    /**
     * Whether a form field may be mapped to the given target.
     *
     * @param string $target The target name.
     * @return bool
     */
    public static function is_valid_target( $target ) {
        return in_array( $target, self::get_valid_targets(), true );
    }

    /**
     * Returns the full mapping store.
     *
     * Shape:
     * [
     *   '<integration_name>' => [
     *     '<form_id>' => [
     *       'form_title' => '<title>',
     *       'mappings'   => [ '<field_id>' => '<target>' ],
     *     ],
     *   ],
     * ]
     *
     * @return array The full mapping store.
     */
    public function get_all() {
        return $this->store->read();
    }

    /**
     * Returns the field_id => target mappings saved for a form.
     *
     * @param string $integration_name The integration name (INTEGRATION_NAME).
     * @param string $form_id          The form plugin's native form id.
     * @return array<string,string> The mappings, or an empty array.
     */
    public function get_mappings( $integration_name, $form_id ) {
        $all     = $this->get_all();
        $form_id = (string) $form_id;
        if ( isset( $all[ $integration_name ][ $form_id ]['mappings'] )
            && is_array( $all[ $integration_name ][ $form_id ]['mappings'] ) ) {
            return $all[ $integration_name ][ $form_id ]['mappings'];
        }
        return array();
    }

    /**
     * Returns a flat list of all saved mappings for the admin overview.
     *
     * Each row: integration_name, form_id, form_title, mapped_count.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_flat_list() {
        $rows = array();
        foreach ( $this->get_all() as $integration_name => $forms ) {
            if ( ! is_array( $forms ) ) {
                continue;
            }
            foreach ( $forms as $form_id => $entry ) {
                $mappings = isset( $entry['mappings'] ) && is_array( $entry['mappings'] )
                    ? $entry['mappings']
                    : array();
                $rows[]   = array(
                    'integration_name' => (string) $integration_name,
                    'form_id'          => (string) $form_id,
                    'form_title'       => isset( $entry['form_title'] )
                        ? (string) $entry['form_title'] : '',
                    'mapped_count'     => count( $mappings ),
                );
            }
        }
        return $rows;
    }

    /**
     * Saves the mapping for a form, replacing any existing one, so each form
     * has at most one mapping.
     *
     * Invalid targets are dropped. If nothing valid remains, the form's entry
     * is removed instead.
     *
     * @param string               $integration_name The integration name.
     * @param string               $form_id          The native form id.
     * @param string               $form_title       The form title, for display.
     * @param array<string,string> $mappings         field_id => target.
     * @return bool True on success.
     */
    public function save_form_mapping( $integration_name, $form_id, $form_title, $mappings ) {
        $clean = array();
        if ( is_array( $mappings ) ) {
            foreach ( $mappings as $field_id => $target ) {
                $field_id = (string) $field_id;
                $target   = (string) $target;
                if ( '' === $field_id || ! self::is_valid_target( $target ) ) {
                    continue;
                }
                $clean[ $field_id ] = $target;
            }
        }

        if ( empty( $clean ) ) {
            return $this->delete_form_mapping( $integration_name, $form_id );
        }

        $integration_name = (string) $integration_name;
        $all              = $this->get_all();
        if ( ! isset( $all[ $integration_name ] ) || ! is_array( $all[ $integration_name ] ) ) {
            $all[ $integration_name ] = array();
        }
        $all[ $integration_name ][ (string) $form_id ] = array(
            'form_title' => (string) $form_title,
            'mappings'   => $clean,
        );

        return $this->store->write( $all );
    }

    /**
     * Deletes the mapping saved for a form.
     *
     * @param string $integration_name The integration name.
     * @param string $form_id          The native form id.
     * @return bool True on success, including when nothing was saved.
     */
    public function delete_form_mapping( $integration_name, $form_id ) {
        $integration_name = (string) $integration_name;
        $form_id          = (string) $form_id;
        $all              = $this->get_all();

        if ( ! isset( $all[ $integration_name ][ $form_id ] ) ) {
            return true;
        }

        unset( $all[ $integration_name ][ $form_id ] );
        if ( empty( $all[ $integration_name ] ) ) {
            unset( $all[ $integration_name ] );
        }

        return $this->store->write( $all );
    }
}
