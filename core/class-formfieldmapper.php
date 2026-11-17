<?php
/**
 * Facebook Pixel Plugin FormFieldMapper class.
 *
 * Stores per-form mappings from a form plugin's native field ids to Meta Lead
 * parameters (e.g. 'your-email' => 'email'), with a decision for every field
 * so fields that were not reviewed can be told apart.
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
     * See TrackableLeadFormIntegrationBase::expand_compound_lead_param().
     *
     * @var array<string,string[]>
     */
    const COMPOUND_TARGETS = array(
        'full_name' => array( 'first_name', 'last_name' ),
        'address'   => array( 'city', 'state', 'zip', 'country' ),
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
     * A saved mapping records a decision for every field the form had when
     * it was saved: the field is included (sent as its target) or excluded
     * (not sent). A field the form gained later has no decision, so the field
     * mapping screen can tell it has not been reviewed. The label is the one
     * the field had when the mapping was saved, so a field the form later
     * loses can still be named. 'rejected' is set on save when no field is
     * included: the form sends no Lead event, which a submission can check
     * without reading the fields.
     *
     * Shape:
     * [
     *   '<integration_name>' => [
     *     '<form_id>' => [
     *       'form_title' => '<title>',
     *       'rejected'   => false,
     *       'fields'     => [
     *         '<field_id>' => [ 'included' => true, 'target' => '<target>', 'label' => '<label>' ],
     *         '<field_id>' => [ 'included' => false, 'label' => '<label>' ],
     *       ],
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
     * Returns the field decisions saved for a form.
     *
     * @param string $integration_name The integration name (INTEGRATION_NAME).
     * @param string $form_id          The form plugin's native form id.
     * @return array<string,array> field_id => ['included' => bool, 'target' =>
     *                             string ('' when excluded), 'label' => string],
     *                             or an empty array.
     */
    public function get_fields( $integration_name, $form_id ) {
        $all     = $this->get_all();
        $form_id = (string) $form_id;
        if ( ! isset( $all[ $integration_name ][ $form_id ]['fields'] )
            || ! is_array( $all[ $integration_name ][ $form_id ]['fields'] ) ) {
            return array();
        }
        $fields = array();
        foreach ( $all[ $integration_name ][ $form_id ]['fields'] as $field_id => $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }
            $target              = isset( $field['target'] ) ? (string) $field['target'] : '';
            $included            = ! empty( $field['included'] ) && self::is_valid_target( $target );
            $fields[ $field_id ] = array(
                'included' => $included,
                'target'   => $included ? $target : '',
                'label'    => isset( $field['label'] ) ? (string) $field['label'] : '',
            );
        }
        return $fields;
    }

    /**
     * Returns the included fields of a form's saved mapping as
     * field_id => target: what a submission of the form sends.
     *
     * @param string $integration_name The integration name (INTEGRATION_NAME).
     * @param string $form_id          The form plugin's native form id.
     * @return array<string,string> The mappings, or an empty array.
     */
    public function get_mappings( $integration_name, $form_id ) {
        $mappings = array();
        foreach ( $this->get_fields( $integration_name, $form_id ) as $field_id => $field ) {
            if ( $field['included'] ) {
                $mappings[ $field_id ] = $field['target'];
            }
        }
        return $mappings;
    }

    /**
     * Whether a mapping is saved for a form, including one with no included
     * field.
     *
     * @param string $integration_name The integration name (INTEGRATION_NAME).
     * @param string $form_id          The form plugin's native form id.
     * @return bool
     */
    public function has_form_mapping( $integration_name, $form_id ) {
        $all = $this->get_all();
        return isset( $all[ $integration_name ][ (string) $form_id ] );
    }

    /**
     * Whether a form's saved mapping was rejected: no field is included, so
     * the form sends no Lead event. False for a form without a saved mapping.
     *
     * @param string $integration_name The integration name (INTEGRATION_NAME).
     * @param string $form_id          The form plugin's native form id.
     * @return bool
     */
    public function is_form_rejected( $integration_name, $form_id ) {
        $all = $this->get_all();
        return ! empty( $all[ $integration_name ][ (string) $form_id ]['rejected'] );
    }

    /**
     * Saves the mapping for a form, replacing any existing one, so each form
     * has at most one mapping.
     *
     * Each field gets a decision: a valid target includes it, '' excludes
     * it. A field with an invalid target gets no decision. A mapping with no
     * included field is still saved: it means the form was rejected and sends
     * no Lead event. Only delete_form_mapping() returns a form to automatic
     * detection.
     *
     * @param string               $integration_name The integration name.
     * @param string               $form_id          The native form id.
     * @param string               $form_title       The form title, for display.
     * @param array<string,string> $decisions        field_id => target, or ''
     *                                               for a field that is not sent.
     * @param array<string,string> $labels           Optional field_id => label.
     * @return bool True on success.
     */
    public function save_form_mapping( $integration_name, $form_id, $form_title, $decisions, $labels = array() ) {
        $fields   = array();
        $rejected = true;
        if ( is_array( $decisions ) ) {
            foreach ( $decisions as $field_id => $target ) {
                $field_id = (string) $field_id;
                $target   = (string) $target;
                if ( '' === $field_id || ( '' !== $target && ! self::is_valid_target( $target ) ) ) {
                    continue;
                }
                $field    = '' === $target
                    ? array( 'included' => false )
                    : array(
                        'included' => true,
                        'target'   => $target,
                    );
                $rejected = $rejected && '' === $target;
                if ( is_array( $labels ) && isset( $labels[ $field_id ] ) && '' !== trim( (string) $labels[ $field_id ] ) ) {
                    $field['label'] = trim( (string) $labels[ $field_id ] );
                }
                $fields[ $field_id ] = $field;
            }
        }

        $integration_name = (string) $integration_name;
        $all              = $this->get_all();
        if ( ! isset( $all[ $integration_name ] ) || ! is_array( $all[ $integration_name ] ) ) {
            $all[ $integration_name ] = array();
        }
        $all[ $integration_name ][ (string) $form_id ] = array(
            'form_title' => (string) $form_title,
            'rejected'   => $rejected,
            'fields'     => $fields,
        );

        return $this->write( $all );
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

        return $this->write( $all );
    }

    /**
     * Stores every mapping. A change to them changes what Lead events send,
     * so the cached Lead event quality is dropped and read again from Meta.
     *
     * @param array $all integration_name => form_id => mapping row.
     * @return bool True on success.
     */
    private function write( $all ) {
        if ( ! $this->store->write( $all ) ) {
            return false;
        }
        LeadEventQuality::clear_cache();
        return true;
    }
}
