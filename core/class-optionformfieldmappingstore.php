<?php
/**
 * Facebook Pixel Plugin OptionFormFieldMappingStore class.
 *
 * Persists form field mappings in a single WordPress option.
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
 * Class OptionFormFieldMappingStore
 */
class OptionFormFieldMappingStore implements FormFieldMappingStore {
    /**
     * Reads the full mapping store from the WordPress option.
     *
     * @return array The stored mappings (empty array when none).
     */
    public function read() {
        $stored = \get_option(
            FacebookPluginConfig::FORM_FIELD_MAPPINGS_KEY,
            array()
        );
        return is_array( $stored ) ? $stored : array();
    }

    /**
     * Persists the full mapping store to the WordPress option. The option is
     * not autoloaded: it is only read on form submissions and in wp-admin.
     *
     * @param array $store The full mapping store to persist.
     * @return bool True on success.
     */
    public function write( $store ) {
        // update_option() returns false for an unchanged value; re-saving the
        // same mapping is still a success.
        if ( $this->read() === $store ) {
            return true;
        }
        return \update_option(
            FacebookPluginConfig::FORM_FIELD_MAPPINGS_KEY,
            $store,
            false
        );
    }
}
