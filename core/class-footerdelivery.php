<?php
/**
 * Facebook Pixel Plugin FooterDelivery class.
 *
 * Emits this delivery's queued pixel code inline in wp_footer. Used by
 * integrations whose submission ends on a normal page render.
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
 * Class FooterDelivery
 */
class FooterDelivery extends AbstractEventDelivery {
    /**
     * WordPress action priority for the footer output.
     *
     * @var int
     */
    private $priority;

    /**
     * Constructor.
     *
     * @param int $priority Optional wp_footer priority. Defaults to 11.
     */
    public function __construct( $priority = 11 ) {
        $this->priority = $priority;
    }

    /**
     * Registers a wp_footer callback that echoes this delivery's queued events.
     *
     * @param string $tracking_name The integration tracking name.
     * @return void
     */
    public function register( $tracking_name ) {
        $this->tracking_name = $tracking_name;
        add_action(
            'wp_footer',
            function () {
                if ( $this->has_events() ) {
                    echo $this->render_code( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            },
            $this->priority
        );
    }
}
