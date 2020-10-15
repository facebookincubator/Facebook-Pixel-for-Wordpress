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

class FacebookWordpressMailchimpForWp extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'mailchimp-for-wp/mailchimp-for-wp.php';
  const TRACKING_NAME = 'mailchimp-for-wp';

  public static function injectPixelCode() {
    self::addPixelFireForHook(array(
      'hook_name' => 'mc4wp_form_subscribed',
      'classname' => __CLASS__,
      'inject_function' => 'injectLeadEvent'));
  }

  public static function injectLeadEvent() {
    if (FacebookPluginUtils::isInternalUser()) {
      return;
    }

    $server_event = ServerEventFactory::safeCreateEvent(
      'Lead',
      array(__CLASS__, 'readFormData'),
      array(),
      self::TRACKING_NAME,
      true
    );
    FacebookServerSideEvent::getInstance()->track($server_event);

    $code = PixelRenderer::render(array($server_event), self::TRACKING_NAME);
    printf("
<!-- Facebook Pixel Event Code -->
  %s
<!-- End Facebook Pixel Event Code -->
    ",
      $code);
  }

  public static function readFormData() {
    $event_data = array();
    if (!empty($_POST['EMAIL'])) {
      $event_data['email'] = $_POST['EMAIL'];
    }

    if (!empty($_POST['FNAME'])) {
      $event_data['first_name'] = $_POST['FNAME'];
    }

    if (!empty($_POST['LNAME'])) {
      $event_data['last_name'] = $_POST['LNAME'];
    }

    if(!empty($_POST['PHONE'])){
      $event_data['phone'] = $_POST['PHONE'];
    }

    if(!empty($_POST['ADDRESS'])){
      $address_data = $_POST['ADDRESS'];

      if(!empty($address_data['city'])){
        $event_data['city'] = $address_data['city'];
      }

      if(!empty($address_data['state'])){
        $event_data['state'] = $address_data['state'];
      }

      if(!empty($address_data['zip'])){
        $event_data['zip'] = $address_data['zip'];
      }

      // You can edit the country field in the plugin editor
      // and do not use ISO code
      // Validating this case
      if(
        !empty($address_data['country'])
        && strlen($address_data['country'])==2
      ){
        $event_data['country'] = $address_data['country'];
      }
    }
    return $event_data;
  }
}
