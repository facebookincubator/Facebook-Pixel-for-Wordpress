<?php
/**
 * Facebook Pixel Plugin MockWC class.
 *
 * This file contains the main logic for MockWC.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define MockWC class.
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
 * MockWC class.
 */
final class MockWC {
	/**
	 * The cart object.
	 *
	 * @var object
	 */
	public $cart = null;

	/**
	 * Initializes the MockWC object.
	 *
	 * @param object $cart The cart object to use.
	 */
	public function __construct( $cart ) {
		$this->cart = $cart;
	}
}
