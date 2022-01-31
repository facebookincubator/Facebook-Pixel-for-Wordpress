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
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Core\EventIdGenerator;

class FacebookWordpressEasyDigitalDownloads extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'easy-digital-downloads/easy-digital-downloads.php';
  const TRACKING_NAME = 'easy-digital-downloads';

  private static $addToCartJS = "
jQuery(document).ready(function ($) {
  $('.edd-add-to-cart').click(function (e) {
    e.preventDefault();

    var _this = $(this), form = _this.closest('form');
    var download = _this.data('download-id');
    var currency = $('.edd_purchase_' + download + ' meta[itemprop=\'priceCurrency\']').attr('content');
    var form = _this.parents('form').last();
    var value = 0;
    var variable_price = _this.data('variable-price');
    var event_id = form.find(\"input[name='facebook_event_id']\").val();
    if( variable_price == 'yes' ) {
      form.find('.edd_price_option_' + download + ':checked', form).each(function(index) {
        value = $(this).data('price');
      });
    } else {
      if ( _this.data('price') && _this.data('price') > 0 ) {
        value = _this.data('price');
      }
    }

    var param = {
      'content_ids': [download],
      'content_type': 'product',
      'currency': currency,
      '%s': '%s',
      'value': value
    };
    fbq('set', 'agent', '%s', '%s');
    if(event_id){
      fbq('track', 'AddToCart', param, {'eventID': event_id});
    }
    else{
      fbq('track', 'AddToCart', param);
    }
  });
});
";

  public static function injectPixelCode() {
    // AddToCart JS listener
    add_action(
      'edd_after_download_content',
      array(__CLASS__, 'injectAddToCartListener')
    );
    add_action(
      'edd_downloads_list_after',
      array(__CLASS__, 'injectAddToCartListener')
    );

    //Hooks to AddToCart ajax requests
    add_action(
      'wp_ajax_edd_add_to_cart',
      array(__CLASS__, 'injectAddToCartEventAjax'),
      5
    );

    add_action(
      'wp_ajax_nopriv_edd_add_to_cart',
      array(__CLASS__, 'injectAddToCartEventAjax'),
      5
    );

    //Injects a hidden field with event id to send it in AddToCart ajax request
    add_action(
      'edd_purchase_link_top',
      array(__CLASS__, 'injectAddToCartEventId')
    );

    // InitiateCheckout
    self::addPixelFireForHook(array(
      'hook_name' => 'edd_after_checkout_cart',
      'classname' => __CLASS__,
      'inject_function' => 'injectInitiateCheckoutEvent'));

    // Purchase
    add_action(
      'edd_payment_receipt_after',
      array(__CLASS__, 'trackPurchaseEvent'),
      10, 2);

    // ViewContent
    add_action(
      'edd_after_download_content',
      array(__CLASS__, 'injectViewContentEvent'),
      40, 1
    );
  }

  public static function injectAddToCartEventId(){
    if(FacebookPluginUtils::isInternalUser()){
      return;
    }
    $eventId = EventIdGenerator::guidv4();
    printf("<input type=\"hidden\" name=\"facebook_event_id\" value=\"%s\">",
      $eventId);
  }

  public static function injectAddToCartEventAjax(){
    if( isset($_POST['nonce']) && isset($_POST['download_id'])
      && isset($_POST['post_data'])){
      $download_id = absint( $_POST['download_id'] );
      //Adding form validations
      $nonce = sanitize_text_field( $_POST['nonce'] );
      if( wp_verify_nonce($nonce, 'edd-add-to-cart-'.$download_id) === false ){
        return;
      }
      //Getting form data
      parse_str( $_POST['post_data'], $post_data );
      if(isset($post_data['facebook_event_id'])){
        //Starting Conversions API event creation
        $event_id = $post_data['facebook_event_id'];
        $server_event = ServerEventFactory::safeCreateEvent(
          'AddToCart',
          array(__CLASS__, 'createAddToCartEvent'),
          array($download_id),
          self::TRACKING_NAME
        );
        $server_event->setEventId($event_id);
        FacebookServerSideEvent::getInstance()->track($server_event);
      }
    }
  }

  public static function injectAddToCartListener($download_id) {
    if (FacebookPluginUtils::isInternalUser()) {
      return;
    }

    $listener_code = sprintf(
      self::$addToCartJS,
      FacebookPixel::FB_INTEGRATION_TRACKING_KEY,
      self::TRACKING_NAME,
      FacebookWordpressOptions::getAgentString(),
      FacebookWordpressOptions::getPixelId()
    );

    printf("
<!-- Meta Pixel Event Code -->
<script>
%s
</script>
<!-- End Meta Pixel Event Code -->
      ",
      $listener_code);
  }

  public static function injectInitiateCheckoutEvent() {
    if (FacebookPluginUtils::isInternalUser() || !function_exists('EDD')) {
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

  public static function trackPurchaseEvent($payment, $edd_receipt_args) {
    if (FacebookPluginUtils::isInternalUser() || empty($payment->ID)) {
      return;
    }

    $server_event = ServerEventFactory::safeCreateEvent(
      'Purchase',
      array(__CLASS__, 'createPurchaseEvent'),
      array($payment),
      self::TRACKING_NAME
    );
    FacebookServerSideEvent::getInstance()->track($server_event);

    add_action(
      'wp_footer',
       array(__CLASS__, 'injectPurchaseEvent'),
       20
    );
  }

  public static function injectPurchaseEvent() {
    $events = FacebookServerSideEvent::getInstance()->getTrackedEvents();
    $code = PixelRenderer::render($events, self::TRACKING_NAME);

    printf("
<!-- Meta Pixel Event Code -->
%s
<!-- End Meta Pixel Event Code -->
      ",
      $code);
  }

  public static function injectViewContentEvent($download_id) {
    if (FacebookPluginUtils::isInternalUser() || empty($download_id)) {
      return;
    }

    $server_event = ServerEventFactory::safeCreateEvent(
      'ViewContent',
      array(__CLASS__, 'createViewContentEvent'),
      array($download_id),
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

  public static function createInitiateCheckoutEvent() {
    $event_data = FacebookPluginUtils::getLoggedInUserInfo();
    $event_data['currency'] = EDDUtils::getCurrency();
    $event_data['value'] = EDDUtils::getCartTotal();

    return $event_data;
  }

  public static function createPurchaseEvent($payment) {
    $event_data = array();

    $payment_meta = \edd_get_payment_meta($payment->ID);
    if (empty($payment_meta)) {
      return $event_data;
    }

    $event_data['email'] = $payment_meta['email'];
    $event_data['first_name'] = $payment_meta['user_info']['first_name'];
    $event_data['last_name'] = $payment_meta['user_info']['last_name'];

    $content_ids = array();
    $value = 0;
    foreach ($payment_meta['cart_details'] as $item) {
      $content_ids[] = $item['id'];
      $value += $item['price'];
    }

    $event_data['currency'] = $payment_meta['currency'];
    $event_data['value'] = $value;
    $event_data['content_ids'] = $content_ids;
    $event_data['content_type'] = 'product';

    return $event_data;
  }

  public static function createViewContentEvent($download_id){
    $event_data = FacebookPluginUtils::getLoggedInUserInfo();
    $currency = EDDUtils::getCurrency();
    $download = edd_get_download($download_id);
    $title = $download ? $download->post_title : '';

    if (get_post_meta($download_id, '_variable_pricing', true)) {
      $prices = get_post_meta($download_id, 'edd_variable_prices', true);
      $price = array_shift($prices);
      $value = $price['amount'];
    } else {
      $value = get_post_meta($download_id, 'edd_price', true);
    }
    if (!$value) {
      $value = 0;
    }
    $event_data['content_ids'] = [(string)$download_id];
    $event_data['content_type'] = 'product';
    $event_data['currency'] = $currency;
    $event_data['value'] = floatval($value);
    $event_data['content_name'] = $title;
    return $event_data;
  }

  public static function createAddToCartEvent($download_id){
    $event_data = FacebookPluginUtils::getLoggedInUserInfo();
    $currency = EDDUtils::getCurrency();
    $download = edd_get_download($download_id);
    $title = $download ? $download->post_title : '';
    if ( get_post_meta($download_id, '_variable_pricing', true) ) {
      $prices = get_post_meta($download_id, 'edd_variable_prices', true);
      $price = array_shift($prices);
      $value = $price['amount'];
    } else {
      $value = get_post_meta($download_id, 'edd_price', true);
    }
    if (!$value) {
      $value = 0;
    }
    $event_data['content_ids'] = [(string)$download_id];
    $event_data['content_type'] = 'product';
    $event_data['currency'] = $currency;
    $event_data['value'] = $value;
    $event_data['content_name'] = $title;
    return $event_data;
  }
}
