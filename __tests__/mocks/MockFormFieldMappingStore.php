<?php
/**
 * Facebook Pixel Plugin MockFormFieldMappingStore class.
 *
 * In-memory form field mapping store for tests.
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
 * MockFormFieldMappingStore class.
 */
final class MockFormFieldMappingStore implements FormFieldMappingStore {
    /**
     * The stored mappings.
     *
     * @var array
     */
    public $data;

    /**
     * Number of write() calls.
     *
     * @var int
     */
    public $writes = 0;

    /**
     * Constructor.
     *
     * @param array $data Initial store contents.
     */
    public function __construct( array $data = array() ) {
        $this->data = $data;
    }

    /**
     * Reads the full mapping store.
     *
     * @return array
     */
    public function read() {
        return $this->data;
    }

    /**
     * Persists the full mapping store.
     *
     * @param array $store The full mapping store.
     * @return bool
     */
    public function write( $store ) {
        ++$this->writes;
        $this->data = $store;
        return true;
    }
}
