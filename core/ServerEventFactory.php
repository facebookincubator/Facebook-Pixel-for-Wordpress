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

namespace FacebookPixelPlugin\Core;

use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\UserData;
use FacebookAds\Object\ServerSide\CustomData;
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
      if (array_key_exists($header, $_SERVER)) {
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

      if (FacebookWordpressOptions::getUsePii()) {
        $user_data = $event->getUserData();
        if (!empty($data['email'])) {
          $user_data->setEmail($data['email']);
        }

        if (!empty($data['first_name'])) {
          $user_data->setFirstName($data['first_name']);
        }

        if (!empty($data['last_name'])) {
          $user_data->setLastName($data['last_name']);
        }

        if (!empty($data['phone'])) {
          $user_data->setPhone($data['phone']);
        }

        if(!empty($data['state'])){
          $user_data->setState($data['state']);
        }

        if(!empty($data['country'])){
          $user_data->setCountryCode($data['country']);
        }

        if(!empty($data['city'])){
          $user_data->setCity($data['city']);
        }

        if(!empty($data['zip'])){
          $user_data->setZipCode($data['zip']);
        }

        if(!empty($data['gender'])){
          $user_data->setGender($data['gender']);
        }
      }

      $custom_data = $event->getCustomData();
      $custom_data->addCustomProperty('fb_integration_tracking', $integration);

      if (!empty($data['currency'])) {
        $custom_data->setCurrency($data['currency']);
      }

      if (!empty($data['value'])) {
        $custom_data->setValue($data['value']);
      }

      if (!empty($data['contents'])) {
        $custom_data->setContents($data['contents']);
      }

      if (!empty($data['content_ids'])) {
        $custom_data->setContentIds($data['content_ids']);
      }

      if (!empty($data['content_type'])) {
        $custom_data->setContentType($data['content_type']);
      }

      if (!empty($data['num_items'])) {
        $custom_data->setNumItems($data['num_items']);
      }

      if (!empty($data['content_name'])) {
        $custom_data->setContentName($data['content_name']);
      }
    } catch (\Exception $e) {
      // Need to log
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
