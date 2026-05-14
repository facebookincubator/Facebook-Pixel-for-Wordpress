<?php
/**
 * Copyright (C) 2017-present, Meta, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the License.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Core;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Static per-request paused state.
 */
class FacebookSignalState {
    /**
     * Whether tracking is paused for the current request.
     *
     * @var bool
     */
    private static $paused = false;

    /**
     * Pause tracking for this request.
     *
     * @return void
     */
    public static function pause() {
        self::$paused = true;
    }

    /**
     * Resume tracking for this request.
     *
     * @return void
     */
    public static function resume() {
        self::$paused = false;
    }

    /**
     * Check whether tracking is paused.
     *
     * @return bool
     */
    public static function is_paused() {
        return (bool) apply_filters(
            'facebook_tracking_paused',
            self::$paused
        );
    }
}
