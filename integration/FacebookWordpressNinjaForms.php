<?php
/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Integration;

defined('ABSPATH') or die('Direct access not allowed');

use FacebookPixelPlugin\Core\FacebookPixel;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;

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
        var fields = response.data.fields;
        var email;
        for (var key in fields) {
          if (fields[key].key === 'email') {
            email = fields[key].value;
            break;
          }
        }

        if (email) {
          var param = {
            em: email,
            fb_wp_tracking: '%s'
          };
          %s
        }
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

    add_action(
      'wp_footer',
      array(__CLASS__, 'injectLeadEvent'),
      11);
  }

  public static function injectLeadEvent() {
    if (is_admin()) {
      return;
    }

    $pixel_code = FacebookPixel::getPixelLeadCode('param', false);
    $listener_code = sprintf(
      self::$leadJS,
      static::$formID,
      self::TRACKING_NAME,
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
