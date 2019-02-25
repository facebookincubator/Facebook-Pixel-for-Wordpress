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

class FacebookWordpressGravityForms extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'gravityforms/gravityforms.php';
  const TRACKING_NAME = 'gravity-forms';

  public static function injectPixelCode() {
    self::addPixelFireForHook(array(
      'hook_name' => 'gform_after_submission',
      'classname' => __CLASS__,
      'inject_function' => 'injectLeadEvent',
      'priority' => 30));
  }

  public static function injectLeadEvent($entry, $form) {
    if (FacebookPluginUtils::isAdmin()) {
      return;
    }

    $param = array();
    $code = FacebookPixel::getPixelLeadCode($param, self::TRACKING_NAME, false);

    printf("
    <!-- Facebook Pixel Event Code -->
    <script>
    %s
    </script>
    <!-- End Facebook Pixel Event Code -->
          ",
      $code);
  }
}
