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

use FacebookAds\Api;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\EventRequest;
use FacebookAds\Object\ServerSide\UserData;
use FacebookAds\Exception\Exception;

defined('ABSPATH') or die('Direct access not allowed');

class FacebookServerSideEvent {
  private static $instance = null;
  // Contains all the events triggered during the request
  private $trackedEvents = [];
  // Contains all Conversions API events that have not been sent
  private $pendingEvents = [];
  // Maps a callback name with a Conversions API event
  // that hasn't been rendered as pixel event
  private $pendingPixelEvents = [];

  public static function getInstance() {
    if (self::$instance == null) {
      self::$instance = new FacebookServerSideEvent();
    }
    return self::$instance;
  }

  public function track($event, $sendNow = true) {
    $this->trackedEvents[] = $event;
    if( $sendNow ){
      do_action( 'send_server_events',
        array($event),
        1
      );
    }
    else{
      $this->pendingEvents[] = $event;
    }
  }

  public function getTrackedEvents() {
    return $this->trackedEvents;
  }

  public function getNumTrackedEvents(){
    return count( $this->trackedEvents );
  }

  public function getPendingEvents(){
    return $this->pendingEvents;
  }

  public function setPendingPixelEvent($callback_name, $event){
    $this->pendingPixelEvents[$callback_name] = $event;
  }

  public function getPendingPixelEvent($callback_name){
    if(isset($this->pendingPixelEvents[$callback_name])){
      return $this->pendingPixelEvents[$callback_name];
    }
    return null;
  }

  public static function send($events) {
    $events = apply_filters('before_conversions_api_event_sent', $events);
    if (empty($events)) {
      return;
    }

    $pixel_id = FacebookWordpressOptions::getPixelId();
    $access_token = FacebookWordpressOptions::getAccessToken();
    $agent = FacebookWordpressOptions::getAgentString();

    if(self::isOpenBridgeEvent($events)){
      $agent .= '_ob'; //  agent suffix is openbridge
    }

    if(empty($pixel_id) || empty($access_token)){
      return;
    }
    try{
      $api = Api::init(null, null, $access_token);

      $request = (new EventRequest($pixel_id))
                  ->setEvents($events)
                  ->setPartnerAgent($agent);

      $response = $request->execute();
    } catch (Exception $e) {
      error_log(json_encode($e));
    }
  }

  private static function isOpenBridgeEvent($events) {
    if(count($events) !== 1){
        return false;
    }

    $customData = $events[0]->getCustomData();
    if (!$customData) {
        return false;
    }

    $customProperties = $customData->getCustomProperties();
    if (!$customProperties ||
    !isset($customProperties['fb_integration_tracking'])) {
        return false;
    }

    return
    $customProperties['fb_integration_tracking'] === 'wp-cloudbridge-plugin';
  }
}
