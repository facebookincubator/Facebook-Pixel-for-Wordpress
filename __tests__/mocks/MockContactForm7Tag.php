<?php
/**
 * Facebook Pixel Plugin MockContactForm7Tag class.
 *
 * This file contains the main logic for MockContactForm7Tag.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define MockContactForm7Tag class.
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
 * MockContactForm7Tag class.
 */
final class MockContactForm7Tag {
	/**
	 * The base type of the contact form 7 tag.
	 *
	 * @var string
	 */
	public $basetype;
		/**
		 * The name of the contact form 7 tag.
		 *
		 * @var string
		 */
	public $name;

	/**
	 * Initializes the MockContactForm7Tag object.
	 *
	 * @param string $basetype The base type of the contact form 7 tag.
	 * @param string $name     The name of the contact form 7 tag.
	 */
	public function __construct( $basetype, $name ) {
		$this->basetype = $basetype;
		$this->name     = $name;
	}
}
