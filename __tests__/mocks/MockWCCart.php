<?php
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

final class MockWCCart {
  public $total = 0;
  private $cart = array();
  private $num_items = 0;

  public function add_item($key, $id, $quantity, $price) {
    $item = array(
      'key' => $key,
      'data' => new MockWCProduct($id),
      'quantity' => $quantity,
      'line_total' => $quantity * $price,
    );

    $this->cart[$key] = $item;
    $this->total += ($quantity * $price);
    $this->num_items += $quantity;
  }

  public function get_cart() {
    return $this->cart;
  }

  public function get_cart_contents_count() {
    return $this->num_items;
  }
}
