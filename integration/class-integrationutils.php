<?php
/**
 * Facebook Pixel Plugin IntegrationUtils class.
 *
 * This file contains the main logic for IntegrationUtils.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define IntegrationUtils class.
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
 * IntegrationUtils class.
 */
class IntegrationUtils {
    /**
     * Retrieves entry values from Formidable Forms for a given entry ID.
     *
     * This method returns an instance of FrmEntryValues, which contains
     * the field values associated with the specified entry ID.
     *
     * @param int $entry_id The ID of the form entry to retrieve values for.
     * @return \FrmEntryValues The entry values object
     *                         for the specified entry ID.
     */
    public static function get_formidable_forms_entry_values( $entry_id ) {
        return new \FrmEntryValues( $entry_id );
    }
}
