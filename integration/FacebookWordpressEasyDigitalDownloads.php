<?php
/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Integration;

defined('ABSPATH') or die('Direct access not allowed');

use FacebookPixelPlugin\Core\FacebookPixel;

class FacebookWordpressEasyDigitalDownloads extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'easy-digital-downloads/easy-digital-downloads.php';

  private static $downloadID;
  private static $paymentID;

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
      'value': value
    };
    %s
  });
});
";

  public static function injectPixelCode() {
    // AddToCart
    add_action(
      'edd_after_download_content',
      array(__CLASS__, 'injectAddToCartEventHook'),
      11);

    // InitiateCheckout
    add_action(
      'edd_after_checkout_cart',
      array(__CLASS__, 'injectInitiateCheckoutEventHook'),
      11);

    // Purchase
    add_action(
      'edd_payment_receipt_after',
      array(__CLASS__, 'injectPurchaseEventHook'),
      11);

    // ViewContent
    add_action(
      'edd_after_download_content',
      array(__CLASS__, 'injectViewContentEventHook'),
      11);
  }

  public static function injectAddToCartEventHook($download_id) {
    add_action(
      'wp_footer',
      array(__CLASS__, 'injectAddToCartEvent'),
      11);
  }

  public static function injectAddToCartEvent() {
    if (is_admin()) {
      return;
    }

    $pixel_code = FacebookPixel::getPixelAddToCartCode('param', false);
    $listener_code = sprintf(
      self::$addToCartJS,
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

  public static function injectInitiateCheckoutEventHook() {
    add_action(
      'wp_footer',
      array(__CLASS__, 'injectInitiateCheckoutEvent'),
      11);
  }

  public static function injectInitiateCheckoutEvent() {
    if (is_admin() || !function_exists('EDD')) {
      return;
    }

    $currency = edd_get_currency();
    $value = EDD()->cart->get_total();
    $param = array_merge(
      array(
        'currency' => $currency,
        'value' => $value,
      ),
      static::getUserEmailParam());
    $code = FacebookPixel::getPixelInitiateCheckoutCode($param, true);

    printf("
<!-- Facebook Pixel Event Code -->
%s
<!-- End Facebook Pixel Event Code -->
      ",
      $code);
  }

  public static function injectPurchaseEventHook($payment) {
    static::$paymentID = $payment->ID;

    add_action(
      'wp_footer',
      array(__CLASS__, 'injectPurchaseEvent'),
      11);
  }

  public static function injectPurchaseEvent() {
    if (is_admin() || empty(static::$paymentID)) {
      return;
    }

    $payment_meta = edd_get_payment_meta(static::$paymentID);

    $content_ids = array();
    $value = 0;
    foreach ($payment_meta['cart_details'] as $item) {
      $content_ids[] = $item['id'];
      $value += $item['price'];
    }
    $currency = $payment_meta['currency'];
    $param = array_merge(
      array(
        'content_ids' => $content_ids,
        'content_type' => 'product',
        'currency' => $currency,
        'value' => $value,
      ),
      static::getUserEmailParam());
    $code = FacebookPixel::getPixelPurchaseCode($param, true);

    printf("
<!-- Facebook Pixel Event Code -->
%s
<!-- End Facebook Pixel Event Code -->
      ",
      $code);
  }

  public static function injectViewContentEventHook($download_id) {
    static::$downloadID = $download_id;

    add_action(
      'wp_footer',
      array(__CLASS__, 'injectViewContentEvent'),
      11);
  }

  public static function injectViewContentEvent() {
    if (is_admin() || empty(static::$downloadID)) {
      return;
    }

    $currency = edd_get_currency();
    if (get_post_meta(static::$downloadID, '_variable_pricing', true)) { // variable price
      $values = get_post_meta(static::$downloadID, 'edd_variable_prices', true);
      $value = array_shift($values)['amount'];
    } else {
      $value = get_post_meta(static::$downloadID, 'edd_price', true);
    }
    if (!$value) {
      $value = 0;
    }
    $code = FacebookPixel::getPixelViewContentCode(
      array_merge(
        array(
          'content_ids' => array(static::$downloadID),
          'content_type' => 'product',
          'currency' => $currency,
          'value' => $value,
        ),
        static::getUserEmailParam()));

    printf("
<!-- Facebook Pixel Event Code -->
%s
<!-- End Facebook Pixel Event Code -->
      ",
      $code);
  }
}
