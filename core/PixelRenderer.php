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

use ReflectionClass;

defined('ABSPATH') or die('Direct access not allowed');

class PixelRenderer {
  const EVENT_ID = 'eventID';
  const TRACK = 'track';
  const TRACK_CUSTOM = 'trackCustom';
  const SCRIPT_TAG = "<script type='text/javascript'>%s</script>";
  const FBQ_CODE = "
  fbq('%s', '%s', %s);
";

  public static function render($event) {
    return sprintf(self::SCRIPT_TAG, self::getPixelTrackCode($event));
  }

  private static function getPixelTrackCode($event) {
    $class = new ReflectionClass('FacebookPixelPlugin\Core\FacebookPixel');

    return sprintf(
      self::FBQ_CODE,
      $class->getConstant(strtoupper($event->getEventName())) !== false
      ? self::TRACK : self::TRACK_CUSTOM,
      $event->getEventName(),
      $event->getCustomData() !== null ?
        json_encode($event->getCustomData()->normalize())
        : "{}"
    );
  }
}
