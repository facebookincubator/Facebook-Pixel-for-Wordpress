<?php
// phpcs:ignoreFile
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

// Load local dev overrides (gitignored) for staging/development environments
$local_config = plugin_dir_path( __FILE__ ) . 'local-config.php';
if ( file_exists( $local_config ) ) {
    require_once $local_config;
}

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . 'core/class-signals.php';
require_once plugin_dir_path( __FILE__ ) . 'core/signals/class-facebooksignalstate.php';
require_once plugin_dir_path( __FILE__ ) . 'core/signals/class-releasesignalsajax.php';

use FacebookPixelPlugin\Core\Signals;
use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Core\FacebookWordpressSettingsPage;
use FacebookPixelPlugin\Core\FacebookWordpressSettingsRecorder;
use FacebookPixelPlugin\Core\FacebookParamBuilder;

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

    // Initialize ParamBuilder server-side before pixel injection.
    FacebookParamBuilder::server_setup();

    // Signals is the facade for the whole events subsystem — pixel injection,
    // CAPI, consent, open bridge and async send. The bootstrap only boots it.
    ( new Signals() )->boot();

    $this->register_settings_page();
    add_action( 'admin_init', array( $this, 'maybe_reset_upgrade_notice' ) );

    self::update_db_for_wpcom();
    }

    /**
     * Resets the FBL4B upgrade notice dismiss flag when the plugin is updated,
     * so MBE users see the upgrade prompt again after each plugin update.
     */
    public function maybe_reset_upgrade_notice() {
        if ( 'mbe' !== FacebookWordpressOptions::get_connection_type() ) {
            return;
        }
        $stored_version = get_option( 'fb_pixel_plugin_version', '0' );
        $current_version = FacebookPluginConfig::PLUGIN_VERSION;
        if ( version_compare( $stored_version, $current_version, '<' ) ) {
            delete_metadata(
                'user',
                0,
                FacebookPluginConfig::ADMIN_IGNORE_FBL4B_UPGRADE_NOTICE,
                '',
                true
            );
            update_option( 'fb_pixel_plugin_version', $current_version );
        }
    }

    private static function update_db_for_wpcom() {
        if ( false !== get_transient( 'facebook_wpcom_check' ) ) {
            return;
        }

        $is_wp_com_hosted = FacebookWordpressOptions::is_wordpress_com_hosted();

        update_option( 'is_wordpress_com_hosted', $is_wp_com_hosted );
        set_transient( 'facebook_wpcom_check', 1, WEEK_IN_SECONDS );
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
     * Declare WooCommerce HPOS (custom order tables) compatibility.
     *
     * @return void
     */
    public static function declare_hpos_compatibility() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
        'custom_order_tables',
        plugin_basename( __FILE__ ),
        true
        );
    }
    }
}

// HPOS compatibility declaration.
add_action(
    'before_woocommerce_init',
    array( '\\FacebookPixelPlugin\\FacebookForWordpress', 'declare_hpos_compatibility' )
);

new FacebookForWordpress();
