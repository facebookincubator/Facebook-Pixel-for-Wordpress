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

namespace FacebookPixelPlugin\Integration;

defined('ABSPATH') or die('Direct access not allowed');

use FacebookPixelPlugin\Core\FacebookPixel;
use FacebookPixelPlugin\Core\FacebookPluginUtils;

class FacebookWordpressCalderaForm extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'caldera-forms/caldera-core.php';
  const TRACKING_NAME = 'caldera-forms';

  public static function injectPixelCode() {
    add_action(
      'caldera_forms_ajax_return',
      array(__CLASS__, 'injectLeadEvent'),
      10, 2);
  }

  public static function injectLeadEvent($out, $form) {
    if (FacebookPluginUtils::isAdmin() || $out['status'] !== 'complete') {
      return $out;
    }

    $param = array();
    $code = FacebookPixel::getPixelLeadCode($param, self::TRACKING_NAME, true);
    $code = sprintf("
    <!-- Facebook Pixel Event Code -->
    %s
    <!-- End Facebook Pixel Event Code -->
         ",
      $code);

    $out['html'] .= $code;
    return $out;
  }
}
