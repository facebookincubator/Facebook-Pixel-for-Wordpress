<?php
/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Integration;

defined('ABSPATH') or die('Direct access not allowed');

use FacebookPixelPlugin\Core\FacebookPixel;
use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;

class FacebookWordpressContactForm7 extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'contact-form-7/wp-contact-form-7.php';

  public static function injectPixelCode() {
    add_action(
      'wpcf7_contact_form',
      array(__CLASS__, 'injectLeadEventHook'),
      11);
  }

  public static function injectLeadEventHook() {
    add_action(
      'wp_footer',
      array(__CLASS__, 'injectLeadEvent'),
      11);
  }

  public static function injectLeadEvent() {
    if (is_admin()) {
      return;
    }

    $param = array();
    if (FacebookWordpressOptions::getUsePii()) {
      $param = '{ em: (function() {
  if (!event || !event.detail || !event.detail.inputs) {
    return "";
  }

  var inputs = event.detail.inputs;
  for (var i = 0; i < inputs.length; i++) {
    var element = inputs[i];
    var name = element.name;
    if (name.indexOf("email") >= 0) {
      return element.value;
    }
  }
 })() }';
    }
    $code = FacebookPixel::getPixelLeadCode($param, false);
    $listener = 'wpcf7submit';

    printf("
<!-- Facebook Pixel Event Code -->
<script>
  document.addEventListener(
    '%s',
    function (event) {%s},
    false
  );
</script>
<!-- End Facebook Pixel Event Code -->
      ",
      $listener,
      $code);
  }
}
