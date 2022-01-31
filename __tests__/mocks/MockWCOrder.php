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

final class MockWCOrder {

  private $items = array();
  private $total = 0;
  private $first_name;
  private $last_name;
  private $email;
  private $phone;
  private $city;
  private $postcode;
  private $state;
  private $country;

  public function __construct($first_name, $last_name, $email, $phone, $city,
    $postcode, $state, $country) {
    $this->first_name = $first_name;
    $this->last_name = $last_name;
    $this->email = $email;
    $this->phone = $phone;
    $this->city = $city;
    $this->postcode = $postcode;
    $this->state = $state;
    $this->country = $country;
  }

  public function get_billing_first_name(){
    return $this->first_name;
  }

  public function get_billing_last_name(){
    return $this->last_name;
  }

  public function get_billing_email(){
    return $this->email;
  }

  public function get_billing_postcode(){
    return $this->postcode;
  }

  public function get_billing_state(){
    return $this->state;
  }

  public function get_billing_country(){
    return $this->country;
  }

  public function get_billing_city(){
    return $this->city;
  }

  public function get_billing_phone(){
    return $this->phone;
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
