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

class FacebookWordpressNinjaForms extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'ninja-forms/ninja-forms.php';
  const TRACKING_NAME = 'ninja-forms';

  public static function injectPixelCode() {
    add_action(
      'ninja_forms_submission_actions',
      array(__CLASS__, 'injectLeadEvent'),
      10, 2);
  }

  public static function injectLeadEvent($actions, $form_data) {
    if (FacebookPluginUtils::isAdmin()) {
      return $actions;
    }

    $param = array();
    $pixel_code = FacebookPixel::getPixelLeadCode($param, self::TRACKING_NAME, true);

    $code = sprintf("
  <!-- Facebook Pixel Event Code -->
  %s
  <!-- End Facebook Pixel Event Code -->
        ",
      $pixel_code);

    foreach ($actions as $key => $action) {
      if (!isset($action['settings']) || !isset($action['settings']['type'])) {
        continue;
      }

      $type = $action['settings']['type'];
      if (!is_string($type)) {
        continue;
      }

      // inject code when form is submitted successfully
      if ($type == 'successmessage') {
        $action['settings']['success_msg'] .= $code;
        $actions[$key] = $action;
      }
    }

    return $actions;
  }
}
