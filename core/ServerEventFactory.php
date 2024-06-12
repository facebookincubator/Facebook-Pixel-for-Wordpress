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

namespace FacebookPixelPlugin\Core;

use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\UserData;
use FacebookAds\Object\ServerSide\CustomData;
use FacebookAds\Object\ServerSide\Normalizer;

use FacebookPixelPlugin\Core\AAMFieldsExtractor;
use FacebookPixelPlugin\Core\AAMSettingsFields;
use FacebookPixelPlugin\Core\EventIdGenerator;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;

defined('ABSPATH') or die('Direct access not allowed');

class ServerEventFactory {
  public static function newEvent(
    $event_name,
    $prefer_referrer_for_event_src = false)
  {
    $user_data = (new UserData())
                  ->setClientIpAddress(self::getIpAddress())
                  ->setClientUserAgent(self::getHttpUserAgent())
                  ->setFbp(self::getFbp())
                  ->setFbc(self::getFbc());

    $event = (new Event())
              ->setEventName($event_name)
              ->setEventTime(time())
              ->setEventId(EventIdGenerator::guidv4())
              ->setEventSourceUrl(
                self::getRequestUri($prefer_referrer_for_event_src))
              ->setActionSource('website')
              ->setUserData($user_data)
              ->setCustomData(new CustomData());

    return $event;
  }

  private static function getIpAddress() {
    $HEADERS_TO_SCAN = array(
      'HTTP_CLIENT_IP',
      'HTTP_X_FORWARDED_FOR',
      'HTTP_X_FORWARDED',
      'HTTP_X_CLUSTER_CLIENT_IP',
      'HTTP_FORWARDED_FOR',
      'HTTP_FORWARDED',
      'REMOTE_ADDR'
    );

    foreach ($HEADERS_TO_SCAN as $header) {
      if (isset($_SERVER[$header])) {
        $ip_list = explode(',', $_SERVER[$header]);
        foreach($ip_list as $ip) {
          $trimmed_ip = trim($ip);
          if (self::isValidIpAddress($trimmed_ip)) {
            return $trimmed_ip;
          }
        }
      }
    }

    return null;
  }

  private static function getHttpUserAgent() {
    $user_agent = null;

    if (!empty($_SERVER['HTTP_USER_AGENT'])) {
      $user_agent = $_SERVER['HTTP_USER_AGENT'];
    }

    return $user_agent;
  }

  private static function getRequestUri($prefer_referrer_for_event_src) {
    if ($prefer_referrer_for_event_src && !empty($_SERVER['HTTP_REFERER'])) {
      return $_SERVER['HTTP_REFERER'];
    }

    $url = "http://";
    if(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
      $url = "https://";
    }

    if (!empty($_SERVER['HTTP_HOST'])) {
      $url .= $_SERVER['HTTP_HOST'];
    }

    if (!empty($_SERVER['REQUEST_URI'])) {
      $url .= $_SERVER['REQUEST_URI'];
    }

    return $url;
  }

  private static function getFbp() {
    $fbp = null;

    if (!empty($_COOKIE['_fbp'])) {
      $fbp = $_COOKIE['_fbp'];
    }

    return $fbp;
  }

  private static function getFbc() {
    $fbc = null;

    if (!empty($_COOKIE['_fbc'])) {
      $fbc = $_COOKIE['_fbc'];
      $_SESSION['_fbc'] = $fbc;
    }

    if (!$fbc && isset($_GET['fbclid'])) {
      $fbclid = $_GET['fbclid'];
      $cur_time = (int)(microtime(true)*1000);
      $fbc = "fb.1.".$cur_time.".".rawurldecode($fbclid);
    }

    if (!$fbc && isset($_SESSION['_fbc'])) {
      $fbc = $_SESSION['_fbc'];
    }

    if ($fbc) {
      $_SESSION['_fbc'] = $fbc;
    }

    return $fbc;
  }

  private static function isValidIpAddress($ip_address) {
    return filter_var($ip_address,
                      FILTER_VALIDATE_IP,
                      FILTER_FLAG_IPV4
                      | FILTER_FLAG_IPV6
                      | FILTER_FLAG_NO_PRIV_RANGE
                      | FILTER_FLAG_NO_RES_RANGE);
  }

  /*
    Given that the data extracted by the integration classes is a mix of
    user data and custom data,
    this function splits these fields in two arrays
    and user data is formatted with the AAM field setting
   */
  private static function splitUserDataAndCustomData($data){
    $user_data = array();
    $custom_data = array();
    $key_to_aam_field = array(
      'email' => AAMSettingsFields::EMAIL,
      'first_name' => AAMSettingsFields::FIRST_NAME,
      'last_name' => AAMSettingsFields::LAST_NAME,
      'phone' => AAMSettingsFields::PHONE,
      'state' => AAMSettingsFields::STATE,
      'country' => AAMSettingsFields::COUNTRY,
      'city' => AAMSettingsFields::CITY,
      'zip' => AAMSettingsFields::ZIP_CODE,
      'gender' => AAMSettingsFields::GENDER,
      'date_of_birth' => AAMSettingsFields::DATE_OF_BIRTH,
      'external_id' => AAMSettingsFields::EXTERNAL_ID,
    );
    foreach( $data as $key => $value ){
      if( isset( $key_to_aam_field[$key] ) ){
        $user_data[$key_to_aam_field[$key]] = $value;
      }
      else{
        $custom_data[$key] = $value;
      }
    }
    return array(
      'user_data' => $user_data,
      'custom_data' => $custom_data
    );
  }

