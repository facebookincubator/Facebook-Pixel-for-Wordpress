<?php
/**
 * Facebook Pixel Plugin FacebookWordpressFormidableForm class.
 *
 * This file contains the lead-tracking integration for Formidable Forms.
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
 * Lead-tracking integration for the Formidable Forms plugin.
 */
class FacebookWordpressFormidableForm extends TrackableLeadFormIntegrationBase {

    const PLUGIN_FILE      = 'formidable/formidable.php';
    const INTEGRATION_NAME = 'formidable-lite';

    /**
     * Initialize the integration.
     *
     * @return void
     */
    protected function set_up_tracking() {
        if ( FacebookPluginUtils::is_internal_user() ) {
            return;
        }
        add_action( 'frm_after_create_entry', array( $this, 'capture_submitted_form' ), 20, 2 );
    }

    /**
     * Captures a created Formidable Forms entry and sends a Lead event.
     *
     * @param int   $entry_id The created entry identifier.
     * @param mixed $form_id  The form identifier.
     * @return void
     */
    public function capture_submitted_form( $entry_id, $form_id ) {
        $event = $this->generate_event( static::EVENT_NAME, $this->extract_lead_data( $form_id, $entry_id ) );
        $this->deliver( $event );
    }

    /**
     * Reads the form id from the plugin submit-hook arguments.
     *
     * @param mixed ...$args The plugin submit-hook arguments.
     * @return mixed The Formidable form identifier.
     */
    protected function get_form_id( ...$args ) {
        return $args[0];
    }

    /**
     * Yields the submitted form fields as normalized parameter rows.
     *
     * @param mixed ...$args The plugin submit-hook arguments.
     * @return \Generator Normalized form-parameter rows.
     * @throws \Exception When the Formidable FrmEntryValues class is unavailable.
     */
    protected function get_form_param_iterator( ...$args ) {
        if ( ! class_exists( 'FrmEntryValues' ) ) {
            throw new \Exception();
        }
        $entry_id     = $args[1];
        $entry_values = new \FrmEntryValues( $entry_id );
        $field_values = $entry_values->get_field_values();

        if ( empty( $field_values ) ) {
            return;
        }
        foreach ( $field_values as $field_value ) {
            $field = $field_value->get_field();
            yield self::get_iterator_yield_output(
                $field->name,
                $field->type,
                $field_value->get_saved_value(),
                'description',
                $field->description
            );
        }
    }

    /**
     * Hard-coded extraction of Lead data used when no mapping is configured.
     *
     * @param iterable $form_param_iterator Normalized form-parameter rows.
     * @return array Normalized lead data keyed by Lead parameter name.
     */
    protected function extract_lead_data_fallback( $form_param_iterator ) {
        $result = array();
        foreach ( $form_param_iterator as $form_param ) {
            if ( empty( $form_param ) ) {
                continue;
            }
            $name        = $form_param['name'];
            $type        = $form_param['type'];
            $value       = $form_param['value'];
            $description = isset( $form_param['description'] ) ? $form_param['description'] : '';

            if ( 'email' === $type ) {
                $result['email'] = $value;
            } elseif ( 'phone' === $type ) {
                $result['phone'] = $value;
            } elseif ( 'text' === $type ) {
                if ( 'Last' === $name && 'Last' === $description ) {
                    $result['last_name'] = $value;
                } elseif ( 'Name' === $name && 'First' === $description ) {
                    $result['first_name'] = $value;
                }
            } elseif ( 'address' === $type ) {
                $result = array_merge( $result, $this->get_address_param_breakdown( $value ) );
            }
        }
        return $result;
    }

    /**
     * Breaks an address field value into its individual Lead parameters.
     *
     * @param array $address_field_value The address field value, keyed by
     *                                   'city', 'state', 'country' and 'zip'.
     * @return array Normalized address data keyed by Lead parameter name.
     */
    private function get_address_param_breakdown( $address_field_value ) {
        $result = array();
        $value  = $address_field_value;
        if ( isset( $value['city'] ) ) {
            $result['city'] = $value['city'];
        }
        if ( isset( $value['state'] ) ) {
            $result['state'] = $value['state'];
        }
        if ( isset( $value['country'] ) && 2 === strlen( $value['country'] ) ) {
            $result['country'] = $value['country'];
        }
        if ( isset( $value['zip'] ) ) {
            $result['zip'] = $value['zip'];
        }
        return $result;
    }
}
