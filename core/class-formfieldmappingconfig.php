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
 * Defines the standard CAPI/Pixel fields that a form field can be mapped to.
 * The keys match the data keys understood by
 * ServerEventFactory::safe_create_event(), with the single exception of
 * `full_name`, which is a UI convenience that resolves into first_name and
 * last_name via ServerEventFactory::split_name().
 */
abstract class FormFieldMappingConfig {
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
     * Returns the user-data target fields as key => human label.
     *
     * @return array<string,string>
     */
    public static function get_user_data_fields() {
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
    public static function get_custom_data_fields() {
        return array(
            self::VALUE            => 'Value',
            self::CURRENCY         => 'Currency',
            self::CONTENT_NAME     => 'Content Name',
            self::CONTENT_CATEGORY => 'Content Category',
            self::CONTENT_IDS      => 'Content IDs',
        );
    }

    /**
     * Returns all target fields (user-data + custom-data) as key => label.
     *
     * @return array<string,string>
     */
    public static function get_all_fields() {
        return array_merge(
            self::get_user_data_fields(),
            self::get_custom_data_fields()
        );
    }

    /**
     * Returns the grouped target fields for rendering option groups.
     *
     * @return array<string,array<string,string>>
     */
    public static function get_grouped_fields() {
        return array(
            'User Data'   => self::get_user_data_fields(),
            'Custom Data' => self::get_custom_data_fields(),
        );
    }

    /**
     * Checks whether the given standard field key is a valid target.
     *
     * @param string $field The standard field key to validate.
     * @return bool True if the field is a recognized target.
     */
    public static function is_valid_target( $field ) {
        return array_key_exists( $field, self::get_all_fields() );
    }
}
