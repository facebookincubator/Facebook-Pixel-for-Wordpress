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

class FacebookWordpressFormidableForm extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'formidable/formidable.php';
  const TRACKING_NAME = 'formidable-lite';

  public static function injectPixelCode() {
    add_action(
      'frm_after_create_entry',
      array(__CLASS__, 'injectLeadEventHook'),
      30, 2);
  }

  public static function injectLeadEventHook($entry_id, $form_id) {
    add_action('wp_footer', array(__CLASS__, 'injectLeadEvent'), 11);
  }

  public static function injectLeadEvent() {
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
