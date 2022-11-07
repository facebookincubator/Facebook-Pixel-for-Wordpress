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

use FacebookPixelPlugin\Core\AAMSettingsFields;
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;

defined('ABSPATH') or die('Direct access not allowed');

class FacebookWordpressOpenBridge {
    const ADVANCED_MATCHING_LABEL = 'fb.advanced_matching';
    const CUSTOM_DATA_LABEL = 'custom_data';

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

    public function extractFromDatabag($databag){
        $current_user = FacebookPluginUtils::getLoggedInUserInfo();

        $event_data = array(
            # user data
            'email' => self::getEmail($current_user, $databag),
            'first_name' => self::getFirstName($current_user, $databag),
            'last_name' => self::getLastName($current_user, $databag),
            'external_id' => self::getExternalID($current_user, $databag),
            'phone' => self::getAAMField(AAMSettingsFields::PHONE, $databag),
            'state' => self::getAAMField(AAMSettingsFields::STATE, $databag),
            'country' => self::getAAMField(AAMSettingsFields::COUNTRY,$databag),
            'city' => self::getAAMField(AAMSettingsFields::CITY, $databag),
            'zip' => self::getAAMField(AAMSettingsFields::ZIP_CODE, $databag),
            'gender' => self::getAAMField(AAMSettingsFields::GENDER, $databag),
            'date_of_birth' =>
                self::getAAMField(AAMSettingsFields::DATE_OF_BIRTH, $databag),

            # custom data
            'currency' => self::getCustomData('currency', $databag),
            'value' => self::getCustomData('value', $databag),
            'content_type' => self::getCustomData('content_type', $databag),
            'content_name' => self::getCustomData('content_name', $databag),
            'content_ids' => self::getCustomDataArray('content_ids', $databag),
            'content_category' =>
                self::getCustomData('content_category', $databag),
          );
        return $event_data;
    }

    private static function getEmail($current_user_data, $pixel_data){
        if($current_user_data['email']){
            return $current_user_data['email'];
        }
        return self::getAAMField(AAMSettingsFields::EMAIL, $pixel_data);
    }

    private static function getFirstName($current_user_data, $pixel_data){
        if($current_user_data['first_name']){
            return $current_user_data['first_name'];
        }
        return self::getAAMField(AAMSettingsFields::FIRST_NAME, $pixel_data);
    }

    private static function getLastName($current_user_data, $pixel_data){
        if($current_user_data['last_name']){
            return $current_user_data['last_name'];
        }
        return self::getAAMField(AAMSettingsFields::LAST_NAME, $pixel_data);
    }

    private static function getExternalID($current_user_data, $pixel_data){
        if($current_user_data['id']){
            return (string) $current_user_data['id'];
        }
        return self::getAAMField(AAMSettingsFields::EXTERNAL_ID, $pixel_data);
    }

    private static function getAAMField($key, $pixel_data){
        if(!array_key_exists(self::ADVANCED_MATCHING_LABEL, $pixel_data)){
            return '';
        }
        if(array_key_exists($key, $pixel_data[self::ADVANCED_MATCHING_LABEL])){
            return $pixel_data[self::ADVANCED_MATCHING_LABEL][$key];
        }
        return '';
    }

    private static function getCustomData($key, $pixel_data){
        if(!array_key_exists(self::CUSTOM_DATA_LABEL, $pixel_data)){
            return '';
        }
        if(array_key_exists($key, $pixel_data[self::CUSTOM_DATA_LABEL])){
            return $pixel_data[self::CUSTOM_DATA_LABEL][$key];
        }
        return '';
    }

    private static function getCustomDataArray($key, $pixel_data){
        if(!array_key_exists(self::CUSTOM_DATA_LABEL, $pixel_data)){
            return '';
        }
        if(array_key_exists($key, $pixel_data[self::CUSTOM_DATA_LABEL])){
            return $pixel_data[self::CUSTOM_DATA_LABEL][$key];
        }
        return [];
    }
}
