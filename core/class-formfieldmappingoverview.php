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
     * A mapping that includes at least one field is saved.
     *
     * @var string
     */
    const STATUS_SAVED = 'saved';

    /**
     * A mapping with no included field is saved: the form sends no Lead
     * event.
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
     * order), active (the plugin is active), missing (the plugin is active
     * but the form is gone), missing_fields and new_fields (see
     * review_fields(); empty for a form without a saved mapping) and
     * edit_url (the form plugin's editor for the form; '' for a form that is
     * not listed by an active plugin).
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
                $row     = self::row( $name, $integration->get_integration_label(), $form['id'], $form['title'], $saved, $saved && $mapper->is_form_rejected( $name, $form['id'] ), $mapping, true, false );

                // Included fields the form no longer has send nothing, and
                // fields it gained since the save have not been reviewed: list
                // only the targets still sent, and flag the form for review.
                if ( $saved ) {
                    $review                = self::review_fields(
                        $integration->get_form_fields( $form['id'] ),
                        $mapper->get_fields( $name, $form['id'] )
                    );
                    $row['missing_fields'] = $review['missing_fields'];
                    $row['new_fields']     = $review['new_fields'];
                    $row['targets']        = self::targets(
                        array_diff_key( $mapping, array_flip( array_column( $review['missing_fields'], 'id' ) ) )
                    );
                }

                $row['edit_url'] = (string) $integration->get_form_edit_url( $form['id'] );
                $rows[]          = $row;

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
                    $mapper->is_form_rejected( $name, $form_id ),
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
     * @param string $name     Integration name.
     * @param string $label    Integration label.
     * @param string $form_id  Form id.
     * @param string $title    Form title.
     * @param bool   $saved    Whether a mapping is saved for the form.
     * @param bool   $rejected Whether the saved mapping was rejected.
     * @param array  $mapping  The saved or suggested mapping (field_id => target).
     * @param bool   $active   Whether the plugin is active.
     * @param bool   $missing  Whether the plugin is active but the form is gone.
     * @return array
     */
    private static function row( $name, $label, $form_id, $title, $saved, $rejected, array $mapping, $active, $missing ) {
        if ( ! $saved ) {
            $status = self::STATUS_AUTOMATIC;
        } else {
            $status = $rejected ? self::STATUS_REJECTED : self::STATUS_SAVED;
        }
        return array(
            'integration_name'  => $name,
            'integration_label' => $label,
            'form_id'           => (string) $form_id,
            'form_title'        => $title,
            'status'            => $status,
            'targets'           => self::targets( $mapping ),
            'active'            => $active,
            'missing'           => $missing,
            'missing_fields'    => array(),
            'new_fields'        => array(),
            'edit_url'          => '',
        );
    }

    /**
     * Summarizes what needs a review, for the admin notice: forms of an
     * active plugin that were never reviewed (they still use automatic
     * detection, e.g. forms created since the last review), and saved
     * mappings whose form changed (see review_fields()). Changes that do not
     * affect a mapping, such as a relabelled field or a removed field that
     * was not sent, are not counted.
     *
     * The signature identifies exactly these items, so a dismissed notice
     * comes back only when a new item appears; it is '' when nothing needs a
     * review.
     *
     * @param array $rows Rows from build().
     * @return array{unreviewed: int, changed: int, signature: string}
     */
    public static function summarize_review( array $rows ) {
        $unreviewed = 0;
        $changed    = 0;
        $items      = array();
        foreach ( $rows as $row ) {
            if ( ! $row['active'] || $row['missing'] ) {
                continue;
            }
            $form = $row['integration_name'] . '/' . $row['form_id'];
            if ( self::STATUS_AUTOMATIC === $row['status'] ) {
                ++$unreviewed;
                $items[] = 'form:' . $form;
            }
            if ( ! empty( $row['missing_fields'] ) || ! empty( $row['new_fields'] ) ) {
                ++$changed;
                foreach ( $row['missing_fields'] as $field ) {
                    $items[] = 'missing:' . $form . '/' . $field['id'];
                }
                foreach ( $row['new_fields'] as $field ) {
                    $items[] = 'new:' . $form . '/' . $field['id'];
                }
            }
        }
        sort( $items, SORT_STRING );
        return array(
            'unreviewed' => $unreviewed,
            'changed'    => $changed,
            'signature'  => empty( $items ) ? '' : md5( implode( "\n", $items ) ),
        );
    }

    /**
     * Compares a form's current fields with the field decisions of its saved
     * mapping:
     *
     * - missing_fields: included fields the form no longer has (deleted, or
     *   renamed in a plugin that identifies fields by name). Nothing is sent
     *   for them. Rows of ['id' =>, 'label' =>, 'target' =>], with the label
     *   the field had when the mapping was saved ('' if unknown).
     * - new_fields: fields the form has but the saved mapping has no decision
     *   for, i.e. added (or renamed) since it was saved. Nothing is sent from
     *   them until they are reviewed. Rows of ['id' =>, 'label' =>].
     *
     * An excluded field the form no longer has is not listed: nothing was
     * sent from it anyway.
     *
     * @param array $form_fields  The form's fields (get_form_fields() rows).
     * @param array $saved_fields The saved decisions (FormFieldMapper::get_fields()).
     * @return array{missing_fields: array, new_fields: array}
     */
    public static function review_fields( array $form_fields, array $saved_fields ) {
        $present    = array();
        $new_fields = array();
        foreach ( $form_fields as $field ) {
            $id             = (string) $field['id'];
            $present[ $id ] = true;
            if ( ! isset( $saved_fields[ $id ] ) ) {
                $new_fields[] = array(
                    'id'    => $id,
                    'label' => isset( $field['label'] ) ? (string) $field['label'] : '',
                );
            }
        }
        $missing_fields = array();
        foreach ( $saved_fields as $id => $field ) {
            if ( $field['included'] && ! isset( $present[ (string) $id ] ) ) {
                $missing_fields[] = array(
                    'id'     => (string) $id,
                    'label'  => $field['label'],
                    'target' => $field['target'],
                );
            }
        }
        return array(
            'missing_fields' => $missing_fields,
            'new_fields'     => $new_fields,
        );
    }

    /**
     * The distinct targets of a mapping, in field order.
     *
     * @param array $mapping field_id => target.
     * @return string[]
     */
    private static function targets( array $mapping ) {
        return array_values( array_unique( array_values( $mapping ) ) );
    }
}
