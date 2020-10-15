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
    if (FacebookPluginUtils::isInternalUser()) {
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

    $event_data = array();
    $name = self::getName($form_data);
    if( $name ){
      $event_data['first_name'] = $name[0];
      $event_data['last_name'] = $name[1];
    }
    else{
      $event_data['first_name'] = self::getFirstName($form_data);
      $event_data['last_name'] = self::getLastName($form_data);
    }
    $event_data['email'] = self::getEmail($form_data);
    $event_data['phone'] = self::getPhone($form_data);
    $event_data['city'] = self::getCity($form_data);
    $event_data['zip'] = self::getZipCode($form_data);
    $event_data['state'] = self::getState($form_data);
    $event_data['country'] = self::getCountry($form_data);
    $event_data['gender'] = self::getGender($form_data);

    return $event_data;
  }

  private static function getEmail($form_data) {
    return self::getField($form_data, 'email');
  }

  private static function getName($form_data) {
    $name = self::getField($form_data, 'name');
    if($name){
      return ServerEventFactory::splitName($name);
    }
    return null;
  }

  private static function getFirstName($form_data){
    return self::getField($form_data, 'firstname');
  }

  private static function getLastName($form_data){
    return self::getField($form_data, 'lastname');
  }

  private static function getPhone($form_data) {
    return self::getField($form_data, 'phone');
  }

  private static function getCity($form_data) {
    return self::getField($form_data, 'city');
  }

  private static function getZipCode($form_data) {
    return self::getField($form_data, 'zip');
  }

  private static function getState($form_data) {
    return self::getField($form_data, 'liststate');
  }

  private static function getCountry($form_data) {
    return self::getField($form_data, 'listcountry');
  }

  private static function getGender($form_data) {
    return self::getField($form_data, 'gender');
  }

  private static function hasPrefix($string, $prefix){
    $len = strlen($prefix);
    return substr($string, 0, $len) === $prefix;
  }

  private static function getField($form_data, $key) {
    if (empty($form_data['fields'])) {
      return null;
    }

    foreach ($form_data['fields'] as $field) {
      if ( self::hasPrefix( $field['key'], $key) ) {
        return $field['value'];
      }
    }

    return null;
  }
}
