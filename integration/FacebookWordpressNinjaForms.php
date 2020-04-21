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
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\PixelRenderer;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\UserData;

class FacebookWordpressNinjaForms extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'ninja-forms/ninja-forms.php';
  const TRACKING_NAME = 'ninja-forms';

  public static function injectPixelCode() {
    add_action(
      'ninja_forms_submission_actions',
      array(__CLASS__, 'injectLeadEvent'),
      10, 3);
  }

  public static function injectLeadEvent($actions, $form_cache, $form_data) {
    if (FacebookPluginUtils::isAdmin()) {
      return $actions;
    }

    foreach ($actions as $key => $action) {
      if (!isset($action['settings']) || !isset($action['settings']['type'])) {
        continue;
      }

      $type = $action['settings']['type'];
      if (!is_string($type)) {
        continue;
      }

      // inject code when form is submitted successfully
      if ($type == 'successmessage') {
        $event = ServerEventFactory::safeCreateEvent(
          'Lead',
          array(__CLASS__, 'readFormData'),
          array($form_data),
          self::TRACKING_NAME,
          true
        );
        FacebookServerSideEvent::getInstance()->track($event);

        $pixel_code = PixelRenderer::render(array($event), self::TRACKING_NAME);
        $code = sprintf("
<!-- Facebook Pixel Event Code -->
%s
<!-- End Facebook Pixel Event Code -->
    ", $pixel_code);

        $action['settings']['success_msg'] .= $code;
        $actions[$key] = $action;
      }
    }

    return $actions;
  }

  public static function readFormData($form_data) {
    if (empty($form_data)) {
      return array();
    }

    $name = self::getName($form_data);
    return array(
      'email' => self::getEmail($form_data),
      'first_name' => $name[0],
      'last_name' => $name[1]
    );
  }

  private static function getEmail($form_data) {
    return self::getField($form_data, 'email');
  }

  private static function getName($form_data) {
    return ServerEventFactory::splitName(self::getField($form_data, 'name'));
  }

  private static function getField($form_data, $key) {
    if (empty($form_data['fields'])) {
      return null;
    }

    foreach ($form_data['fields'] as $field) {
      if ($field['key'] == $key) {
        return $field['value'];
      }
    }

    return null;
  }
}
