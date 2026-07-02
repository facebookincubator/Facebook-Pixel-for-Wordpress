<?php
/**
 * Facebook Pixel Plugin InlineScriptDelivery class.
 *
 * Browser delivery that emits each dispatched event as an inline script via
 * wp_add_inline_script (falling back to WooCommerce's $wc_queued_js when the
 * page is rendered outside a normal enqueue cycle). Used by integrations whose
 * pixel code must ride along with an already-registered script rather than be
 * echoed in the footer or an AJAX response body.
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
 * Class InlineScriptDelivery
 */
class InlineScriptDelivery extends AbstractEventDelivery {
    const HANDLE = 'meta_pixel_inline';

    /**
     * Whether the inline-script carrier handle has been registered this
     * request. Static so multiple deliveries share a single handle.
     *
     * @var bool
     */
    private static $registered = false;

    /**
     * Records the tracking name; the events are emitted immediately on queue(),
     * so there is no deferred hook to register.
     *
     * @param string $tracking_name The integration tracking name.
     * @return void
     */
    public function register( $tracking_name ) {
        $this->tracking_name = $tracking_name;
    }

    /**
     * Renders the event to pixel code and enqueues it inline.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @return void
     */
    public function queue( $event ) {
        $this->store( $event );
        $this->enqueue_code( $this->render_block( $event ) );
    }

    /**
     * Wraps a single event's pixel code in the Meta Pixel comment markers.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @return string
     */
    protected function render_block( $event ) {
        $code = PixelRenderer::render(
            array( $event ),
            $this->tracking_name,
            false
        );

        return sprintf(
            "\n<!-- Meta Pixel Event Code -->\n%s\n<!-- End Meta Pixel Event Code -->\n",
            $code
        );
    }

    /**
     * Enqueues the given code as an inline script, registering a carrier handle
     * once per request. Falls back to WooCommerce's queued-JS buffer when the
     * enqueue API is unavailable.
     *
     * @param string $code The pixel code to enqueue.
     * @return void
     */
    protected function enqueue_code( $code ) {
        if ( ! function_exists( 'wp_add_inline_script' ) ) {
            global $wc_queued_js;
            $wc_queued_js .= "\n" . $code;
            return;
        }

        if ( ! self::$registered ) {
            if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
                wp_register_script(
                    self::HANDLE,
                    '',
                    array(),
                    FacebookPluginConfig::PLUGIN_VERSION,
                    true
                );
            }
            wp_enqueue_script( self::HANDLE );
            self::$registered = true;
        }

        wp_add_inline_script( self::HANDLE, $code );
    }
}
