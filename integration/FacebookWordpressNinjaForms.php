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

  private static $formID;

  private static $leadJS = "
jQuery(document).ready(function($) {
  var facebookWordpressNinjaFormsController = Marionette.Object.extend({
    initialize: function() {
      this.listenTo(Backbone.Radio.channel('form-' + '%s'), 'submit:response', this.actionSubmit);
    },
    actionSubmit: function(response) {
      if (response.data && response.data.fields) {
        %s
      }
    },
  });

  new facebookWordpressNinjaFormsController();
});
";

  public static function injectPixelCode() {
    add_action(
      'ninja_forms_display_after_form',
      array(__CLASS__, 'injectLeadEventHook'),
      11);
  }

  public static function injectLeadEventHook($form_id) {
    static::$formID = $form_id;

    // bug fixed for https://wordpress.org/support/topic/marionette-is-not-defined/
    // using 90 here to make sure the Marionette is loaded
    add_action(
      'wp_footer',
      array(__CLASS__, 'injectLeadEvent'),
      90);
  }

  public static function injectLeadEvent() {
    if (FacebookPluginUtils::isAdmin()) {
      return;
    }

    $param = array();
    $pixel_code = FacebookPixel::getPixelLeadCode($param, self::TRACKING_NAME, false);
    $listener_code = sprintf(
      self::$leadJS,
      static::$formID,
      $pixel_code);

    printf("
<!-- Facebook Pixel Event Code -->
<script>
%s
</script>
<!-- End Facebook Pixel Event Code -->
      ",
      $listener_code);
  }
}
