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
use FacebookPixelPlugin\Core\FacebookPluginUtils;

class FacebookWordpressWPECommerce extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'wp-e-commerce/wp-e-commerce.php';
  const TRACKING_NAME = 'wp-e-commerce';

  public static function injectPixelCode() {
    // AddToCart
    add_action('wpsc_add_to_cart_json_response',
      array(__CLASS__, 'injectAddToCartEvent'), 11);

    // InitiateCheckout
    self::addPixelFireForHook(array(
      'hook_name' => 'wpsc_before_shopping_cart_page',
      'classname' => __CLASS__,
      'inject_function' => 'injectInitiateCheckoutEvent'));

    // Purchase
    add_action(
      'wpsc_transaction_results_shutdown',
      array(__CLASS__, 'injectPurchaseEvent'), 11, 3);
  }

  // Event hook for AddToCart.
  public static function injectAddToCartEvent($response) {
    if (FacebookPluginUtils::isAdmin()) {
      return $response;
    }
    $product_id = $response['product_id'];
    $params = static::getParametersForCart($product_id);
    $code = FacebookPixel::getPixelAddToCartCode($params, self::TRACKING_NAME, true);

    $code = sprintf("
    <!-- Facebook Pixel Event Code -->
    %s
    <!-- End Facebook Pixel Event Code -->
         ",
      $code);
    $response['widget_output'] .= $code;
    return $response;
  }

  public static function injectInitiateCheckoutEvent() {
    if (FacebookPluginUtils::isAdmin()) {
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
    if (FacebookPluginUtils::isAdmin() || !$display_to_screen) {
      return;
    }

    $params = static::getParametersForPurchase($purchase_log_object);
    $code = FacebookPixel::getPixelPurchaseCode($params, self::TRACKING_NAME, true);

    printf("
<!-- Facebook Pixel Event Code -->
%s
<!-- End Facebook Pixel Event Code -->
     ",
      $code);
  }

  private static function getParametersForPurchase($purchase_log_object) {
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

  private static function getParametersForCart($product_id) {
    global $wpsc_cart;
    $cart_items = $wpsc_cart->get_items();
    foreach ($cart_items as $item) {
      if ($item->product_id === $product_id) {
        $unit_price = $item->unit_price;
        break;
      }
    }

    $params = array(
      'content_ids' => array($product_id),
      'content_type' => 'product',
      'currency' => function_exists('\wpsc_get_currency_code') ? \wpsc_get_currency_code() : 'Unknown',
      'value' => $unit_price,
    );

    return $params;
  }
}
