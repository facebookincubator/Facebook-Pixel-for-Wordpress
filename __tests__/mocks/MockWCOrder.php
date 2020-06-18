<?php
/*
 * Copyright (C) 2017-present, Facebook, Inc.
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

final class MockWCOrder {
  public $data = array();
  private $items = array();
  private $total = 0;

  public function __construct($first_name, $last_name, $email, $phone) {
    $this->data['billing'] = array(
      'first_name' => $first_name,
      'last_name' => $last_name,
      'email' => $email,
      'phone' => $phone
    );
  }

  public function add_item($id, $quantity, $total) {
    $item = new MockWCOrderItem($id, $quantity, $total);
    $this->items[] = $item;
    $this->total += $total;
  }

  public function get_items() {
    return $this->items;
  }

  public function get_total() {
    return $this->total;
  }
}
