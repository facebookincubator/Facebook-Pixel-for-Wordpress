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
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\FacebookWordPressOptions;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\UserData;

class FacebookWordpressCalderaForm extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'caldera-forms/caldera-core.php';
  const TRACKING_NAME = 'caldera-forms';

  public static function injectPixelCode() {
    add_action(
      'caldera_forms_ajax_return',
      array(__CLASS__, 'injectLeadEvent'),
      10, 2);
  }

  public static function injectLeadEvent($out, $form) {
    if (FacebookPluginUtils::isAdmin() || $out['status'] !== 'complete') {
      return $out;
    }

    if (FacebookWordpressOptions::getUseS2S()) {
      $server_event = self::createServerEvent($form);
      FacebookServerSideEvent::send($server_event);
    }

    $param = array();
    $code = FacebookPixel::getPixelLeadCode($param, self::TRACKING_NAME, true);
    $code = sprintf("
    <!-- Facebook Pixel Event Code -->
    %s
    <!-- End Facebook Pixel Event Code -->
         ",
      $code);

    $out['html'] .= $code;
    return $out;
  }

  private static function createServerEvent($form) {
    $email = self::getEmail($form);
    $first_name = self::getFirstName($form);
    $last_name = self::getLastName($form);

    $user_data = (new UserData())
                  ->setEmail($email)
                  ->setFirstName($first_name)
                  ->settLastName($last_name);

    $event = (new Event())
              ->setEventName('Lead')
              ->setEventTime(time())
              ->setUserData($user_data);

    return $event;
  }

  private static function getEmail($form) {
    return self::getFieldValue($form, 'type', 'email');
  }

  private static function getFirstName($form) {
    return self::getFieldValue($form, 'slug', 'first_name');
  }

  private static function getLastName($form) {
    return self::getFieldValue($form, 'slug', 'last_name');
  }

  private static function getFieldValue($form, $attr, $attr_value) {
    foreach ($form['fields'] as $field) {
      if ($field[$attr] == $attr_value) {
        return $_POST[$field['ID']];
      }
    }
  }
}
