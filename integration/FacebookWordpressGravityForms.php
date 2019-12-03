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
    add_filter(
      'gform_confirmation',
      array(__CLASS__, 'injectLeadEvent'),
      10, 4);
  }

  public static function injectLeadEvent($confirmation, $form, $entry, $ajax) {
    if (FacebookPluginUtils::isAdmin()) {
      return $confirmation;
    }

    $pixel_code = FacebookPixel::getPixelLeadCode(
                    array(), self::TRACKING_NAME, false);
    $code = sprintf("
    <!-- Facebook Pixel Event Code -->
    <script>
    %s
    </script>
    <!-- End Facebook Pixel Event Code -->
    ", $pixel_code);

    if (is_string($confirmation)) {
        $confirmation .= $code;
    } elseif ( is_array($confirmation) && isset($confirmation['redirect'])) {
        $redirect_code = sprintf("
            <!-- Facebook Pixel Gravity Forms Redirect Code -->
            <script>%sdocument.location.href=%s;%s</script>
            <!-- End Facebook Pixel Gravity Forms Redirect Code -->",
            apply_filters('gform_cdata_open', ''),
            defined('JSON_HEX_TAG') ?
              json_encode($confirmation['redirect'], JSON_HEX_TAG)
              : json_encode($confirmation['redirect']),
            apply_filters('gform_cdata_close', '')
          );

        $confirmation = $code . $redirect_code;
    }

    return $confirmation;
  }
}
