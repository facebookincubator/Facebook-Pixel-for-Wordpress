<?php
/**
 * Facebook Pixel Plugin FormFieldMappingConfig class.
 *
 * This file contains the registry of standard target fields that a form
 * field can be mapped to from the admin Field Mapping screen.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FormFieldMappingConfig class.
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
 * Class FormFieldMappingConfig
 *
 * Registry of the standard CAPI/Pixel fields that a form field can be mapped
 * to. The keys match the data keys understood by
 * ServerEventFactory::safe_create_event(), with the single exception of
 * `full_name`, which is a UI convenience that resolves into first_name and
 * last_name via ServerEventFactory::split_name().
 *
 * Instantiable so the target-field set can be substituted (e.g. in tests or
 * a future extensible/filterable registry) rather than reached statically.
 * The field keys remain class constants for convenient reference.
 */
class FormFieldMappingConfig {
    // User-data (PII) standard fields.
    const FIRST_NAME    = 'first_name';
    const LAST_NAME     = 'last_name';
    const FULL_NAME     = 'full_name';
    const EMAIL         = 'email';
    const PHONE         = 'phone';
    const CITY          = 'city';
    const STATE         = 'state';
    const ZIP           = 'zip';
    const COUNTRY       = 'country';
    const GENDER        = 'gender';
    const DATE_OF_BIRTH = 'date_of_birth';
    const EXTERNAL_ID   = 'external_id';

    // Custom-data standard fields.
    const VALUE            = 'value';
    const CURRENCY         = 'currency';
    const CONTENT_NAME     = 'content_name';
    const CONTENT_CATEGORY = 'content_category';
    const CONTENT_IDS      = 'content_ids';

    /**
     * Filter hook name used to customize the grouped target fields.
     */
    const TARGET_FIELDS_FILTER = 'facebook_pixel_form_mapping_target_fields';

    /**
     * Returns the user-data target fields as key => human label.
     *
     * @return array<string,string>
     */
    public function get_user_data_fields() {
        return array(
            self::FIRST_NAME    => 'First Name',
            self::LAST_NAME     => 'Last Name',
            self::FULL_NAME     => 'Full Name (auto-split)',
            self::EMAIL         => 'Email',
            self::PHONE         => 'Phone',
            self::CITY          => 'City',
            self::STATE         => 'State',
            self::ZIP           => 'Zip / Postal Code',
            self::COUNTRY       => 'Country',
            self::GENDER        => 'Gender',
            self::DATE_OF_BIRTH => 'Date of Birth',
            self::EXTERNAL_ID   => 'External ID',
        );
    }

    /**
     * Returns the custom-data target fields as key => human label.
     *
     * @return array<string,string>
     */
    public function get_custom_data_fields() {
        return array(
            self::VALUE            => 'Value',
            self::CURRENCY         => 'Currency',
            self::CONTENT_NAME     => 'Content Name',
            self::CONTENT_CATEGORY => 'Content Category',
            self::CONTENT_IDS      => 'Content IDs',
        );
    }

    /**
     * Returns all target fields (flattened across every group) as
     * key => label. Reflects any fields added/removed via the
     * TARGET_FIELDS_FILTER filter, so it is the single source of truth for
     * both the UI and validation.
     *
     * @return array<string,string>
     */
    public function get_all_fields() {
        $all = array();
        foreach ( $this->get_grouped_fields() as $fields ) {
            if ( is_array( $fields ) ) {
                $all = array_merge( $all, $fields );
            }
        }
        return $all;
    }

    /**
     * Returns the grouped target fields for rendering option groups.
     *
     * The default groups (User Data, Custom Data) are passed through the
     * TARGET_FIELDS_FILTER filter so other plugins or site owners can add,
     * relabel, or remove target fields and groups.
     *
     * @return array<string,array<string,string>>
     */
    public function get_grouped_fields() {
        $groups = array(
            'User Data'   => $this->get_user_data_fields(),
            'Custom Data' => $this->get_custom_data_fields(),
        );

        /**
         * Filters the standard target fields shown in the Form Field Mapping
         * screen, grouped by section label.
         *
         * Whatever this filter returns becomes both the set of options
         * rendered in the UI and the set of keys accepted as valid mapping
         * targets. Each group is an array of field_key => human label.
         *
         * Note: a field whose key is one of the plugin's built-in standard
         * keys (email, first_name, value, etc.) is forwarded to the Pixel /
         * Conversions API event. A brand-new custom key will appear in the UI
         * and be accepted/stored, but is only transmitted once the event
         * pipeline (ServerEventFactory) understands it.
         *
         * Example — add a field and a custom group:
         *
         *     add_filter(
         *         'facebook_pixel_form_mapping_target_fields',
         *         function ( $groups ) {
         *             $groups['User Data']['email'] = 'Email address';
         *             $groups['Loyalty'] = array(
         *                 'loyalty_tier' => 'Loyalty Tier',
         *             );
         *             return $groups;
         *         }
         *     );
         *
         * @param array<string,array<string,string>> $groups Grouped target
         *                                                    fields keyed by
         *                                                    section label.
         */
        $groups = apply_filters( self::TARGET_FIELDS_FILTER, $groups );

        return is_array( $groups ) ? $groups : array();
    }

    /**
     * Checks whether the given standard field key is a valid target.
     *
     * @param string $field The standard field key to validate.
     * @return bool True if the field is a recognized target.
     */
    public function is_valid_target( $field ) {
        return array_key_exists( $field, $this->get_all_fields() );
    }
}
