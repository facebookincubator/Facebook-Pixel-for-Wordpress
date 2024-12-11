<?php
/**
 * Facebook Pixel Plugin MockGravityFormField class.
 *
 * This file contains the main logic for MockGravityFormField.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define MockGravityFormField class.
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
 * MockGravityFormField class.
 */
final class MockGravityFormField {
    /**
     * The type of the form field.
     *
     * @var string
     */
    public $type;

    /**
     * The identifier of the form field.
     *
     * @var string
     */
    public $id;

    /**
     * An array of input fields for the form field.
     *
     * @var array
     */
    public $inputs = array();

    /**
     * Constructs a new instance of the MockGravityFormField class.
     *
     * @param string $type The type of the form field.
     * @param string $id   The identifier of the form field.
     */
    public function __construct( $type, $id ) {
        $this->type = $type;
        $this->id   = $id;
    }

    /**
     * Adds a label to the form field.
     *
     * @param string $label The text of the label.
     * @param string $id    The identifier of the label.
     */
    public function add_label( $label, $id ) {
        $input          = array(
            'label' => $label,
            'id'    => $id,
        );
        $this->inputs[] = $input;
    }
}
