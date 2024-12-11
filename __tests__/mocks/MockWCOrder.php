<?php
/**
 * Facebook Pixel Plugin MockWCOrder class.
 *
 * This file contains the main logic for MockWCOrder.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define MockWCOrder class.
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
 * MockWCOrder class.
 */
final class MockWCOrder {

    /**
     * The items in the order.
     *
     * @var array
     */
    private $items = array();

    /**
     * The total value of the order.
     *
     * @var float
     */
    private $total = 0;

    /**
     * The first name of the order recipient.
     *
     * @var string
     */
    private $first_name;

    /**
     * The last name of the order recipient.
     *
     * @var string
     */
    private $last_name;

    /**
     * The email address of the order recipient.
     *
     * @var string
     */
    private $email;

    /**
     * The phone number of the order recipient.
     *
     * @var string
     */
    private $phone;

    /**
     * The city of the order recipient.
     *
     * @var string
     */
    private $city;

    /**
     * The postcode of the order recipient.
     *
     * @var string
     */
    private $postcode;

    /**
     * The state of the order recipient.
     *
     * @var string
     */
    private $state;

    /**
     * The country of the order recipient.
     *
     * @var string
     */
    private $country;

    /**
     * Initializes a new instance of the MockWCOrder class.
     *
     * @param string $first_name The first name of the order recipient.
     * @param string $last_name  The last name of the order recipient.
     * @param string $email      The email address of the order recipient.
     * @param string $phone      The phone number of the order recipient.
     * @param string $city       The city of the order recipient.
     * @param string $postcode   The postcode of the order recipient.
     * @param string $state      The state of the order recipient.
     * @param string $country    The country of the order recipient.
     */
    public function __construct(
        $first_name,
        $last_name,
        $email,
        $phone,
        $city,
        $postcode,
        $state,
        $country
    ) {
        $this->first_name = $first_name;
        $this->last_name  = $last_name;
        $this->email      = $email;
        $this->phone      = $phone;
        $this->city       = $city;
        $this->postcode   = $postcode;
        $this->state      = $state;
        $this->country    = $country;
    }

    /**
     * Retrieves the billing first name.
     *
     * @return string The billing first name.
     */
    public function get_billing_first_name() {
        return $this->first_name;
    }

    /**
     * Retrieves the billing last name.
     *
     * @return string The billing last name.
     */
    public function get_billing_last_name() {
        return $this->last_name;
    }

    /**
     * Retrieves the billing email address.
     *
     * @return string The billing email address.
     */
    public function get_billing_email() {
        return $this->email;
    }

    /**
     * Retrieves the billing postcode.
     *
     * @return string The billing postcode.
     */
    public function get_billing_postcode() {
        return $this->postcode;
    }

    /**
     * Retrieves the billing state.
     *
     * @return string The billing state.
     */
    public function get_billing_state() {
        return $this->state;
    }

    /**
     * Retrieves the billing country.
     *
     * @return string The billing country.
     */
    public function get_billing_country() {
        return $this->country;
    }

    /**
     * Retrieves the billing city.
     *
     * @return string The billing city.
     */
    public function get_billing_city() {
        return $this->city;
    }

    /**
     * Retrieves the billing phone number.
     *
     * @return string The billing phone number.
     */
    public function get_billing_phone() {
        return $this->phone;
    }

    /**
     * Adds an item to the order.
     *
     * This method creates a new order item with the specified product ID,
     * quantity, and total price, and adds it to the order's item list. It also
     * updates the total value of the order by adding the item's total price.
     *
     * @param int   $id       The product ID.
     * @param int   $quantity The quantity of the product.
     * @param float $total    The total price of the product.
     *
     * @return void
     */
    public function add_item( $id, $quantity, $total ) {
        $item          = new MockWCOrderItem( $id, $quantity, $total );
        $this->items[] = $item;
        $this->total  += $total;
    }

    /**
     * Returns the items in the order.
     *
     * @return array The items in the order. Each item is an instance of
     *               MockWCOrderItem.
     */
    public function get_items() {
        return $this->items;
    }

    /**
     * Returns the total value of the cart.
     *
     * @return float The total value of the cart.
     */
    public function get_total() {
        return $this->total;
    }
}
