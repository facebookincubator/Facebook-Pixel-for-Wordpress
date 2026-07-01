<?php
/**
 * Facebook Pixel Plugin AjaxHtmlDelivery class.
 *
 * Appends the browser pixel code to the `html` key of a plugin's AJAX return
 * array (e.g. Caldera Forms' caldera_forms_ajax_return).
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
class AjaxHtmlDelivery implements EventDelivery {
    /**
     * The WordPress filter carrying the AJAX return array.
     *
     * @var string
     */
    private $filter;

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
     * @param int    $priority Optional filter priority. Defaults to 20.
     */
    public function __construct( $filter, $priority = 20 ) {
        $this->filter   = $filter;
        $this->priority = $priority;
    }

    /**
     * Registers the return filter that appends the pixel code to $out['html'].
     *
     * @param Signals $signals       The dispatcher providing render().
     * @param string  $tracking_name The integration tracking name.
     * @return void
     */
    public function register( $signals, $tracking_name ) {
        add_filter(
            $this->filter,
            function ( $out ) use ( $signals, $tracking_name ) {
                if ( ! is_array( $out ) || ! isset( $out['html'] ) ) {
                    return $out;
                }
                $code = $signals->render( $tracking_name, true );
                if ( '' !== $code ) {
                    $out['html'] .= $code;
                }
                return $out;
            },
            $this->priority,
            1
        );
    }
}
