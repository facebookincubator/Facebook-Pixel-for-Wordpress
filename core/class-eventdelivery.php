<?php
/**
 * Facebook Pixel Plugin EventDelivery interface.
 *
 * A delivery strategy decides WHERE the browser pixel code for the tracked
 * events is emitted (page footer, an AJAX response key, appended HTML, ...).
 * Integrations choose one as configuration; Signals owns the rendering.
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

namespace FacebookPixelPlugin\Core;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Interface EventDelivery
 */
interface EventDelivery {
    /**
     * Registers the WordPress hook(s) that emit the browser pixel code for the
     * events tracked on $signals for $tracking_name.
     *
     * @param Signals $signals       The dispatcher providing render().
     * @param string  $tracking_name The integration tracking name.
     * @return void
     */
    public function register( $signals, $tracking_name );
}
