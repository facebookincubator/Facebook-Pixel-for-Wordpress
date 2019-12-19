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
use FacebookPixelPlugin\Core\EventIdGenerator;

defined('ABSPATH') or die('Direct access not allowed');

class ServerEventHelper {
  public static function newEvent($event_name) {
    $user_data = (new UserData())
                  ->setClientIpAddress(self::getIpAddress())
                  ->setClientUserAgent(self::getHttpUserAgent());

    $event = (new Event())
              ->setEventName($event_name)
              ->setEventTime(time())
              ->setEventId(EventIdGenerator::guidv4())
              ->setEventSourceUrl(self::getRequestUri())
              ->setUserData($user_data);

    return $event;
  }

  private static function getIpAddress() {
    $ip_address = null;

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
      $ip_address = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
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
    $request_uri = null;

    if (!empty($_SERVER['REQUEST_URI'])) {
      $request_uri = $_SERVER['REQUEST_URI'];
    }

    return $request_uri;
  }
}
