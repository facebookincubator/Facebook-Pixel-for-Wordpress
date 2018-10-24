<?php
/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Integration;

defined('ABSPATH') or die('Direct access not allowed');

use FacebookPixelPlugin\Core\FacebookWordpressOptions;

abstract class FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = '';

  /**
   * inject the pixel code for the plugin
   */
  abstract protected static function injectPixelCode();

  /**
   * generic way to get user email,
   * firstname, lastname from wp_get_current_user
   */
  protected static function getUserEmailParam() {
    if (!FacebookWordpressOptions::getUsePii()) {
      return array();
    }

    if (is_user_logged_in()) {
      $current_user = wp_get_current_user();

      return array(
        'em' => $current_user->user_email,
        'fn' => $current_user->user_firstname,
        'ln' => $current_user->user_lastname,
      );
    }
    return array();
  }
}
