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
    return isset($pixel_id) && ctype_digit($pixel_id) && $pixel_id !== '0';
  }

  /**
   * Whether current user is Administrator.
   */
  public static function isAdmin() {
    return current_user_can('install_plugins');
  }
}
