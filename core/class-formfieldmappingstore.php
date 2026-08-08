<?php
/**
 * Facebook Pixel Plugin FormFieldMappingStore interface.
 *
 * Storage seam for form field mappings, allowing the persistence backend to
 * be swapped (e.g. with an in-memory fake in tests).
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FormFieldMappingStore interface.
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
 * Interface FormFieldMappingStore
 */
interface FormFieldMappingStore {
    /**
     * Reads the full mapping store.
     *
     * @return array The stored mappings (empty array when none).
     */
    public function read();

    /**
     * Persists the full mapping store.
     *
     * @param array $store The full mapping store to persist.
     * @return bool True on success.
     */
    public function write( $store );
}