  public static function safeCreateEvent(
    $event_name,
    $callback,
    $arguments,
    $integration,
    $prefer_referrer_for_event_src = false)
  {
    $event = self::newEvent($event_name, $prefer_referrer_for_event_src);

    try {
      $data = call_user_func_array($callback, $arguments);
      $data_split = self::splitUserDataAndCustomData($data);
      $user_data_array = $data_split['user_data'];
      $custom_data_array = $data_split['custom_data'];
      $user_data_array =
        AAMFieldsExtractor::getNormalizedUserData($user_data_array);

      $user_data = $event->getUserData();
      if(
        isset($user_data_array[AAMSettingsFields::EMAIL])
      ){
        $user_data->setEmail(
          $user_data_array[AAMSettingsFields::EMAIL]
        );
      }
      if(
        isset($user_data_array[AAMSettingsFields::FIRST_NAME])
      ){
        $user_data->setFirstName(
          $user_data_array[AAMSettingsFields::FIRST_NAME]
        );
      }
      if(
        isset($user_data_array[AAMSettingsFields::LAST_NAME])
      ){
        $user_data->setLastName(
          $user_data_array[AAMSettingsFields::LAST_NAME]
        );
      }
      if(
        isset($user_data_array[AAMSettingsFields::GENDER])
      ){
        $user_data->setGender(
          $user_data_array[AAMSettingsFields::GENDER]
        );
      }
      if(
        isset($user_data_array[AAMSettingsFields::DATE_OF_BIRTH])
      ){
        $user_data->setDateOfBirth(
          $user_data_array[AAMSettingsFields::DATE_OF_BIRTH]);
      }
      if(
        isset($user_data_array[AAMSettingsFields::EXTERNAL_ID]) &&
        !is_null($user_data_array[AAMSettingsFields::EXTERNAL_ID])
      ){
        if (is_array($user_data_array[AAMSettingsFields::EXTERNAL_ID])) {
          $external_ids = $user_data_array[AAMSettingsFields::EXTERNAL_ID];
          $hashed_eids = array();
          foreach($external_ids as $k => $v) {
            $hashed_eids[$k] = hash("sha256", $v);
          }
          $user_data->setExternalIds($hashed_eids);
        } else {
          $user_data->setExternalId(
            hash("sha256", $user_data_array[AAMSettingsFields::EXTERNAL_ID])
          );
        }
      }
      if(
        isset($user_data_array[AAMSettingsFields::PHONE])
      ){
        $user_data->setPhone(
          $user_data_array[AAMSettingsFields::PHONE]
        );
      }
      if(
        isset($user_data_array[AAMSettingsFields::CITY])
      ){
        $user_data->setCity(
          $user_data_array[AAMSettingsFields::CITY]
        );
      }
      if(
        isset($user_data_array[AAMSettingsFields::STATE])
      ){
        $user_data->setState(
          $user_data_array[AAMSettingsFields::STATE]
        );
      }
      if(
        isset($user_data_array[AAMSettingsFields::ZIP_CODE])
      ){
        $user_data->setZipCode(
          $user_data_array[AAMSettingsFields::ZIP_CODE]
        );
      }
      if(
        isset($user_data_array[AAMSettingsFields::COUNTRY])
      ){
        $user_data->setCountryCode(
          $user_data_array[AAMSettingsFields::COUNTRY]
        );
      }

      $custom_data = $event->getCustomData();
      $custom_data->addCustomProperty('fb_integration_tracking', $integration);

      if (!empty($data['currency'])) {
        $custom_data->setCurrency($custom_data_array['currency']);
      }

      if (!empty($data['value'])) {
        $custom_data->setValue($custom_data_array['value']);
      }

      if (!empty($data['contents'])) {
        $custom_data->setContents($custom_data_array['contents']);
      }

      if (!empty($data['content_ids'])) {
        $custom_data->setContentIds($custom_data_array['content_ids']);
      }

      if (!empty($data['content_type'])) {
        $custom_data->setContentType($custom_data_array['content_type']);
      }

      if (!empty($data['num_items'])) {
        $custom_data->setNumItems($custom_data_array['num_items']);
      }

      if (!empty($data['content_name'])) {
        $custom_data->setContentName($custom_data_array['content_name']);
      }

      if (!empty($data['content_category'])){
        $custom_data->setContentCategory(
          $custom_data_array['content_category']
        );
      }
    } catch (\Exception $e) {
      error_log(json_encode($e));
    }

    return $event;
  }

  public static function splitName($name) {
    $first_name = $name;
    $last_name = null;
    $index = strpos($name, ' ');
    if ($index !== false) {
      $first_name = substr($name, 0, $index);
      $last_name = substr($name, $index + 1);
    }

    return array($first_name, $last_name);
  }
}
