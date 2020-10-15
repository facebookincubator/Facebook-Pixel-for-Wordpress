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

class FacebookWordpressGravityForms extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'gravityforms/gravityforms.php';
  const TRACKING_NAME = 'gravity-forms';

  public static function injectPixelCode() {
    add_filter(
      'gform_confirmation',
      array(__CLASS__, 'injectLeadEvent'),
      10, 4);
  }

  public static function injectLeadEvent($confirmation, $form, $entry, $ajax) {
    if (FacebookPluginUtils::isInternalUser()) {
      return $confirmation;
    }

    $event = ServerEventFactory::safeCreateEvent(
      'Lead',
      array(__CLASS__, 'readFormData'),
      array($form, $entry),
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

    if (is_string($confirmation)) {
        $confirmation .= $code;
    } elseif ( is_array($confirmation) && isset($confirmation['redirect'])) {
        $redirect_code = sprintf("
            <!-- Facebook Pixel Gravity Forms Redirect Code -->
            <script>%sdocument.location.href=%s;%s</script>
            <!-- End Facebook Pixel Gravity Forms Redirect Code -->",
            apply_filters('gform_cdata_open', ''),
            defined('JSON_HEX_TAG') ?
              json_encode($confirmation['redirect'], JSON_HEX_TAG)
              : json_encode($confirmation['redirect']),
            apply_filters('gform_cdata_close', '')
          );

        $confirmation = $code . $redirect_code;
    }

    return $confirmation;
  }

  public static function readFormData($form, $entry) {
    if (empty($form) || empty($entry)) {
      return array();
    }
    $user_data = array(
      'email' => self::getEmail($form, $entry),
      'first_name' => self::getFirstName($form, $entry),
      'last_name' => self::getLastName($form, $entry),
      'phone' => self::getPhone($form, $entry)
    );
    $address_data = self::getAddressData($form, $entry);
    return array_merge($user_data, $address_data);
  }

  private static function getAddressData($form, $entry){
    if (empty($form['fields'])) {
      return array();
    }

    $address_data = array();

    foreach ($form['fields'] as $field) {
      if ($field->type == 'address') {
        if($field->inputs){
          foreach($field->inputs as $input){
            if(
              array_key_exists('label', $input)
              && $input['label'] != null
            ){
              if($input['label'] == 'City'){
                $address_data['city'] = $entry[$input['id']];
              }
              else if($input['label'] == 'State / Province'){
                $address_data['state'] = $entry[$input['id']];
              }
              else if($input['label'] == 'ZIP / Postal Code'){
                $address_data['zip'] = $entry[$input['id']];
              }
              else if($input['label'] == 'Country'){
                if(strlen($entry[$input['id']]) == 2){
                  $address_data['country'] = $entry[$input['id']];
                }
              }
            }
          }
        }
        break;
      }
    }

    return $address_data;
  }

  private static function getPhone($form, $entry) {
    return self::getFieldByType($form, $entry, 'phone');
  }

  private static function getEmail($form, $entry) {
    return self::getFieldByType($form, $entry, 'email');
  }

  private static function getFieldByType($form, $entry, $type){
    if (empty($form['fields'])) {
      return null;
    }

    foreach ($form['fields'] as $field) {
      if ($field->type == $type) {
        return $entry[$field->id];
      }
    }

    return null;
  }

  private static function getFirstName($form, $entry) {
    return self::getName($form, $entry, 'name', 'First');
  }

  private static function getLastName($form, $entry) {
    return self::getName($form, $entry, 'name', 'Last');
  }

  private static function getName($form, $entry, $type, $label) {
    if (empty($form['fields'])) {
      return null;
    }

    foreach ($form['fields'] as $field) {
      if ($field->type == $type) {
        $inputs = $field->inputs;
        if (!empty($inputs)) {
          foreach ($inputs as $input) {
            if ($input['label'] == $label) {
              return $entry[$input['id']];
            }
          }
        }
      }
    }

    return null;
  }
}
