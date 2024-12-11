<?php
/**
 * Plugin Name: Meta pixel for WordPress
 * Plugin URI: https://www.facebook.com/business/help/881403525362441
 * Description: <strong><em>***ATTENTION: After upgrade the plugin may be deactivated due to a known issue, to workaround please refresh this page and activate plugin.***</em></strong> The Facebook pixel is an analytics tool that helps you measure the effectiveness of your advertising. You can use the Facebook pixel to understand the actions people are taking on your website and reach audiences you care about.
 * Author: Facebook
 * Author URI: https://www.facebook.com/
 * Version: {*VERSION_NUMBER*}
 * Text Domain: official-facebook-pixel
 *
 * @package FacebookPixelPlugin
 */

/*
* Copyright (C) 2017-present, Facebook, Inc.
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; version 2 of the License.
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*/

namespace FacebookPixelPlugin;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

use FacebookPixelPlugin\Core\FacebookPixel;
use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Core\FacebookWordpressOpenBridge;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Core\FacebookWordpressPixelInjection;
use FacebookPixelPlugin\Core\FacebookWordpressSettingsPage;
use FacebookPixelPlugin\Core\FacebookWordpressSettingsRecorder;
use FacebookPixelPlugin\Core\ServerEventAsyncTask;

/**
 * FacebookForWordpress root class.
 */
class FacebookForWordpress {
    /**
     * Plugin constructor. Initializes the plugin options, loads the translation files,
     * sets up the Facebook pixel, sets up the pixel injection, and sets up the settings
     * page. Also starts the server event async task.
     */
    public function __construct() {
    FacebookWordpressOptions::initialize();

    load_plugin_textdomain(
        FacebookPluginConfig::TEXT_DOMAIN,
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages/'
    );

    $options = FacebookWordpressOptions::get_options();
    FacebookPixel::initialize( FacebookWordpressOptions::get_pixel_id() );

    add_action( 'init', array( $this, 'register_pixel_injection' ), 0 );
    add_action( 'parse_request', array( $this, 'handle_events_request' ), 0 );

    $this->register_settings_page();

    new ServerEventAsyncTask();
    }


    /**
     * Registers the pixel injection. This method instantiates the
     * FacebookWordpressPixelInjection and calls its inject method.
     *
     * The inject method is responsible for adding the necessary hooks to
     * inject the Facebook pixel code into the footer of the WordPress page.
     */
    public function register_pixel_injection() {
    $injection_obj = new FacebookWordpressPixelInjection();
    $injection_obj->inject();
    }


    /**
     * Registers the settings page for the Facebook for WordPress plugin. This method
     * instantiates the FacebookWordpressSettingsPage and FacebookWordpressSettingsRecorder
     * objects. The settings page object is responsible for adding the necessary hooks
     * and rendering the settings page. The settings recorder object is responsible for
     * recording data about the user's settings and sending it to Meta.
     */
    public function register_settings_page() {
    if ( is_admin() ) {
        $plugin_name = plugin_basename( __FILE__ );
        new FacebookWordpressSettingsPage( $plugin_name );
        ( new FacebookWordpressSettingsRecorder() )->init();
    }
    }


    /**
     * Handles incoming events requests by checking if the request URI
     * ends with the configured open bridge path and if the request
     * method is POST. If both conditions are met, it decodes the JSON
     * payload from the request body and forwards it to the open bridge
     * request handler. Additionally, it sets CORS headers to allow
     * cross-origin requests if the origin is specified in the request
     * headers.
     */
    public function handle_events_request() {
    if ( isset( $_SERVER['REQUEST_URI'] ) ) {
        $request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

        if (
        FacebookPluginUtils::ends_with(
            $request_uri,
            FacebookPluginConfig::OPEN_BRIDGE_PATH
        ) &&
        isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD']
        ) {
        $data = json_decode( file_get_contents( 'php://input' ), true );
        if ( ! is_null( $data ) ) {
            FacebookWordpressOpenBridge::get_instance()->handle_open_bridge_req(
                $data
            );
        }
        if ( isset( $_SERVER['HTTP_ORIGIN'] ) ) {
            $origin = wp_kses( wp_unslash( $_SERVER['HTTP_ORIGIN'] ), array() );
            header( "Access-Control-Allow-Origin: $origin" );
            header( 'Access-Control-Allow-Credentials: true' );
            header( 'Access-Control-Max-Age: 86400' );
        }
        exit();
        }
    }
    }
}

new FacebookForWordpress();
