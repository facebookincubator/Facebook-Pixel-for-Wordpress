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

use FacebookPixelPlugin\Core\FacebookServerSideEvent;

defined('ABSPATH') or die('Direct access not allowed');

class ServerEventAsyncTask extends \WP_Async_Task {
  protected $action = 'send_server_events';

  protected function prepare_data($data) {
    try {
      if (!empty($data)) {
        return array(
          'event_data' => base64_encode(serialize($data[0])),
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
      $events = unserialize(base64_decode($_POST['event_data']));
      // When an array has just one object, the deserialization process
      // returns just the object
      // and we want an array
      if( $num_events == 1 ){
        $events = array( $events );
      }

      FacebookServerSideEvent::send($events);
    }
    catch (\Exception $ex) {
      error_log($ex);
    }
  }
}
