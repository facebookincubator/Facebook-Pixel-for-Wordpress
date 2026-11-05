<?php
/**
 * Facebook Pixel Plugin FormFieldMappingOverview class.
 *
 * Builds the rows of the Form Field Mapping table: every form of the active
 * supported form plugins, plus saved mappings whose plugin or form is gone.
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
 * Class FormFieldMappingOverview
 */
class FormFieldMappingOverview {
    /**
     * No mapping saved: automatic detection decides what is sent.
     *
     * @var string
     */
    const STATUS_AUTOMATIC = 'automatic';

    /**
     * A mapping with at least one field is saved.
     *
     * @var string
     */
    const STATUS_SAVED = 'saved';

    /**
     * A mapping with no fields is saved: the form sends no Lead event.
     *
     * @var string
     */
    const STATUS_REJECTED = 'rejected';

    /**
     * Lists every form of the active supported form plugins with what it
     * sends: its saved mapping or, for a form without one, what automatic
     * detection would send (the suggestion). Saved mappings whose plugin is
     * not active or whose form no longer exists follow, so they can be reset.
     *
     * Each row: integration_name, integration_label, form_id, form_title,
     * status (a STATUS_* value), targets (the targets the form sends, in field
     * order), active (the plugin is active) and missing (the plugin is active
     * but the form is gone).
     *
     * @param array           $integrations Supported lead-form integrations keyed by
     *                                      INTEGRATION_NAME.
     * @param FormFieldMapper $mapper       Mapping storage.
     * @return array<int,array<string,mixed>>
     */
    public static function build( array $integrations, FormFieldMapper $mapper ) {
        $rows   = array();
        $listed = array();
        foreach ( $integrations as $name => $integration ) {
            if ( ! $integration->is_field_mapping_available() ) {
                continue;
            }
            foreach ( $integration->get_forms() as $form ) {
                $saved   = $mapper->has_form_mapping( $name, $form['id'] );
                $mapping = $saved
                    ? $mapper->get_mappings( $name, $form['id'] )
                    : $integration->suggest_field_mapping( $form['id'] );
                $rows[]  = self::row( $name, $integration->get_integration_label(), $form['id'], $form['title'], $saved, $mapping, true, false );

                $listed[ $name ][ $form['id'] ] = true;
            }
        }

        foreach ( $mapper->get_all() as $name => $forms ) {
            if ( ! is_array( $forms ) ) {
                continue;
            }
            foreach ( $forms as $form_id => $entry ) {
                $form_id = (string) $form_id;
                if ( isset( $listed[ $name ][ $form_id ] ) ) {
                    continue;
                }
                $integration = isset( $integrations[ $name ] ) ? $integrations[ $name ] : null;
                $active      = null !== $integration && $integration->is_field_mapping_available();
                $rows[]      = self::row(
                    (string) $name,
                    null !== $integration ? $integration->get_integration_label() : (string) $name,
                    $form_id,
                    isset( $entry['form_title'] ) ? (string) $entry['form_title'] : '',
                    true,
                    $mapper->get_mappings( $name, $form_id ),
                    $active,
                    $active
                );
            }
        }

        return $rows;
    }

    /**
     * Builds one row.
     *
     * @param string $name    Integration name.
     * @param string $label   Integration label.
     * @param string $form_id Form id.
     * @param string $title   Form title.
     * @param bool   $saved   Whether a mapping is saved for the form.
     * @param array  $mapping The saved or suggested mapping (field_id => target).
     * @param bool   $active  Whether the plugin is active.
     * @param bool   $missing Whether the plugin is active but the form is gone.
     * @return array
     */
    private static function row( $name, $label, $form_id, $title, $saved, array $mapping, $active, $missing ) {
        if ( ! $saved ) {
            $status = self::STATUS_AUTOMATIC;
        } else {
            $status = empty( $mapping ) ? self::STATUS_REJECTED : self::STATUS_SAVED;
        }
        return array(
            'integration_name'  => $name,
            'integration_label' => $label,
            'form_id'           => (string) $form_id,
            'form_title'        => $title,
            'status'            => $status,
            'targets'           => array_values( array_unique( array_values( $mapping ) ) ),
            'active'            => $active,
            'missing'           => $missing,
        );
    }
}
