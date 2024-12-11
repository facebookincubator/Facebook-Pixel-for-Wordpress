<?php
/**
 * Facebook Pixel Plugin EDDUtils class.
 *
 * This file contains the main logic for EDDUtils.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define EDDUtils class.
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

namespace FacebookPixelPlugin\Integration;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * EDDUtils class.
 */
class EDDUtils {
  /**
   * Return the currency code.
   *
   * @since 3.0.0
   * @return string The currency code.
   */
  public static function get_currency() {
    return edd_get_currency();
  }

  /**
   * Get the total of the cart.
   *
   * @since 3.0.0
   *
   * @return float The total amount of the cart.
   */
  public static function get_cart_total() {
    return EDD()->cart->get_total();
  }
}
