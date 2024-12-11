<?php
/**
 * Facebook Pixel Plugin EventIdGenerator class.
 *
 * This file contains the main logic for EventIdGenerator.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define EventIdGenerator class.
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

/**
 * Class EventIdGenerator
 */
final class EventIdGenerator {
    /**
     * Creates a new guid v4 - via https://stackoverflow.com/a/15875555
     *
     * @return string A 36 character string containing dashes.
     */
    public static function guidv4() {
        $data = openssl_random_pseudo_bytes( 16 );

        $data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );
        $data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );

    return vsprintf(
        '%s%s-%s-%s-%s-%s%s%s',
        str_split( bin2hex( $data ), 4 )
    );
    }
}
