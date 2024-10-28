<?php
/**
 * Facebook Pixel Plugin MockWCCart class.
 *
 * This file contains the main logic for MockWCCart.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define MockWCCart class.
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
 * MockWCCart class.
 */
final class MockWCCart {

	/**
	 * The total value of the cart.
	 *
	 * @var int
	 */
	public $total = 0;

	/**
	 * Array of cart items.
	 *
	 * @var array
	 */
	private $cart = array();

	/**
	 * The number of items in the cart.
	 *
	 * @var int
	 */
	private $num_items = 0;

	/**
	 * Adds an item to the cart.
	 *
	 * This method creates a new cart item with the specified key, product ID,
	 * quantity, and price. It updates the cart array, total cart value, and
	 * the number of items in the cart.
	 *
	 * @param string $key      The unique key for the cart item.
	 * @param int    $id       The product ID.
	 * @param int    $quantity The quantity of the product.
	 * @param float  $price    The price of the product.
	 *
	 * @return void
	 */
	public function add_item( $key, $id, $quantity, $price ) {
		$item = array(
			'key'        => $key,
			'data'       => new MockWCProduct( $id ),
			'quantity'   => $quantity,
			'line_total' => $quantity * $price,
		);

		$this->cart[ $key ] = $item;
		$this->total       += ( $quantity * $price );
		$this->num_items   += $quantity;
	}

	/**
	 * Returns the cart items.
	 *
	 * @return array The cart items. Each item is an associative array with the
	 *               following keys:
	 *               - key: The unique key for the item.
	 *               - data: The product object.
	 *               - quantity: The quantity of the product.
	 *               - line_total: The total value of the product line.
	 */
	public function get_cart() {
		return $this->cart;
	}

	/**
	 * Retrieves the number of items in the cart.
	 *
	 * @return int The number of items in the cart.
	 */
	public function get_cart_contents_count() {
		return $this->num_items;
	}
}
