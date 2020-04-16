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

namespace FacebookPixelPlugin\Core;

defined('ABSPATH') or die('Direct access not allowed');

use ReflectionClass;

class FacebookPixel {
  const ADDPAYMENTINFO = 'AddPaymentInfo';
  const ADDTOCART = 'AddToCart';
  const ADDTOWISHLIST = 'AddToWishlist';
  const COMPLETEREGISTRATION = 'CompleteRegistration';
  const CONTACT = 'Contact';
  const CUSTOMIZEPRODUCT = 'CustomizeProduct';
  const DONATE = 'Donate';
  const FINDLOCATION = 'FindLocation';
  const INITIATECHECKOUT = 'InitiateCheckout';
  const LEAD = 'Lead';
  const PAGEVIEW = 'PageView';
  const PURCHASE = 'Purchase';
  const SCHEDULE = 'Schedule';
  const SEARCH = 'Search';
  const STARTTRIAL = 'StartTrial';
  const SUBMITAPPLICATION = 'SubmitApplication';
  const SUBSCRIBE = 'Subscribe';
  const VIEWCONTENT = 'ViewContent';

  const FB_INTEGRATION_TRACKING_KEY = 'fb_integration_tracking';

  private static $pixelId = '';

  private static $pixelBaseCode = "
<!-- Facebook Pixel Code -->
<script type='text/javascript'>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
</script>
<!-- End Facebook Pixel Code -->
";

  private static $pixelFbqCodeWithoutScript = "
  fbq('%s', '%s'%s%s);
";

  private static $pixelNoscriptCode = "
<!-- Facebook Pixel Code -->
<noscript>
<img height=\"1\" width=\"1\" style=\"display:none\" alt=\"fbpx\"
src=\"https://www.facebook.com/tr?id=%s&ev=%s%s&noscript=1\" />
</noscript>
<!-- End Facebook Pixel Code -->
";

  public static function initialize($pixel_id = '') {
    self::$pixelId = $pixel_id;
  }

  /**
   * Gets FB pixel ID
   */
  public static function getPixelId() {
    return self::$pixelId;
  }

  /**
   * Sets FB pixel ID
   */
  public static function setPixelId($pixel_id) {
    self::$pixelId = $pixel_id;
  }

  /**
   * Gets FB pixel base code
   */
  public static function getPixelBaseCode() {
    return self::$pixelBaseCode;
  }

  /**
   * Gets FB pixel init code
   */
  public static function getPixelInitCode($agent_string, $param = array(), $with_script_tag = true) {
    if (empty(self::$pixelId)) {
      return;
    }

    $code = $with_script_tag
    ? "<script type='text/javascript'>" . self::$pixelFbqCodeWithoutScript . "</script>"
    : self::$pixelFbqCodeWithoutScript;
    $param_str = $param;
    if (is_array($param)) {
      $param_str = json_encode($param, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT);
    }
    $agent_param = array('agent' => $agent_string);
    return sprintf(
      $code,
      'init',
      self::$pixelId,
      ', ' . $param_str,
      ', ' . json_encode($agent_param, JSON_PRETTY_PRINT));
  }

  /**
   * Gets FB pixel track code
   * $param is the parameter for the pixel event.
   *   If it is an array, FB_INTEGRATION_TRACKING_KEY parameter with $tracking_name value will automatically
   *   be added into the $param. If it is a string, please append the FB_INTEGRATION_TRACKING_KEY parameter
   *   with its tracking name into the JS Parameter block
   */
  public static function getPixelTrackCode($event, $param = array(), $tracking_name = '', $with_script_tag = true) {
    if (empty(self::$pixelId)) {
      return;
    }

    $code = $with_script_tag
    ? "<script type='text/javascript'>" . self::$pixelFbqCodeWithoutScript . "</script>"
    : self::$pixelFbqCodeWithoutScript;
    $param_str = $param;
    if (is_array($param)) {
      if (!empty($tracking_name)) {
        $param[self::FB_INTEGRATION_TRACKING_KEY] = $tracking_name;
      }
      $param_str = json_encode($param, JSON_PRETTY_PRINT);
    }
    $class = new ReflectionClass(__CLASS__);
    return sprintf(
      $code,
      $class->getConstant(strtoupper($event)) !== false ? 'track' : 'trackCustom',
      $event,
      ', ' . $param_str,
      '');
  }

  /**
   * Gets FB pixel noscript code
   */
  public static function getPixelNoscriptCode($event = 'PageView', $cd = array(), $tracking_name = '') {
    if (empty(self::$pixelId)) {
      return;
    }

    $data = '';
    foreach ($cd as $k => $v) {
      $data .= '&cd[' . $k . ']=' . $v;
    }
    if (!empty($tracking_name)) {
      $data .= '&cd[' . self::FB_INTEGRATION_TRACKING_KEY . ']=' . $tracking_name;
    }
    return sprintf(
      self::$pixelNoscriptCode,
      self::$pixelId,
      $event,
      $data);
  }

  /**
   * Gets FB pixel AddToCart code
   */
  public static function getPixelAddToCartCode($param = array(), $tracking_name = '', $with_script_tag = true) {
    return self::getPixelTrackCode(
      self::ADDTOCART,
      $param,
      $tracking_name,
      $with_script_tag);
  }

  /**
   * Gets FB pixel InitiateCheckout code
   */
  public static function getPixelInitiateCheckoutCode($param = array(), $tracking_name = '', $with_script_tag = true) {
    return self::getPixelTrackCode(
      self::INITIATECHECKOUT,
      $param,
      $tracking_name,
      $with_script_tag);
  }

  /**
   * Gets FB pixel Lead code
   */
  public static function getPixelLeadCode($param = array(), $tracking_name = '', $with_script_tag = true) {
    return self::getPixelTrackCode(
      self::LEAD,
      $param,
      $tracking_name,
      $with_script_tag);
  }

  /**
   * Gets FB pixel PageView code
   */
  public static function getPixelPageViewCode($param = array(), $tracking_name = '', $with_script_tag = true) {
    return self::getPixelTrackCode(
      self::PAGEVIEW,
      $param,
      $tracking_name,
      $with_script_tag);
  }

  /**
   * Gets FB pixel Purchase code
   */
  public static function getPixelPurchaseCode($param = array(), $tracking_name = '', $with_script_tag = true) {
    return self::getPixelTrackCode(
      self::PURCHASE,
      $param,
      $tracking_name,
      $with_script_tag);
  }

  /**
   * Gets FB pixel ViewContent code
   */
  public static function getPixelViewContentCode($param = array(), $tracking_name = '', $with_script_tag = true) {
    return self::getPixelTrackCode(
      self::VIEWCONTENT,
      $param,
      $tracking_name,
      $with_script_tag);
  }
}
