<?php
/**
 * Facebook Pixel Plugin InMemoryFormFieldMappingStore class.
 *
 * In-memory FormFieldMappingStore for tests — no WordPress option calls.
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

namespace FacebookPixelPlugin\Tests\Mocks;

use FacebookPixelPlugin\Core\FormFieldMappingStore;

/**
 * InMemoryFormFieldMappingStore class.
 */
final class InMemoryFormFieldMappingStore implements FormFieldMappingStore {
    /**
     * The in-memory store contents.
     *
     * @var array
     */
    public $data;

    /**
     * Constructor.
     *
     * @param array $data Initial store contents.
     */
    public function __construct( $data = array() ) {
        $this->data = $data;
    }

    /**
     * @return array The current store contents.
     */
    public function read() {
        return $this->data;
    }

    /**
     * @param array $store The store contents to persist.
     * @return bool Always true.
     */
    public function write( $store ) {
        $this->data = $store;
        return true;
    }
}
