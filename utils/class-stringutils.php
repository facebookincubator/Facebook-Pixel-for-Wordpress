<?php
/**
 * Facebook Pixel Plugin StringUtils class.
 *
 * This file contains string helper methods used across the plugin.
 *
 * @package FacebookPixelPlugin
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

declare( strict_types=1 );

namespace FacebookPixelPlugin\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * String helper methods used across the plugin.
 */
class StringUtils {

    /**
     * Split a full name string into an array containing the first name
     * and last name.
     *
     * If the name contains a space, it will be split into a first name and
     * last name. Otherwise, the entire name will be considered the first
     * name and the last name will be null.
     *
     * @param string $name The full name to split.
     * @return array An array containing the first name and last name.
     */
    public static function split_name( $name ) {
        $first_name = $name;
        $last_name  = null;
        $index      = strpos( $name, ' ' );
        if ( false !== $index ) {
            $first_name = substr( $name, 0, $index );
            $last_name  = substr( $name, $index + 1 );
        }

        return array( $first_name, $last_name );
    }
}
