<?php
/**
 * Facebook Pixel Plugin MockWCProduct class.
 *
 * This file contains the main logic for MockWCProduct.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define MockWCProduct class.
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
 * MockWCProduct class.
 */
final class MockWCProduct {
	/**
	 * The unique identifier of the product.
	 *
	 * @var int|null
	 */
	private $id = null;

	/**
	 * The type of the product.
	 *
	 * @var string|null
	 */
	private $type = null;

	/**
	 * The title of the product.
	 *
	 * @var string|null
	 */
	private $title = null;

	/**
	 * The price of the product.
	 *
	 * @var float|null
	 */
	private $price = null;

	/**
	 * Initializes the MockWCProduct object.
	 *
	 * @param int    $id    The product ID.
	 * @param string $type  Optional. The type of the product. Default null.
	 * @param string $title Optional. The title of the product. Default null.
	 * @param float  $price Optional. The price of the product. Default null.
	 */
	public function __construct( $id, $type = null, $title = null, $price = null ) {
		$this->id    = $id;
		$this->type  = $type;
		$this->title = $title;
		$this->price = $price;
	}

	/**
	 * Retrieves the product ID.
	 *
	 * @return int The product ID.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Retrieves the product SKU.
	 *
	 * @return string The product SKU.
	 */
	public function get_sku() {
		return '';
	}

	/**
	 * Retrieves the title of the product.
	 *
	 * @return string The title of the product.
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * Retrieves the price of the product.
	 *
	 * @return float The price of the product.
	 */
	public function get_price() {
		return $this->price;
	}
	/**
	 * Checks if the product is of a certain type.
	 *
	 * @param string $type The type to check against.
	 *
	 * @return bool True if the product is of the given type, false otherwise.
	 */
	public function is_type( $type ) {
		return $this->type === $type;
	}
}
