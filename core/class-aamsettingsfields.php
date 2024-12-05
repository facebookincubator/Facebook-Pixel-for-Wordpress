<?php
/**
 * Facebook Pixel Plugin AAMSettingsFields class.
 *
 * This file contains the main logic for AAMSettingsFields.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define AAMSettingsFields class.
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

/**
 * Class AAMSettingsFields
 */
abstract class AAMSettingsFields {
    const EMAIL         = 'em';
    const FIRST_NAME    = 'fn';
    const LAST_NAME     = 'ln';
    const GENDER        = 'ge';
    const PHONE         = 'ph';
    const CITY          = 'ct';
    const STATE         = 'st';
    const ZIP_CODE      = 'zp';
    const DATE_OF_BIRTH = 'db';
    const COUNTRY       = 'country';
    const EXTERNAL_ID   = 'external_id';

    /**
     * Return all the fields which are part of the AAMSettings.
     *
     * @return string[] An array of all AAMSettings fields.
     */
    public static function get_all_fields() {
        return array(
            self::EMAIL,
            self::FIRST_NAME,
            self::LAST_NAME,
            self::GENDER,
            self::PHONE,
            self::CITY,
            self::STATE,
            self::ZIP_CODE,
            self::DATE_OF_BIRTH,
            self::COUNTRY,
            self::EXTERNAL_ID,
        );
    }
}
