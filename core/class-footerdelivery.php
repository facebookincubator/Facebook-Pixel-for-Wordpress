<?php
/**
 * Facebook Pixel Plugin FooterDelivery class.
 *
 * Emits the browser pixel code inline in wp_footer. Used by integrations whose
 * submission ends on a normal page render (no AJAX response to enrich).
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
class FooterDelivery implements EventDelivery {
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
     * Registers a wp_footer callback that echoes the rendered pixel code.
     *
     * @param Signals $signals       The dispatcher providing render().
     * @param string  $tracking_name The integration tracking name.
     * @return void
     */
    public function register( $signals, $tracking_name ) {
        add_action(
            'wp_footer',
            function () use ( $signals, $tracking_name ) {
                $code = $signals->render( $tracking_name, true );
                if ( '' !== $code ) {
                    echo $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            },
            $this->priority
        );
    }
}
