<?php
/**
 * Facebook Pixel Plugin AjaxFilterDelivery class.
 *
 * Injects the raw browser pixel code into a key of a plugin's AJAX response
 * array (e.g. Contact Form 7's wpcf7_feedback_response, WPForms' AJAX success
 * response), where a front-end listener evaluates it.
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
class AjaxFilterDelivery implements EventDelivery {
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
     * Registers the response filter that injects the (unwrapped) pixel code.
     *
     * @param Signals $signals       The dispatcher providing render().
     * @param string  $tracking_name The integration tracking name.
     * @return void
     */
    public function register( $signals, $tracking_name ) {
        $key = $this->key;
        add_filter(
            $this->filter,
            function ( $response ) use ( $signals, $tracking_name, $key ) {
                if ( ! is_array( $response ) ) {
                    return $response;
                }
                $code = $signals->render( $tracking_name, false );
                if ( '' !== $code ) {
                    $response[ $key ] = $code;
                }
                return $response;
            },
            $this->priority,
            1
        );
    }
}
