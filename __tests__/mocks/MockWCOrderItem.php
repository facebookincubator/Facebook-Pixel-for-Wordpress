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

final class MockWCOrderItem {
  private $id;
  private $quantity;
  private $total;

  public function __construct($id, $quantity, $total) {
    $this->id = $id;
    $this->quantity = $quantity;
    $this->total = $total;
  }

  public function get_product_id() {
    return $this->id;
  }

  public function get_quantity() {
    return $this->quantity;
  }

  public function get_total() {
    return $this->total;
  }
}
