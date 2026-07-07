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
 *
 * Injects the browser Pixel bootstrap (base/init/noscript/page-view) into the
 * page, wires the integrations, and flushes queued server events. Created and
 * owned by Signals; it receives the shared Signals + BrowserEvents rather than
 * reaching for them.
 *
 * @internal Not part of the plugin's public API; use the Signals facade.
 */
class FacebookWordpressPixelInjection {
    /**
     * Cache for rendered pixels.
     *
     * @var array
     */
    public static $render_cache = array();

    /**
     * The facade for the signals subsystem.
     *
     * @var Signals
     */
    private $signals;

    /**
     * The browser-side pixel generator.
     *
     * @var BrowserEvents
     */
    private $browser;

    /**
     * Constructor.
     *
     * @param Signals       $signals The signals facade.
     * @param BrowserEvents $browser The browser-side pixel generator.
     */
    public function __construct( Signals $signals, BrowserEvents $browser ) {
        $this->signals = $signals;
        $this->browser = $browser;
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
        $pixel_id = FacebookWordpressOptions::get_active_pixel_id();
        if ( FacebookPluginUtils::is_positive_integer( $pixel_id ) ) {
            add_action(
                'wp_enqueue_scripts',
                array( $this, 'enqueue_signal_script' )
            );
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
            ( new $class_name( $this->signals ) )->inject_pixel_code();
            }
            add_action(
                'wp_footer',
                array( $this, 'send_pending_events' )
            );
        }
    }

    /**
     * Sends any queued server-side events (unless signals are held).
     *
     * @return void
     */
    public function send_pending_events() {
        if ( $this->signals->is_held() ) {
            return;
        }

        $this->signals->flush_pending_events();
    }

    /**
     * Injects the Facebook pixel base code, the FacebookSignal init code, and
     * the page-view code into `wp_head`.
     *
     * @return void
     */
    public function inject_pixel_code() {
        $pixel_id = $this->browser->get_pixel_id();
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
        echo $this->browser->get_pixel_base_code(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->get_facebook_signal_init_code(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->browser->get_pixel_page_view_code(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Injects the Facebook Pixel noscript code into `wp_body_open`.
     *
     * @return void
     */
    public function inject_pixel_noscript_code() {
        echo $this->browser->get_pixel_noscript_code(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Enqueue FacebookSignal helper script.
     *
     * @return void
     */
    public function enqueue_signal_script() {
        wp_enqueue_script(
            'facebook-signal',
            plugins_url( '../../js/facebook_signal.js', __FILE__ ),
            array(),
            FacebookPluginConfig::PLUGIN_VERSION,
            false
        );

        wp_localize_script(
            'facebook-signal',
            'facebookSignalConfig',
            array(
                'cookieName'    => Signals::COOKIE_NAME,
                'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                'signalsAction' => Signals::AJAX_ACTION,
                'signalsNonce'  => wp_create_nonce( Signals::NONCE_ACTION ),
            )
        );
    }

    /**
     * Initialize FacebookSignal with current config.
     *
     * @return string
     */
    private function get_facebook_signal_init_code() {
        $pixel_id  = $this->browser->get_pixel_id();
        $user_info = FacebookPluginUtils::is_internal_user()
            ? array()
            : FacebookWordpressOptions::get_user_info();
			
        $options   = array( 'agent' => FacebookWordpressOptions::get_agent_string() );

        $config = array(
            'held'          => false,
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'releaseAction' => ReleaseSignalsAjax::ACTION,
            'pixelId'       => $pixel_id,
            'attribution'   => (object) array(),
            'capig'         => FacebookWordpressOptions::get_capig(),
        );

        if ( $this->signals->is_held() ) {
            $attribution = array_filter(
                array(
                    'fbp'       => $this->signals->get_attribution_data( 'fbp' ),
                    'fbc'       => $this->signals->get_attribution_data( 'fbc' ),
                    'fbpDomain' => $this->signals->get_attribution_data( 'fbp_domain' ),
                    'fbcDomain' => $this->signals->get_attribution_data( 'fbc_domain' ),
                )
            );
            if ( ! empty( $attribution ) ) {
                $config['attribution'] = $attribution;
            }
        }

        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        return "<script type='text/javascript'>" .
            'FacebookSignal.init(' . wp_json_encode( $config, $flags ) . ');' .
            'FacebookSignal.initPixel(' .
                wp_json_encode( $pixel_id, $flags ) . ',' .
                wp_json_encode( (object) $user_info, $flags ) . ',' .
                wp_json_encode( $options, $flags ) .
            ');' .
            '</script>';
    }
}
