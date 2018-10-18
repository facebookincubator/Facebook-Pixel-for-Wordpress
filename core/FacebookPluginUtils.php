<?php
/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Core;

defined('ABSPATH') or die('Direct access not allowed');

/**
 * Helper functions
 */
class FacebookPluginUtils {
  /**
   * Returns true if id is a positive non-zero integer
   *
   * @access public
   * @param string $pixel_id
   * @return bool
   */
  public static function isPositiveInteger($pixel_id) {
    return isset($pixel_id) && is_numeric($pixel_id) && (int)$pixel_id > 0;
  }
}

