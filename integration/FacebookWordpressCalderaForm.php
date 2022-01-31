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

use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\FacebookWordPressOptions;
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\PixelRenderer;
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
    if (
      FacebookPluginUtils::isInternalUser() ||
      $out['status'] !== 'complete'
    ) {
      return $out;
    }

    $server_event = ServerEventFactory::safeCreateEvent(
      'Lead',
      array(__CLASS__, 'readFormData'),
      array($form),
      self::TRACKING_NAME,
      true
    );
    FacebookServerSideEvent::getInstance()->track($server_event);

    $code = PixelRenderer::render(array($server_event), self::TRACKING_NAME);
    $code = sprintf("
    <!-- Meta Pixel Event Code -->
    %s
    <!-- End Meta Pixel Event Code -->
        ",
    $code);

    $out['html'] .= $code;
    return $out;
  }

  public static function readFormData($form) {
    if (empty($form)) {
      return array();
    }
    return array(
      'email' => self::getEmail($form),
      'first_name' => self::getFirstName($form),
      'last_name' => self::getLastName($form),
      'phone' => self::getPhone($form),
      'state' => self::getState($form)
    );
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

  private static function getState($form){
    return self::getFieldValue($form, 'type', 'states');
  }

  private static function getPhone($form) {
    // Extract phone number from the better version first, fallback to the basic
    // version if it's null
    $phone = self::getFieldValue($form, 'type', 'phone_better');
    return empty($phone) ? self::getFieldValue($form, 'type', 'phone')
      : $phone;
  }

  private static function getFieldValue($form, $attr, $attr_value) {
    if (empty($form['fields'])) {
      return null;
    }

    foreach ($form['fields'] as $field) {
      if (array_key_exists($attr, $field) && $field[$attr] == $attr_value) {
        return $_POST[$field['ID']];
      }
    }

    return null;
  }
}
