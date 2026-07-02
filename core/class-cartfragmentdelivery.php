<?php
/**
 * Facebook Pixel Plugin CartFragmentDelivery class.
 *
 * Browser delivery for add-to-cart events. On a normal (non-AJAX) request the
 * pixel code is enqueued inline like InlineScriptDelivery. On an AJAX add-to-cart
 * request the code is instead injected into a page container div through the
 * cart-fragments filter, so the pixel fires after the AJAX response replaces
 * that div. The container is printed in the footer at registration time.
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
 * Class CartFragmentDelivery
 */
class CartFragmentDelivery extends InlineScriptDelivery {
    /**
     * The cart-fragments filter carrying the AJAX response.
     *
     * @var string
     */
    private $filter;

    /**
     * The id of the container div the fragment replaces.
     *
     * @var string
     */
    private $div_id;

    /**
     * Constructor.
     *
     * @param string $filter The cart-fragments filter name.
     * @param string $div_id The container div id (without the leading '#').
     */
    public function __construct( $filter, $div_id ) {
        $this->filter = $filter;
        $this->div_id = $div_id;
    }

    /**
     * Prints the container div in the footer and registers the cart-fragments
     * filter that fills it on AJAX add-to-cart requests.
     *
     * @param string $tracking_name The integration tracking name.
     * @return void
     */
    public function register( $tracking_name ) {
        $this->tracking_name = $tracking_name;

        $div_id = $this->div_id;
        add_action(
            'wp_footer',
            function () use ( $div_id ) {
                echo wp_kses(
                    "<div id='" . $div_id . "'></div>",
                    array( 'div' => array( 'id' => array() ) )
                );
            }
        );

        add_filter( $this->filter, array( $this, 'inject_fragment' ), 10, 1 );
    }

    /**
     * On AJAX requests the event is held for the fragment filter; otherwise it
     * is enqueued inline immediately.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @return void
     */
    public function queue( $event ) {
        if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
            $this->store( $event );
        } else {
            parent::queue( $event );
        }
    }

    /**
     * Injects the queued events' pixel code into the container div fragment.
     *
     * @param array $fragments The cart-fragments response array.
     * @return array
     */
    public function inject_fragment( $fragments ) {
        if ( is_array( $fragments ) && $this->has_events() ) {
            $code                             = $this->render_code( true );
            $fragments[ '#' . $this->div_id ] =
                "<div id='" . $this->div_id . "'>" . $code . '</div>';
        }
        return $fragments;
    }
}
