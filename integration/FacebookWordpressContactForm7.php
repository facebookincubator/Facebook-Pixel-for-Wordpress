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

use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\FacebookWordPressOptions;
use FacebookPixelPlugin\Core\ServerEventHelper;
use FacebookPixelPlugin\Core\PixelRenderer;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\UserData;

class FacebookWordpressContactForm7 extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'contact-form-7/wp-contact-form-7.php';
  const TRACKING_NAME = 'contact-form-7';

  public static function injectPixelCode() {
    add_action(
      'wpcf7_submit',
      array(__CLASS__, 'injectLeadEvent'),
      10, 2);
  }

  public static function injectLeadEvent($form, $result) {
    if (FacebookPluginUtils::isAdmin()) {
      return $result;
    }

    $server_event = self::createServerEvent($form);
    FacebookServerSideEvent::getInstance()->track($server_event);

    $code = PixelRenderer::render($server_event, self::TRACKING_NAME);
    $code = sprintf("
    <!-- Facebook Pixel Event Code -->
    %s
    <!-- End Facebook Pixel Event Code -->
         ",
      $code);

    $result['message'] .= $code ;
    return $result;
  }

  private static function createServerEvent($form) {
    $event = ServerEventHelper::newEvent('Lead');

    if (!is_null($form)) {
      $form_tags = $form->scan_form_tags();
      $email = self::getEmail($form_tags);
      $name = self::getName($form_tags);

      $first_name = $name;
      $last_name = null;
      $index = strpos($name, ' ');
      if ($index !== false) {
        $first_name = substr($name, 0, $index);
        $last_name = substr($name, $index + 1);
      }

      $user_data = $event->getUserData();
      $user_data->setEmail($email)
                ->setFirstName($first_name)
                ->setLastName($last_name);
    }

    return $event;
  }

  private static function getEmail($form_tags) {
    foreach ($form_tags as $tag) {
      if ($tag->basetype == "email") {
        return $_POST[$tag->name];
      }
    }

    return null;
  }

  private static function getName($form_tags) {
    foreach ($form_tags as $tag) {
      if ($tag->basetype === "text"
        && strpos(strtolower($tag->name), 'name') !== false) {
        return $_POST[$tag->name];
      }
    }

    return null;
  }
}
