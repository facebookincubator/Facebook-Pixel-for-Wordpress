<?php
/*
 * Copyright (C) 2017-present, Meta, Inc.
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

  public static function getLoggedInUserInfo() {
    $current_user = wp_get_current_user();
    if (empty($current_user)) {
      return array();
    }

    return array(
      'email' => $current_user->user_email,
      'first_name' => $current_user->user_firstname,
      'last_name' => $current_user->user_lastname,
      'id' => $current_user->ID,
    );
  }

  public static function newGUID()
  {
    if (function_exists('com_create_guid') === true)
    {
        return trim(com_create_guid(), '{}');
    }

    return sprintf(
      '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
      mt_rand(0, 65535),
      mt_rand(0, 65535),
      mt_rand(0, 65535),
      mt_rand(16384, 20479),
      mt_rand(32768, 49151),
      mt_rand(0, 65535),
      mt_rand(0, 65535),
      mt_rand(0, 65535)
    );
  }

  // All standard WordPress user roles are considered internal unless they have
  // the Subscriber role.
  // WooCommerce uses the 'read' capability for its customer role.
  // Also check for the 'upload_files' capability to account for the shop_worker
  // and shop_vendor roles in Easy Digital Downloads.
  // https://wordpress.org/support/article/roles-and-capabilities
  public static function isInternalUser() {
    return current_user_can('edit_posts') || current_user_can('upload_files');
  }

  public static function endsWith( $haystack, $needle ) {
    $length = strlen( $needle );
    if( !$length ) {
      return false;
    }
    return substr( $haystack, -$length ) === $needle;
  }

  public static function string_contains($haystack, $needle) {
    return (bool) strstr($haystack, $needle);
  }
}
