<?php
/**
 * Facebook Pixel Plugin MockFormidableFormFieldValue class.
 *
 * This file contains the main logic for MockFormidableFormFieldValue.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define MockFormidableFormFieldValue class.
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
 * MockFormidableFormFieldValue class.
 */
final class MockFormidableFormFieldValue {
    /**
     * The form field instance.
     *
     * @var MockFormidableFormField
     */
    private $field;

    /**
     * The saved value of the form field.
     *
     * @var mixed
     */
    private $saved_value;

    /**
     * Creates a new instance of the MockFormidableFormFieldValue class.
     *
     * @param MockFormidableFormField $field       The form field instance.
     * @param mixed                   $saved_value The saved
     * value of the form field.
     */
    public function __construct( $field, $saved_value ) {
        $this->field       = $field;
        $this->saved_value = $saved_value;
    }

    /**
     * Retrieves the form field instance.
     *
     * @return MockFormidableFormField The form field instance.
     */
    public function get_field() {
        return $this->field;
    }

    /**
     * Retrieves the saved value of the form field.
     *
     * @return mixed The saved value of the form field.
     */
    public function get_saved_value() {
        return $this->saved_value;
    }
}
