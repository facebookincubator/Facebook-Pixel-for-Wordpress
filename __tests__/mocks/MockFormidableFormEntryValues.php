<?php
/**
 * Facebook Pixel Plugin MockFormidableFormEntryValues class.
 *
 * This file contains the main logic for MockFormidableFormEntryValues.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define MockFormidableFormEntryValues class.
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

namespace FacebookPixelPlugin\Tests\Mocks;

/**
 * MockFormidableFormEntryValues class.
 */
final class MockFormidableFormEntryValues {
    /**
     * An array of field values.
     *
     * @var array
     */
    private $field_values;
    /**
     * A flag indicating whether to throw an exception.
     *
     * @var bool
     */
    private $throw;

    /**
     * Creates a new instance of the MockFormidableFormEntryValues class.
     *
     * @param array $field_values An array of field values.
     */
    public function __construct( $field_values ) {
        $this->field_values = $field_values;
    }

    /**
     * Sets whether to throw an exception when get_field_values() is called.
     *
     * @param bool $status Whether to throw an exception.
     */
    public function set_throw( $status ) {
        $this->throw = $status;
    }

    /**
     * Retrieves the field values.
     *
     * If the 'throw' property is set to true, an exception is thrown.
     *
     * @throws \Exception If an error occurs during field value retrieval.
     *
     * @return array The array of field values.
     */
    public function get_field_values() {
    if ( $this->throw ) {
        throw new \Exception( 'Unable to read field values!' );
    }

        return $this->field_values;
    }
}
