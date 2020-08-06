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
  private static $instance = null;
  private $tracked_events = [];

  public static function getInstance() {
    if (self::$instance == null) {
      self::$instance = new FacebookServerSideEvent();
    }

    return self::$instance;
  }

  public function track($event) {
    $this->tracked_events[] = $event;

    if (FacebookWordpressOptions::getUseS2S()) {
      do_action('send_server_event', $event);
    }
  }

  public function getTrackedEvents() {
    return $this->tracked_events;
  }

  public static function send($events) {
    $events = apply_filters('before_conversions_api_event_sent', $events);
    if (empty($events)) {
      return;
    }

    $pixel_id = FacebookWordpressOptions::getPixelId();
    $access_token = FacebookWordpressOptions::getAccessToken();
    $agent = FacebookWordpressOptions::getAgentString();

    $api = Api::init(null, null, $access_token);

    $request = (new EventRequest($pixel_id))
                  ->setEvents($events)
                  ->setPartnerAgent($agent);

    $response = $request->execute();
  }
}
