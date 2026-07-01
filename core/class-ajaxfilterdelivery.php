<?php
/**
 * Facebook Pixel Plugin AjaxFilterDelivery class.
 *
 * Injects this delivery's queued pixel code into a key of a plugin's AJAX
 * response array (e.g. Contact Form 7's wpcf7_feedback_response), where a
 * front-end listener evaluates it.
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
 * Class AjaxFilterDelivery
 */
class AjaxFilterDelivery extends AbstractEventDelivery {
    /**
     * The WordPress filter carrying the AJAX response array.
     *
     * @var string
     */
    private $filter;

    /**
     * The response array key to place the pixel code under.
     *
     * @var string
     */
    private $key;

    /**
     * Filter priority.
     *
     * @var int
     */
    private $priority;

    /**
     * Constructor.
     *
     * @param string $filter   The AJAX response filter name.
     * @param string $key      The response array key for the pixel code.
     * @param int    $priority Optional filter priority. Defaults to 20.
     */
    public function __construct( $filter, $key = 'fb_pxl_code', $priority = 20 ) {
        $this->filter   = $filter;
        $this->key      = $key;
        $this->priority = $priority;
    }

    /**
     * Registers the response filter that injects this delivery's queued events
     * (unwrapped) into the response array.
     *
     * @param string $tracking_name The integration tracking name.
     * @return void
     */
    public function register( $tracking_name ) {
        $this->tracking_name = $tracking_name;
        $key                 = $this->key;
        add_filter(
            $this->filter,
            function ( $response ) use ( $key ) {
                if ( is_array( $response ) && $this->has_events() ) {
                    $response[ $key ] = $this->render_code( false );
                }
                return $response;
            },
            $this->priority,
            1
        );
    }
}
