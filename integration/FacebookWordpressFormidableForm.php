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
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\FacebookWordPressOptions;
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\PixelRenderer;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\UserData;

class FacebookWordpressFormidableForm extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'formidable/formidable.php';
  const TRACKING_NAME = 'formidable-lite';

  public static function injectPixelCode() {
    add_action(
      'frm_after_create_entry',
      array(__CLASS__, 'trackServerEvent'),
      20,
      2
    );
  }

  public static function trackServerEvent($entry_id, $form_id) {
    if (FacebookPluginUtils::isInternalUser()) {
      return;
    }

    $server_event = ServerEventFactory::safeCreateEvent(
      'Lead',
      array(__CLASS__, 'readFormData'),
      array($entry_id),
      self::TRACKING_NAME,
      true
    );
    FacebookServerSideEvent::getInstance()->track($server_event);

    add_action(
      'wp_footer',
       array(__CLASS__, 'injectLeadEvent'),
       20
    );
  }

  public static function injectLeadEvent() {
    if (FacebookPluginUtils::isInternalUser()) {
      return;
    }

    $events = FacebookServerSideEvent::getInstance()->getTrackedEvents();
    $code = PixelRenderer::render($events, self::TRACKING_NAME);

    printf("
<!-- Meta Pixel Event Code -->
%s
<!-- End Meta Pixel Event Code -->
      ",
      $code);
  }

  public static function readFormData($entry_id) {
    if (empty($entry_id)) {
      return array();
    }

    $entry_values =
        IntegrationUtils::getFormidableFormsEntryValues($entry_id);

    $field_values = $entry_values->get_field_values();
    if (!empty($field_values)) {
      $user_data = array(
        'email' => self::getEmail($field_values),
        'first_name' => self::getFirstName($field_values),
        'last_name' => self::getLastName($field_values),
        'phone' => self::getPhone($field_values)
      );
      $address_data = self::getAddressInformation($field_values);
      return array_merge($user_data, $address_data);
    }

    return array();
  }

  private static function getEmail($field_values) {
    return self::getFieldValueByType($field_values, 'email');
  }

  private static function getFirstName($field_values) {
    return self::getFieldValue($field_values, 'text', 'Name', 'First');
  }

  private static function getLastName($field_values) {
    return self::getFieldValue($field_values, 'text', 'Last', 'Last');
  }

  private static function getPhone($field_values) {
    return self::getFieldValueByType($field_values, 'phone');
  }

  private static function getAddressInformation($field_values){
    $address_saved_value = self::getFieldValueByType($field_values, 'address');
    $address_data = array();
    if($address_saved_value){
      if(isset($address_saved_value['city'])){
        $address_data['city'] = $address_saved_value['city'];
      }
      if(isset($address_saved_value['state'])){
        $address_data['state'] = $address_saved_value['state'];
      }
      // Validating ISO code
      // In current version, country field saves the full name
      if(
        isset($address_saved_value['country'])
        && strlen($address_saved_value['country']) == 2
      ){
          $address_data['country'] = $address_saved_value['country'];
      }
      if(isset($address_saved_value['zip'])){
        $address_data['zip'] = $address_saved_value['zip'];
      }
    }
    return $address_data;
  }

  private static function getFieldValueByType($field_values, $type){
    foreach ($field_values as $field_value) {
      $field = $field_value->get_field();
      if ($field->type == $type) {
        return $field_value->get_saved_value();
      }
    }

    return null;
  }

  private static function getFieldValue(
    $field_values,
    $type,
    $name,
    $description)
  {
    foreach ($field_values as $field_value) {
      $field = $field_value->get_field();
      if ($field->type == $type &&
          $field->name == $name &&
          $field->description == $description) {
        return $field_value->get_saved_value();
      }
    }

    return null;
  }
}
