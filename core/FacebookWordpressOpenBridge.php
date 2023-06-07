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
        $event = ServerEventFactory::safeCreateEvent(
            $event_name,
            array($this, 'extractFromDatabag'),
            array($data),
            'wp-cloudbridge-plugin',
            true
        );

        $event->setEventId($data['event_id']);
        FacebookServerSideEvent::send([$event]);
    }

    public function extractFromDatabag($databag){
        $current_user = self::getPIIFromSession();

        $event_data = array(
            # user data
            'email' => self::getEmail($current_user, $databag),
            'first_name' => self::getFirstName($current_user, $databag),
            'last_name' => self::getLastName($current_user, $databag),
            'external_id' => self::getExternalID($current_user, $databag),
            'phone' => self::getPhone($current_user, $databag),
            'state' => self::getState($current_user, $databag),
            'country' => self::getCountry($current_user, $databag),
            'city' => self::getCity($current_user, $databag),
            'zip' => self::getZip($current_user, $databag),
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

    private static function getPIIFromSession(){
        $current_user = FacebookPluginUtils::getLoggedInUserInfo();
        $user_id = get_current_user_id();
        if($user_id != 0){
          $current_user['city'] = get_user_meta($user_id, 'billing_city', true);
          $current_user['zip'] = get_user_meta($user_id, 'billing_postcode', true);
          $current_user['country'] = get_user_meta($user_id, 'billing_country', true);
          $current_user['state'] = get_user_meta($user_id, 'billing_state', true);
          $current_user['phone'] = get_user_meta($user_id, 'billing_phone', true);
        }
        return array_filter($current_user);
      }

    private static function getEmail($current_user_data, $pixel_data){
        if(isset($current_user_data['email'])){
            return $current_user_data['email'];
        }
        return self::getAAMField(AAMSettingsFields::EMAIL, $pixel_data);
    }

    private static function getFirstName($current_user_data, $pixel_data){
        if(isset($current_user_data['first_name'])){
            return $current_user_data['first_name'];
        }
        return self::getAAMField(AAMSettingsFields::FIRST_NAME, $pixel_data);
    }

    private static function getLastName($current_user_data, $pixel_data){
        if(isset($current_user_data['last_name'])){
            return $current_user_data['last_name'];
        }
        return self::getAAMField(AAMSettingsFields::LAST_NAME, $pixel_data);
    }

    private static function getExternalID($current_user_data, $pixel_data){
        if(isset($current_user_data['id'])){
            return (string) $current_user_data['id'];
        }
        return self::getAAMField(AAMSettingsFields::EXTERNAL_ID, $pixel_data);
    }

    private static function getPhone($current_user_data, $pixel_data){
        if(isset($current_user_data['phone'])){
            return $current_user_data['phone'];
        }
        return self::getAAMField(AAMSettingsFields::PHONE, $pixel_data);
    }

    private static function getCity($current_user_data, $pixel_data){
        if(isset($current_user_data['city'])){
            return $current_user_data['city'];
        }
        return self::getAAMField(AAMSettingsFields::CITY, $pixel_data);
    }

    private static function getZip($current_user_data, $pixel_data){
        if(isset($current_user_data['zip'])){
            return $current_user_data['zip'];
        }
        return self::getAAMField(AAMSettingsFields::ZIP_CODE, $pixel_data);
    }

    private static function getCountry($current_user_data, $pixel_data){
        if(isset($current_user_data['country'])){
            return $current_user_data['country'];
        }
        return self::getAAMField(AAMSettingsFields::COUNTRY, $pixel_data);
    }

    private static function getState($current_user_data, $pixel_data){
        if(isset($current_user_data['state'])){
            return $current_user_data['state'];
        }
        return self::getAAMField(AAMSettingsFields::STATE, $pixel_data);
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
