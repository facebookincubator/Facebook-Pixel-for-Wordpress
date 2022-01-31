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

/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Integration;

defined('ABSPATH') or die('Direct access not allowed');

use FacebookPixelPlugin\Core\FacebookPixel;
use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\PixelRenderer;

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
    if (FacebookPluginUtils::isInternalUser()) {
      return $response;
    }

    $product_id = $response['product_id'];
    $server_event = ServerEventFactory::safeCreateEvent(
      'AddToCart',
      array(__CLASS__, 'createAddToCartEvent'),
      array($product_id),
      self::TRACKING_NAME
    );
    FacebookServerSideEvent::getInstance()->track($server_event);

    $code = PixelRenderer::render(array($server_event), self::TRACKING_NAME);
    $code = sprintf("
    <!-- Meta Pixel Event Code -->
    %s
    <!-- End Meta Pixel Event Code -->
         ",
      $code);
    $response['widget_output'] .= $code;
    return $response;
  }

  public static function injectInitiateCheckoutEvent() {
    if (FacebookPluginUtils::isInternalUser()) {
      return;
    }

    $server_event = ServerEventFactory::safeCreateEvent(
      'InitiateCheckout',
      array(__CLASS__, 'createInitiateCheckoutEvent'),
      array(),
      self::TRACKING_NAME
    );
    FacebookServerSideEvent::getInstance()->track($server_event);

    $code = PixelRenderer::render(array($server_event), self::TRACKING_NAME);
    printf("
<!-- Meta Pixel Event Code -->
%s
<!-- End Meta Pixel Event Code -->
      ",
      $code);
  }

  public static function injectPurchaseEvent(
    $purchase_log_object,
    $session_id,
    $display_to_screen)
  {
    if (FacebookPluginUtils::isInternalUser() || !$display_to_screen) {
      return;
    }

    $server_event = ServerEventFactory::safeCreateEvent(
      'Purchase',
      array(__CLASS__, 'createPurchaseEvent'),
      array($purchase_log_object),
      self::TRACKING_NAME
    );
    FacebookServerSideEvent::getInstance()->track($server_event);

    $code = PixelRenderer::render(array($server_event), self::TRACKING_NAME);

    printf("
<!-- Meta Pixel Event Code -->
%s
<!-- End Meta Pixel Event Code -->
     ",
      $code);
  }

  public static function createPurchaseEvent($purchase_log_object) {
    $event_data = FacebookPluginUtils::getLoggedInUserInfo();

    $cart_items = $purchase_log_object->get_items();
    $total_price = $purchase_log_object->get_total();
    $currency = function_exists('\wpsc_get_currency_code')
                  ? \wpsc_get_currency_code() : '';

    $item_ids = array();
    foreach ($cart_items as $item) {
      // This is for backwards compatibility
      $item_array = (array) $item;
      $item_ids[] = $item_array['prodid'];
    }

    $event_data['content_ids'] = $item_ids;
    $event_data['content_type'] = 'product';
    $event_data['currency'] = $currency;
    $event_data['value'] = $total_price;

    return $event_data;
  }

  public static function createAddToCartEvent($product_id) {
    $event_data = FacebookPluginUtils::getLoggedInUserInfo();

    global $wpsc_cart;
    $cart_items = $wpsc_cart->get_items();
    foreach ($cart_items as $item) {
      if ($item->product_id === $product_id) {
        $unit_price = $item->unit_price;
        break;
      }
    }

    $event_data['content_ids'] = array($product_id);
    $event_data['content_type'] = 'product';
    $event_data['currency'] =
      function_exists('\wpsc_get_currency_code')
        ? \wpsc_get_currency_code() : '';
    $event_data['value'] = $unit_price;

    return $event_data;
  }

  public static function createInitiateCheckoutEvent() {
    $event_data = FacebookPluginUtils::getLoggedInUserInfo();
    $content_ids = array();

    $value = 0;
    global $wpsc_cart;
    $cart_items = $wpsc_cart->get_items();
    foreach ($cart_items as $item) {
      $content_ids[] = $item->product_id;
      $value += $item->unit_price;
    }

    $event_data['currency'] =
    function_exists('\wpsc_get_currency_code')
      ? \wpsc_get_currency_code() : '';
    $event_data['value'] = $value;
    $event_data['content_ids'] = $content_ids;

    return $event_data;
  }
}
