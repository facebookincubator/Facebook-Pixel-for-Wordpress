<?php
/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Integration;

defined('ABSPATH') or die('Direct access not allowed');

use FacebookPixelPlugin\Core\FacebookWordpressOptions;

abstract class FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = '';
  const TRACKING_NAME = '';

  /**
   * inject the pixel code for the plugin
   */
  abstract protected static function injectPixelCode();
}
