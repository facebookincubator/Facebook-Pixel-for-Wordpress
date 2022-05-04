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

use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;

defined('ABSPATH') or die('Direct access not allowed');

class FacebookWordpressOpenBridge {

    private static $instance = null;
    private static $blocked_events = array('Microdata');

    public function __construct() {
    }

    public static function getInstance() {
        if (self::$instance == null) {
          self::$instance = new FacebookWordpressOpenBridge();
        }
        return self::$instance;
    }

    public function handleOpenBridgeReq($data){
        $event_name = $data['event_name'];
        if(in_array($event_name, self::$blocked_events)){
            return;
        }
        $server_event = ServerEventFactory::safeCreateEvent(
            $event_name,
            array($this, 'extractFromDatabag'),
            array($data),
            'wp-cloudbridge-plugin',
            true
        );

        $server_event->setEventId($data['event_id']);
        FacebookServerSideEvent::getInstance()->track($server_event, true);
    }

    public function extractFromDatabag($data){
        $event_data = FacebookPluginUtils::getLoggedInUserInfo();

        $custom_data = $data['custom_data'];
        foreach ($custom_data as $key => $value) {
            if(is_array($value)) continue;
            $event_data[$key] = $value;
        }

        $matching_data = $data['fb.advanced_matching'];
        foreach ($matching_data as $key => $value) {
            if(is_array($value)) continue;
            $event_data[$key] = $value;
        }
        return $event_data;
    }

}
