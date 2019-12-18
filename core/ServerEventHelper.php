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
use FacebookPixelPlugin\Core\EventIdGenerator;

defined('ABSPATH') or die('Direct access not allowed');

class ServerEventHelper {
  public static function newEvent() {
    $event = (new Event())
              ->setEventTime(time())
              ->setEventId(EventIdGenerator::guidv4());
    return $event;
  }
}