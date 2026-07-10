<?php
/**
 * Facebook Pixel Plugin EasyDigitalDownloadsIntegrationHelper class.
 *
 * This file contains helper methods that wrap Easy Digital Downloads functions
 * used by the tracking integration.
 *
 * @package FacebookPixelPlugin
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

namespace FacebookPixelPlugin\Integration\Helpers;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Helper methods that wrap Easy Digital Downloads functions used by the
 * tracking integration.
 */
class EasyDigitalDownloadsIntegrationHelper {
    /**
     * Retrieves the payment meta for the given payment.
     *
     * @param int $payment_id The Easy Digital Downloads payment ID.
     * @return mixed The payment meta.
     */
    public static function get_payment_meta( $payment_id ) {
        return edd_get_payment_meta( $payment_id );
    }

    /**
     * Retrieves the download object for the given download ID.
     *
     * @param int $download_id The Easy Digital Downloads download ID.
     * @return mixed The download object.
     */
    public static function get_download( $download_id ) {
        return edd_get_download( $download_id );
    }

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
