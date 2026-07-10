<?php
/**
 * Facebook Pixel Plugin ThirdPartyIntegrationsUtil class.
 *
 * This file contains the registry for detecting active third-party plugin
 * integrations and gathering active-plugin telemetry for Meta.
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

declare( strict_types=1 );

namespace FacebookPixelPlugin\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized registry for localization integrations and active plugin detection.
 *
 * Provides discovery mechanism for available localization integrations, manages
 * instantiation of integration classes, and tracks active plugin availability
 * to send plugin telemetry data to Facebook/Meta.
 *
 * @since 3.5.9
 */
class ThirdPartyIntegrationsUtil {

    /**
     * Cached list of active plugin display names.
     *
     * @var array
     */
    private static $all_active_plugins = array();

    /**
     * Whether the given third-party integration plugin is installed.
     *
     * @param string $integration_file The plugin's main file (e.g. 'folder/plugin.php').
     * @return bool True if the plugin file exists in the plugins directory.
     */
    public static function is_integration_available( $integration_file ) {
        if ( empty( $integration_file ) ) {
            return false;
        }
        $plugin_dir = defined( 'WP_PLUGIN_DIR' )
            ? WP_PLUGIN_DIR
            : ABSPATH . 'wp-content/plugins';
        return file_exists( trailingslashit( $plugin_dir ) . $integration_file );
    }

    /**
     * Get list of active plugin names
     *
     * @return array Array of active plugin names
     */
    public static function get_all_active_plugin_data(): array {
        if ( ! empty( self::$all_active_plugins ) ) {
            return self::$all_active_plugins;
        }

        try {
            if ( ! function_exists( 'get_plugins' ) ) {
                include_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $active_plugins_list = get_option( 'active_plugins', array() );
            $all_plugins         = get_plugins();
            $active_plugins_data = array();

            foreach ( $active_plugins_list as $plugin_file ) {
                if ( isset( $all_plugins[ $plugin_file ] ) ) {
                    $plugin_data               = $all_plugins[ $plugin_file ];
                        $active_plugins_data[] = $plugin_data['Name'];
                }
            }
            self::$all_active_plugins = $active_plugins_data;
            return $active_plugins_data;
        } catch ( \Exception $e ) {
            return array();
        }
    }
}
