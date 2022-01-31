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

class FacebookWordpressWPForms extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'wpforms-lite/wpforms.php';
  const TRACKING_NAME = 'wpforms-lite';

  public static function injectPixelCode() {
    add_action(
      'wpforms_process_before',
      array(__CLASS__, 'trackEvent'),
      20,
      2
    );
  }

  public static function trackEvent($entry, $form_data) {
    if (FacebookPluginUtils::isInternalUser()) {
      return;
    }

    $server_event = ServerEventFactory::safeCreateEvent(
      'Lead',
      array(__CLASS__, 'readFormData'),
      array($entry, $form_data),
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
    $pixel_code = PixelRenderer::render($events, self::TRACKING_NAME);

    printf("
<!-- Meta Pixel Event Code -->
%s
<!-- End Meta Pixel Event Code -->
      ",
      $pixel_code);
  }

  public static function readFormData($entry, $form_data) {
    if (empty($entry) || empty($form_data)) {
      return array();
    }

    $name = self::getName($entry, $form_data);

    $event_data = array(
      'email' => self::getEmail($entry, $form_data),
      'first_name' => !empty($name) ? $name[0] : null,
      'last_name' => !empty($name) ? $name[1] : null,
      'phone' => self::getPhone($entry, $form_data)
    );

    $event_data = array_merge(
      $event_data,
      self::getAddress($entry, $form_data)
    );

    return $event_data;
  }

  private static function getPhone($entry, $form_data) {
    return self::getField($entry, $form_data, 'phone');
  }

  private static function getEmail($entry, $form_data) {
    return self::getField($entry, $form_data, 'email');
  }

  private static function getAddress($entry, $form_data){
    $address_field_data = self::getField($entry, $form_data, 'address');
    if($address_field_data == null){
      return array();
    }
    $address_data = array();
    if(array_key_exists('city', $address_field_data)){
      $address_data['city'] = $address_field_data['city'];
    }
    if(array_key_exists('state', $address_field_data)){
      $address_data['state'] = $address_field_data['state'];
    }
    //Country values are sent in ISO format
    if(array_key_exists('country', $address_field_data)){
      $address_data['country'] = $address_field_data['country'];
    }
    else{
      // When country is not present, it could be that address scheme is us
      // so country will be US
      $address_scheme = self::getAddressScheme($form_data);
      if( $address_scheme == 'us'){
        $address_data['country'] = 'US';
      }
    }
    if(array_key_exists('postal', $address_field_data)){
      $address_data['zip'] = $address_field_data['postal'];
    }
    return $address_data;
  }

  private static function getName($entry, $form_data) {
    if (empty($form_data['fields']) || empty($entry['fields'])) {
      return null;
    }

    $entries = $entry['fields'];
    foreach ($form_data['fields'] as $field) {
      if ($field['type'] == 'name') {
        if ($field['format'] == 'simple') {
          return ServerEventFactory::splitName($entries[$field['id']]);
        } else if ($field['format'] == 'first-last') {
          return array(
            $entries[$field['id']]['first'],
            $entries[$field['id']]['last']
          );
        }
      }
    }

    return null;
  }

  private static function getField($entry, $form_data, $type) {
    if (empty($form_data['fields']) || empty($entry['fields'])) {
      return null;
    }

    foreach ($form_data['fields'] as $field) {
      if ($field['type'] == $type) {
        return $entry['fields'][$field['id']];
      }
    }

    return null;
  }

  private static function getAddressScheme($form_data){
    foreach ($form_data['fields'] as $field) {
      if ($field['type'] == 'address') {
        if(array_key_exists('scheme', $field)){
          return $field['scheme'];
        }
      }
    }
    return null;
  }
}
