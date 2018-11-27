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

/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Integration;

defined('ABSPATH') or die('Direct access not allowed');

use FacebookPixelPlugin\Core\FacebookPixel;

class FacebookWordpressWPECommerce extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'wp-e-commerce/wp-e-commerce.php';
  const TRACKING_NAME = 'wp-e-commerce';

  private static $addToCartJS = "
jQuery(function($) {
  $('.wpsc_buy_button').click(function() {
    var item_group = $(this).parents('.group');
    var form = item_group.find('.product_form');
    var content_id = form[0].attributes['name'].value;

    var prodtitle = item_group.find('.wpsc_product_title');
    var content_name = prodtitle[0].innerText;

    var current_price = item_group.find('.currentprice');
    var value = current_price[0].innerText.slice(1);

    var param = {
      'content_ids': [content_id],
      'content_name': content_name,
      'content_type': 'product',
      '%s': '%s',
      'value': value
    };
    if (value) {
        param['currency'] = 'USD';
    }

    %s
  })
})
  ";

  public static function injectPixelCode() {
    // AddToCart
    add_action(
      'wpsc_product_form_fields_begin',
      array(__CLASS__, 'injectAddToCartEventHook'),
      11);


    // InitiateCheckout
    add_action(
      'wpsc_before_shopping_cart_page',
      array(__CLASS__, 'injectInitiateCheckoutEventHook'),
      11);

    // Purchase
    add_action(
      'wpsc_transaction_results_shutdown',
      array(__CLASS__, 'injectPurchaseEvent'), 11, 3);
  }

  // Event hook for AddToCart.
  public static function injectAddToCartEventHook() {
    add_action(
      'wp_footer',
      array(__CLASS__, 'injectAddToCartEvent'),
      11);
  }

  public static function injectAddToCartEvent() {
    if (is_admin()) {
      return;
    }

    $pixel_code = FacebookPixel::getPixelAddToCartCode('param', self::TRACKING_NAME, false);
    $listener_code = sprintf(
      self::$addToCartJS,
      FacebookPixel::FB_INTEGRATION_TRACKING_KEY,
      self::TRACKING_NAME,
      $pixel_code);

    printf("
<!-- Facebook Pixel Event Code -->
<script>
%s
</script>
<!-- End Facebook Pixel Event Code -->
    ",
      $listener_code);
  }

  // Event hook for InitiateCheckout.
  public static function injectInitiateCheckoutEventHook() {
    add_action(
      'wp_footer',
      array(__CLASS__, 'injectInitiateCheckoutEvent'),
      11);
  }

  public static function injectInitiateCheckoutEvent() {
    if (is_admin()) {
      return;
    }

    $code = FacebookPixel::getPixelInitiateCheckoutCode(array(), self::TRACKING_NAME, false);
    printf("
<!-- Facebook Pixel Event Code -->
<script>
%s
</script>
<!-- End Facebook Pixel Event Code -->
      ",
      $code);
  }

  public static function injectPurchaseEvent($purchase_log_object, $session_id, $display_to_screen) {
    if (is_admin() || !$display_to_screen) {
      return;
    }

    $params = self::getParameters($purchase_log_object);
    $code = FacebookPixel::getPixelPurchaseCode($params, self::TRACKING_NAME, true);

    printf("
<!-- Facebook Pixel Event Code -->
%s
<!-- End Facebook Pixel Event Code -->
     ",
     $code);
  }

  private static function getParameters($purchase_log_object) {
    $cart_items = $purchase_log_object->get_items();
    $total_price = $purchase_log_object->get_total();
    $currency = function_exists('\wpsc_get_currency_code') ? \wpsc_get_currency_code() : 'Unknown';
    $item_ids = array();

    foreach ($cart_items as $item) {
      // This is for backwards compatibility
      $item_array = (array) $item;
      $item_ids[] = $item_array['prodid'];
    }

    $params = array(
     'content_ids' => $item_ids,
     'content_type' => 'product',
     'currency' => $currency,
     'value' => $total_price,
   );

   return $params;
  }
}
