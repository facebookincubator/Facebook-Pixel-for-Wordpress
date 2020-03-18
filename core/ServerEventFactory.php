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
  public static function newEvent($event_name) {
    $user_data = (new UserData())
                  ->setClientIpAddress(self::getIpAddress())
                  ->setClientUserAgent(self::getHttpUserAgent())
                  ->setFbp(self::getFbp())
                  ->setFbc(self::getFbc());

    $event = (new Event())
              ->setEventName($event_name)
              ->setEventTime(time())
              ->setEventId(EventIdGenerator::guidv4())
              ->setEventSourceUrl(self::getRequestUri())
              ->setUserData($user_data)
              ->setCustomData(new CustomData());

    return $event;
  }

  private static function getIpAddress() {
    $ip_address = null;

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
      $ip_address = $_SERVER['HTTP_CLIENT_IP'];
    } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else if (!empty($_SERVER['REMOTE_ADDR'])) {
      $ip_address = $_SERVER['REMOTE_ADDR'];
    }

    return $ip_address;
  }

  private static function getHttpUserAgent() {
    $user_agent = null;

    if (!empty($_SERVER['HTTP_USER_AGENT'])) {
      $user_agent = $_SERVER['HTTP_USER_AGENT'];
    }

    return $user_agent;
  }

  private static function getRequestUri() {
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

  public static function safeCreateEvent($event_name, $callback, $arguments) {
    $event = self::newEvent($event_name);

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
      }

      $custom_data = $event->getCustomData();
      if (!empty($data['currency'])) {
        $custom_data->setCurrency($data['currency']);
      }

      if (!empty($data['value'])) {
        $custom_data->setValue($data['value']);
      }

      if (!empty($data['content_ids'])) {
        $custom_data->setContentIds($data['content_ids']);
      }

      if (!empty($data['content_type'])) {
        $custom_data->setContentType($data['content_type']);
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
