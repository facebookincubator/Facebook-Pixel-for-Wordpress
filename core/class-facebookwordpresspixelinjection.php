<?php
/**
 * Facebook Pixel Plugin FacebookWordpressPixelInjection class.
 *
 * This file contains the main logic for FacebookWordpressPixelInjection.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressPixelInjection class.
 *
 * @return void
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
 * Class FacebookWordpressPixelInjection
 */
class FacebookWordpressPixelInjection {
    /**
     * Cache for rendered pixels.
     *
     * @var array
     */
    public static $render_cache = array();

    /**
     * Constructor for the FacebookWordpressPixelInjection class.
     */
    public function __construct() {
    }

    /**
     * Injects Facebook Pixel code into WordPress.
     *
     * This method injects the necessary Facebook Pixel code into WordPress by
     * using the `wp_head` and `wp_footer` actions.
     * It also injects the necessary code for the no-JavaScript
     * version of the Facebook Pixel.
     *
     * @return void
     */
    public function inject() {
        $pixel_id = FacebookWordpressOptions::get_pixel_id();
        if ( FacebookPluginUtils::is_positive_integer( $pixel_id ) ) {
            add_action(
                'wp_head',
                array( $this, 'inject_pixel_code' )
            );
            add_action(
                'wp_body_open',
                array( $this, 'inject_pixel_noscript_code' )
            );
            foreach (
                FacebookPluginConfig::integration_config() as $key => $value
                ) {
            $class_name = 'FacebookPixelPlugin\\Integration\\' . $value;
            $class_name::inject_pixel_code();
            }
            add_action(
                'wp_footer',
                array( $this, 'send_pending_events' )
            );
        }
    }

    /**
     * Sends any pending Facebook server-side events.
     *
     * This method checks if there are any pending Facebook server-side events,
     * and if so, it sends them by triggering the `send_server_events` action.
     *
     * @return void
     */
    public function send_pending_events() {
        $pending_events =
        FacebookServerSideEvent::get_instance()->get_pending_events();
        if ( count( $pending_events ) > 0 ) {
            do_action(
                'send_server_events',
                $pending_events,
                count( $pending_events )
            );
        }
    }

    /**
     * Injects the Facebook pixel base code, Open Bridge configuration code
     * if CAI is enabled, Facebook pixel initialization code and Facebook
     * pixel page view code.
     *
     * This method is hooked into the `wp_head` action and is responsible
     * for injecting the necessary code to enable the Facebook pixel for
     * the current page. It uses the `FacebookPixel` class to generate
     * the necessary code and injects it into the page.
     *
     * @return void
     */
    public function inject_pixel_code() {
        $pixel_id = FacebookPixel::get_pixel_id();
        if (
            ( isset(
                self::$render_cache[ FacebookPluginConfig::IS_PIXEL_RENDERED ]
            ) &&
            true === self::$render_cache[ FacebookPluginConfig::IS_PIXEL_RENDERED ] )
            ||
            empty( $pixel_id )
            ) {
            return;
        }

        self::$render_cache[ FacebookPluginConfig::IS_PIXEL_RENDERED ] = true;
        echo FacebookPixel::get_pixel_base_code(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $capi_integration_status =
        FacebookWordpressOptions::get_capi_integration_status();
        echo FacebookPixel::get_pixel_init_code( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            FacebookWordpressOptions::get_agent_string(),
            FacebookWordpressOptions::get_user_info(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            '1' === $capi_integration_status // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        );
        echo FacebookPixel::get_pixel_page_view_code(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Injects the Facebook Pixel noscript code.
     *
     * This method is responsible for adding the noscript version of the
     * Facebook Pixel code to the page. It uses the `get_pixel_noscript_code`
     * method from the `FacebookPixel` class to generate the necessary code.
     *
     * @return void
     */
    public function inject_pixel_noscript_code() {
        echo FacebookPixel::get_pixel_noscript_code(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
