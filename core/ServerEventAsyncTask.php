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

use FacebookPixelPlugin\Core\FacebookServerSideEvent;

use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\UserData;
use FacebookAds\Object\ServerSide\CustomData;
use FacebookAds\Object\ServerSide\Content;

defined('ABSPATH') or die('Direct access not allowed');

class ServerEventAsyncTask extends \WP_Async_Task {
  protected $action = 'send_server_events';

  private function convert_user_data($user_data_normalized){
    $norm_key_to_key = [
      AAMSettingsFields::EMAIL => 'email',
      AAMSettingsFields::FIRST_NAME => 'first_name',
      AAMSettingsFields::LAST_NAME => 'last_name',
      AAMSettingsFields::GENDER => 'gender',
      AAMSettingsFields::DATE_OF_BIRTH => 'date_of_birth',
      AAMSettingsFields::EXTERNAL_ID => 'external_id',
      AAMSettingsFields::PHONE => 'phone',
      AAMSettingsFields::CITY => 'city',
      AAMSettingsFields::STATE => 'state',
      AAMSettingsFields::ZIP_CODE => 'zip_code',
      AAMSettingsFields::COUNTRY => 'country_code',
    ];
    $user_data = array();
    foreach($user_data_normalized as $norm_key => $field){
      if(array_key_exists($norm_key, $norm_key_to_key)){
        $user_data[$norm_key_to_key[$norm_key]] = $field;
      }
      else{
        $user_data[$norm_key] = $field;
      }
    }
    return $user_data;
  }

  private function convert_array_to_event($event_as_array){
    $event = new Event($event_as_array);
    // If user_data exists, an UserData object is created
    // and set
    if(array_key_exists('user_data', $event_as_array)){
      // The method convert_user_data converts the keys used in the
      // normalized array to the keys used in the constructor of UserData
      $user_data = new UserData($this->convert_user_data(
        $event_as_array['user_data']
      ));
      $event->setUserData($user_data);
    }
    // If custom_data exists, a CustomData object is created and set
    if(array_key_exists('custom_data', $event_as_array)){
      $custom_data = new CustomData($event_as_array['custom_data']);
      // If contents exists in custom_data, an array of Content is created
      // and set
      if(array_key_exists('contents', $event_as_array['custom_data'])){
        $contents = array();
        foreach(
          $event_as_array['custom_data']['contents'] as $contents_as_array
        ){
          // The normalized contents array encodes product id as id
          // but the constructor of Content requires product_id
          if(array_key_exists('id', $contents_as_array)){
            $contents_as_array['product_id'] = $contents_as_array['id'];
          }
          $contents[] = new Content($contents_as_array);
        }
        $custom_data->setContents($contents);
      }
      if(array_key_exists('fb_integration_tracking',
        $event_as_array['custom_data'])){
        $custom_data->addCustomProperty('fb_integration_tracking',
          $event_as_array['custom_data']['fb_integration_tracking']);
      }
      $event->setCustomData($custom_data);
    }
    return $event;
  }

  protected function prepare_data($data) {
    try {
      if (!empty($data)) {
        $num_events = $data[1];
        $events = $data[0];
        // $data[0] can be a single event or an array
        // We want to receive it as an array
        if($num_events == 1){
          $events = array($events);
        }
        // Each event is casted to a php array with normalize()
        $events_as_array = array();
        foreach($events as $event){
          $events_as_array[] = $event->normalize();
        }
        // The array of events is converted to a JSON string
        // and encoded in base 64
        return array(
          'event_data' => base64_encode(json_encode($events_as_array)),
          'num_events'=>$data[1]
        );
      }
    } catch (\Exception $ex) {
      error_log($ex);
    }

    return array();
  }

  protected function run_action() {
    try {
      $num_events = $_POST['num_events'];
      if( $num_events == 0 ){
        return;
      }
      // $_POST['event_data'] is decoded from base 64, returning a JSON string
      // and decoded as a php array
      $events_as_array = json_decode(base64_decode($_POST['event_data']), true);
      // If the passed json string is invalid, no processing is done
      if(!$events_as_array){
        return;
      }
      $events = array();
      // Every event is a php array and casted to an Event object
      foreach( $events_as_array as $event_as_array ){
        $event = $this->convert_array_to_event($event_as_array);
        $events[] = $event;
      }
      FacebookServerSideEvent::send($events);
    }
    catch (\Exception $ex) {
      error_log($ex);
    }
  }
}
