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
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\PixelRenderer;
use FacebookAds\Object\ServerSide\Content;

class FacebookWordpressWooCommerce extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'facebook-for-woocommerce/facebook-for-woocommerce.php';
  const TRACKING_NAME = 'woocommerce';

  // Being consistent with the WooCommerce plugin
  const FB_ID_PREFIX = 'wc_post_id_';

  public static function injectPixelCode() {
    // Add the hooks only if the WooCommerce plugin is not active
    if(!self::isFacebookForWooCommerceActive()) {
      add_action('woocommerce_after_checkout_form',
        array(__CLASS__, 'trackInitiateCheckout'),
        40);

      add_action( 'woocommerce_add_to_cart',
        array(__CLASS__, 'trackAddToCartEvent'),
        40, 4 );
    }
  }

  public static function trackAddToCartEvent(
    $cart_item_key, $product_id, $quantity, $variation_id) {
    if (FacebookPluginUtils::isAdmin()) {
      return;
    }

    $server_event = ServerEventFactory::safeCreateEvent(
      'AddToCart',
      array(__CLASS__, 'createAddToCartEvent'),
      array($cart_item_key, $product_id, $quantity),
      self::TRACKING_NAME
    );

    FacebookServerSideEvent::getInstance()->track($server_event);
  }

  public static function createAddToCartEvent(
    $cart_item_key, $product_id, $quantity)
  {
    $event_data = FacebookPluginUtils::getLoggedInUserInfo();
    $event_data['content_type'] = 'product';
    $event_data['currency'] = \get_woocommerce_currency();

    $cart_item = self::getCartItem($cart_item_key);
    if (!empty($cart_item_key)) {
      $event_data['content_ids'] =
        array(self::getProductId($cart_item['data']));
      $event_data['value'] = self::getAddToCartValue($cart_item, $quantity);
    }

    return $event_data;
  }

  public static function trackInitiateCheckout() {
    if (FacebookPluginUtils::isAdmin()) {
      return;
    }

    $server_event = ServerEventFactory::safeCreateEvent(
      'InitiateCheckout',
      array(__CLASS__, 'createInitiateCheckoutEvent'),
      array(),
      self::TRACKING_NAME
    );

    FacebookServerSideEvent::getInstance()->track($server_event);
  }

  public static function createInitiateCheckoutEvent() {
    $event_data = FacebookPluginUtils::getLoggedInUserInfo();
    $event_data['content_type'] = 'product';
    $event_data['currency'] = \get_woocommerce_currency();

    if ($cart = WC()->cart) {
      $event_data['num_items'] = $cart->get_cart_contents_count();
      $event_data['value'] = $cart->total;
      $event_data['content_ids'] = self::getContentIds($cart);
      $event_data['contents'] = self::getContents($cart);
    }

    return $event_data;
  }

  private static function getContentIds($cart) {
    $product_ids = [];
    foreach ($cart->get_cart() as $item) {
      if (!empty($item['data'])) {
        $product_ids[] = self::getProductId($item['data']);
      }
    }

    return $product_ids;
  }

  private static function getContents($cart) {
    $contents = [];
    foreach ($cart->get_cart() as $item) {
      if (!empty($item['data']) && !empty($item['quantity'])) {
        $content = new Content();
        $content->setProductId(self::getProductId($item['data']));
        $content->setQuantity($item['quantity']);
        $content->setItemPrice($item['line_total'] / $item['quantity']);

        $contents[] = $content;
      }
    }

    return $contents;
  }

  private static function getProductId($product) {
    $woo_id = $product->get_id();

    return $product->get_sku() ?
      $product->get_sku() . '_' . $woo_id
      : self::FB_ID_PREFIX . $woo_id;
  }

  private static function isFacebookForWooCommerceActive() {
    return in_array(
      'facebook-for-woocommerce/facebook-for-woocommerce.php',
      get_option('active_plugins'));
  }
}
