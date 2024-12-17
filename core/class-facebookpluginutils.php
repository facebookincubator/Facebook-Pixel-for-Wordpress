<?php
/**
 * Facebook Pixel Plugin FacebookPluginUtils class.
 *
 * This file contains the main logic for FacebookPluginUtils.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookPluginUtils class.
 *
 * @return void
 */

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

namespace FacebookPixelPlugin\Core;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Helper functions
 */
class FacebookPluginUtils {
    /**
     * Returns true if id is a positive non-zero integer
     *
     * @access public
     * @param string $pixel_id The id to check.
     * @return bool
     */
    public static function is_positive_integer( $pixel_id ) {
        return isset( $pixel_id )
        && ctype_digit( $pixel_id ) && '0' !== $pixel_id;
    }

    /**
     * Gets the information of the currently logged in user.
     *
     * @access public
     * @return array An associative array with the following keys:
     *               'email': The user's email address.
     *               'first_name': The user's first name.
     *               'last_name': The user's last name.
     *               'id': The user's ID.
     */
    public static function get_logged_in_user_info() {
        $current_user = wp_get_current_user();
        if ( empty( $current_user ) ) {
            return array();
        }

        return array(
            'email'      => $current_user->user_email,
            'first_name' => $current_user->user_firstname,
            'last_name'  => $current_user->user_lastname,
            'id'         => $current_user->ID,
        );
    }

    /**
     * Generates a random GUID.
     *
     * @return string The generated GUID.
     */
    public static function new_guid() {
        if ( function_exists( 'com_create_guid' ) === true ) {
            return trim( com_create_guid(), '{}' );
        }

        return sprintf(
            '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
            wp_rand( 0, 65535 ),
            wp_rand( 0, 65535 ),
            wp_rand( 0, 65535 ),
            wp_rand( 16384, 20479 ),
            wp_rand( 32768, 49151 ),
            wp_rand( 0, 65535 ),
            wp_rand( 0, 65535 ),
            wp_rand( 0, 65535 )
        );
    }

    /**
     * All standard WordPress user roles are considered internal
     * unless they have the Subscriber role.
     * WooCommerce uses the 'read' capability for its customer role.
     * Also check for the 'upload_files' capability to account for the
     * shop_worker and shop_vendor roles in Easy Digital Downloads.
     * https://wordpress.org/support/article/roles-and-capabilities
     *
     * @return bool
     */
    public static function is_internal_user() {
        return current_user_can( 'edit_posts' )
        || current_user_can( 'upload_files' );
    }

    /**
     * Checks if a string ends with a specified substring.
     *
     * @param string $haystack The string to search in.
     * @param string $needle The substring to search for at the
     * end of $haystack.
     * @return bool True if $haystack ends with $needle, false otherwise.
     */
    public static function ends_with( $haystack, $needle ) {
        $length = strlen( $needle );
        if ( ! $length ) {
            return false;
        }
        return substr( $haystack, -$length ) === $needle;
    }

    /**
     * Checks if a string contains a specified substring.
     *
     * @param string $haystack The string to search in.
     * @param string $needle The substring to search for within $haystack.
     * @return bool True if $haystack contains $needle, false otherwise.
     */
    public static function string_contains( $haystack, $needle ) {
        return (bool) strstr( $haystack, $needle );
    }
}
