<?php
/**
 * Facebook Pixel Plugin OptionFormFieldMappingStore class.
 *
 * Default FormFieldMappingStore backed by the WordPress options table.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define OptionFormFieldMappingStore class.
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
 * Class OptionFormFieldMappingStore
 */
class OptionFormFieldMappingStore implements FormFieldMappingStore {
    /**
     * Reads the mapping store from the WordPress options table.
     *
     * @return array The stored mappings (empty array when none/invalid).
     */
    public function read() {
        $stored = \get_option(
            FacebookPluginConfig::FORM_FIELD_MAPPINGS_KEY,
            array()
        );
        return is_array( $stored ) ? $stored : array();
    }

    /**
     * Persists the mapping store to the WordPress options table.
     *
     * @param array $store The full mapping store to persist.
     * @return bool True on success.
     */
    public function write( $store ) {
        return \update_option(
            FacebookPluginConfig::FORM_FIELD_MAPPINGS_KEY,
            $store
        );
    }
}
