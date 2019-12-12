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

use FacebookAds\Api;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\EventRequest;
use FacebookAds\Object\ServerSide\UserData;

defined('ABSPATH') or die('Direct access not allowed');

class FacebookServerSideEvent {
    public static function send($event) {
      $pixel_id = FacebookWordpressOptions::getPixelId();
      $access_token = FacebookWordpressOptions::getAccessToken();

      $api = Api::init(null, null, $access_token);

      $events = array();
      array_push($events, $event);

      $request = (new EventRequest($pixel_id))
                   ->setEvents($events);
      $response = $request->execute();
    }
}
