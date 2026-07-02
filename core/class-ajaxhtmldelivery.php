<?php
/**
 * Facebook Pixel Plugin AjaxHtmlDelivery class.
 *
 * Appends this delivery's queued pixel code to the `html` key of a plugin's
 * AJAX return array (e.g. Caldera Forms' caldera_forms_ajax_return).
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
 * Class AjaxHtmlDelivery
 */
class AjaxHtmlDelivery extends AbstractEventDelivery {
    /**
     * The WordPress filter carrying the AJAX return array.
     *
     * @var string
     */
    private $filter;

    /**
     * The response array key to append the code to.
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
     * @param string $filter   The AJAX return filter name.
     * @param string $key      The response array key to append to. Defaults
     *                         to 'html'.
     * @param int    $priority Optional filter priority. Defaults to 20.
     */
    public function __construct( $filter, $key = 'html', $priority = 20 ) {
        $this->filter   = $filter;
        $this->key      = $key;
        $this->priority = $priority;
    }

    /**
     * Registers the return filter that appends this delivery's queued events
     * to the configured response key.
     *
     * @param string $tracking_name The integration tracking name.
     * @return void
     */
    public function register( $tracking_name ) {
        $this->tracking_name = $tracking_name;
        $key                 = $this->key;
        add_filter(
            $this->filter,
            function ( $out ) use ( $key ) {
                if ( is_array( $out ) && isset( $out[ $key ] )
                    && $this->has_events() ) {
                    $out[ $key ] .= $this->render_code( true );
                }
                return $out;
            },
            $this->priority,
            1
        );
    }
}
