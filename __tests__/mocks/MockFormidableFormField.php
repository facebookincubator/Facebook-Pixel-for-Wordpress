<?php
/**
 * Facebook Pixel Plugin MockFormidableFormField class.
 *
 * This file contains the main logic for MockFormidableFormField.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define MockFormidableFormField class.
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
 * MockFormidableFormField class.
 */
final class MockFormidableFormField {
	/**
	 * The type of the form field.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * The name of the form field.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * The description of the form field.
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Constructs a new instance of the MockFormidableFormField class.
	 *
	 * @param string $type The type of the form field.
	 * @param string $name The name of the form field.
	 * @param string $description The description of the form field.
	 */
	public function __construct( $type, $name, $description ) {
		$this->type        = $type;
		$this->name        = $name;
		$this->description = $description;
	}
}
