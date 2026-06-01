<?php
/**
 * Facebook Pixel Plugin FacebookPixelConstants class.
 *
 * This file contains shared constants used across the plugin.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookPixelConstants class.
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
 * Class FacebookPixelConstants
 *
 * Contains shared constants used by FacebookCapiEvent,
 * FacebookServerSideEvent, and other plugin classes.
 */
class FacebookPixelConstants {
    const TEST_EVENT_SESSION = 'test_event_code';
}
