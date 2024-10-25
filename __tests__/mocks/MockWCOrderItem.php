<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase WordPress.Files.FileName.InvalidClassFileName
/**
 * Facebook Pixel Plugin MockWCOrderItem class.
 *
 * This file contains the main logic for MockWCOrderItem.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define MockWCOrderItem class.
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
 * MockWCOrderItem class.
 */
final class MockWCOrderItem {

	/**
	 * The product ID.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * The quantity of the product.
	 *
	 * @var int
	 */
	private $quantity;

	/**
	 * The total value of the product.
	 *
	 * @var int
	 */
	private $total;

	/**
	 * Initializes the MockWCOrderItem object.
	 *
	 * @param int $id       The product ID.
	 * @param int $quantity The quantity of the product.
	 * @param int $total    The total value of the product.
	 */
	public function __construct( $id, $quantity, $total ) {
		$this->id       = $id;
		$this->quantity = $quantity;
		$this->total    = $total;
	}

	/**
	 * Retrieves the product ID of the order item.
	 *
	 * @return int The product ID.
	 */
	public function get_product_id() {
		return $this->id;
	}

	/**
	 * Retrieves the quantity of the order item.
	 *
	 * @return int The quantity of the order item.
	 */
	public function get_quantity() {
		return $this->quantity;
	}

	/**
	 * Retrieves the total value of the order item.
	 *
	 * @return int The total value of the order item.
	 */
	public function get_total() {
		return $this->total;
	}
}
