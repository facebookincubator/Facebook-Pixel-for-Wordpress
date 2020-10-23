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

  const DIV_ID_FOR_AJAX_PIXEL_EVENTS = 'fb-pxl-ajax-code';

  public static function injectPixelCode() {
    // Add the hooks only if the WooCommerce plugin is not active
    if(!self::isFacebookForWooCommerceActive()) {
      add_action('woocommerce_after_checkout_form',
        array(__CLASS__, 'trackInitiateCheckout'),
        40);

      add_action( 'woocommerce_add_to_cart',
        array(__CLASS__, 'trackAddToCartEvent'),
        40, 4);

      add_action( 'woocommerce_thankyou',
        array(__CLASS__, 'trackPurchaseEvent'),
        40);

      add_action( 'woocommerce_payment_complete',
        array(__CLASS__, 'trackPurchaseEvent'),
        40);

      add_action( 'woocommerce_after_single_product',
        array(__CLASS__, 'trackViewContentEvent'),
        40);
      // This div will be used to insert the fbq calls
      // when an event is triggered by an AJAX request
      add_action( 'wp_footer',
        array(__CLASS__, 'addDivForAjaxPixelEvent')
      );
    }
  }

  public static function addDivForAjaxPixelEvent(){
    echo self::getDivForAjaxPixelEvent();
  }

  public static function getDivForAjaxPixelEvent($content = ''){
    return "<div id='".self::DIV_ID_FOR_AJAX_PIXEL_EVENTS."'>"
      .$content."</div>";
  }

  public static function trackViewContentEvent(){
    if(FacebookPluginUtils::isInternalUser()){
      return;
    }

    global $post;
    if (!isset($post->ID)){
      return;
    }

    $product = wc_get_product($post->ID);
    if(!$product){
      return;
    }

    $server_event = ServerEventFactory::safeCreateEvent(
      'ViewContent',
      array(__CLASS__, 'createViewContentEvent'),
      array($product),
      self::TRACKING_NAME
    );

    FacebookServerSideEvent::getInstance()->track($server_event, false);

    self::enqueuePixelCode($server_event);
  }

  public static function createViewContentEvent($product){
    $event_data = self::getPIIFromSession();

    $product_id = self::getProductId($product);
    $content_type = $product->is_type('variable') ? 'product_group' : 'product';

    $event_data['content_type'] = $content_type;
    $event_data['currency'] = \get_woocommerce_currency();
    $event_data['value'] = $product->get_price();
    $event_data['content_ids'] = array($product_id);
    $event_data['content_name'] = $product->get_title();
    $event_data['content_category'] =
      self::getProductCategory($product->get_id());

    return array_filter($event_data);
  }

  private static function getProductCategory($product_id){
    $categories = get_the_terms(
        $product_id,
        'product_cat'
      );
    return count($categories) > 0 ? $categories[0]->name : null;
  }

  public static function trackPurchaseEvent($order_id) {
    if (FacebookPluginUtils::isInternalUser()) {
      return;
    }

    $server_event = ServerEventFactory::safeCreateEvent(
      'Purchase',
      array(__CLASS__, 'createPurchaseEvent'),
      array($order_id),
      self::TRACKING_NAME
    );

    FacebookServerSideEvent::getInstance()->track($server_event);

    self::enqueuePixelCode($server_event);
  }

  public static function createPurchaseEvent($order_id) {
    $order = wc_get_order($order_id);

    $content_type = 'product';
    $product_ids = array();
    $contents = array();

    foreach ($order->get_items() as $item) {
      $product = wc_get_product($item->get_product_id());
      if ('product_group' !== $content_type
        && $product->is_type('variable'))
      {
        $content_type = 'product_group';
      }

      $quantity = $item->get_quantity();
      $product_id = self::getProductId($product);

      $content  = new Content();
      $content->setProductId($product_id);
      $content->setQuantity($quantity);
      $content->setItemPrice($item->get_total() / $quantity);

      $contents[] = $content;
      $product_ids[] = $product_id;
    }

    $event_data = self::getPiiFromBillingInformation($order);
    $event_data['content_type'] = $content_type;
    $event_data['currency'] = \get_woocommerce_currency();
    $event_data['value'] = $order->get_total();
    $event_data['content_ids'] = $product_ids;
    $event_data['contents'] = $contents;

    return $event_data;
  }

  public static function trackAddToCartEvent(
    $cart_item_key, $product_id, $quantity, $variation_id) {
    if (FacebookPluginUtils::isInternalUser()) {
      return;
    }

    $server_event = ServerEventFactory::safeCreateEvent(
      'AddToCart',
      array(__CLASS__, 'createAddToCartEvent'),
      array($cart_item_key, $product_id, $quantity),
      self::TRACKING_NAME
    );

    // When AJAX is used to add an item to the cart
    // The Conversions API event should be sent inmediately
    // because the wp_footer action is not executed
    $is_ajax_request = wp_doing_ajax();

    FacebookServerSideEvent::getInstance()->track($server_event,
      $is_ajax_request);

    // If it is not an ajax request
    // We show the pixel call in the footer
    // Otherwise we add a filter
    // to modify the ajax response
    // and show the fbq call in the div #fb-pxl-ajax-code
    if(!$is_ajax_request){
      self::enqueuePixelCode($server_event);
    }
    else{
      FacebookServerSideEvent::getInstance()->setPendingPixelEvent(
        'addPixelCodeToAddToCartFragment',
        $server_event
      );
      add_filter('woocommerce_add_to_cart_fragments',
        array(__CLASS__, 'addPixelCodeToAddToCartFragment'));
    }
  }

  public static function addPixelCodeToAddToCartFragment($fragments) {
    $server_event =
      FacebookServerSideEvent::getInstance()
        ->getPendingPixelEvent('addPixelCodeToAddToCartFragment');
    if( !is_null($server_event) ){
      $pixel_code = self::generatePixelCode($server_event, true);
      $fragments['#'.self::DIV_ID_FOR_AJAX_PIXEL_EVENTS] =
        self::getDivForAjaxPixelEvent($pixel_code);
    }
    return $fragments;
  }

  public static function createAddToCartEvent(
    $cart_item_key, $product_id, $quantity)
  {
    $event_data = self::getPIIFromSession();
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

    self::enqueuePixelCode($server_event);
  }

  public static function createInitiateCheckoutEvent() {
    $event_data = self::getPIIFromSession();
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

  private static function getPiiFromBillingInformation($order) {
    $pii = array();

    $pii['first_name'] = $order->get_billing_first_name();
    $pii['last_name'] = $order->get_billing_last_name();
    $pii['email'] = $order->get_billing_email();
    $pii['zip'] = $order->get_billing_postcode();
    $pii['state'] = $order->get_billing_state();
    $pii['country'] = $order->get_billing_country();
    $pii['city'] = $order->get_billing_city();
    $pii['phone'] = $order->get_billing_phone();

    return $pii;
  }

  private static function getAddToCartValue($cart_item, $quantity) {
    if (!empty($cart_item)) {
      $price = $cart_item['line_total'] / $cart_item['quantity'];
      return $quantity * $price;
    }

    return null;
  }

  private static function getCartItem($cart_item_key) {
    if (WC()->cart) {
      $cart = WC()->cart->get_cart();
      if (!empty($cart) && !empty($cart[$cart_item_key])) {
        return $cart[$cart_item_key];
      }
    }

    return null;
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

  private static function getPIIFromSession(){
    $event_data = FacebookPluginUtils::getLoggedInUserInfo();
    $user_id = get_current_user_id();
    if($user_id != 0){
      $event_data['city'] = get_user_meta($user_id, 'billing_city', true);
      $event_data['zip'] = get_user_meta($user_id, 'billing_postcode', true);
      $event_data['country'] = get_user_meta($user_id, 'billing_country', true);
      $event_data['state'] = get_user_meta($user_id, 'billing_state', true);
      $event_data['phone'] = get_user_meta($user_id, 'billing_phone', true);
    }
    return array_filter($event_data);
  }

  private static function isFacebookForWooCommerceActive() {
    return in_array(
      'facebook-for-woocommerce/facebook-for-woocommerce.php',
      get_option('active_plugins'));
  }

  public static function generatePixelCode($server_event, $script_tag = false){
    $code = PixelRenderer::render(array($server_event), self::TRACKING_NAME,
      $script_tag);
    $code = sprintf("
<!-- Facebook Pixel Event Code -->
%s
<!-- End Facebook Pixel Event Code -->
      ",
    $code);
    return $code;
  }

  public static function enqueuePixelCode($server_event){
    $code = self::generatePixelCode($server_event, false);
    wc_enqueue_js($code);
    return $code;
  }
}
