<?php
/**
 * Facebook Pixel Plugin FacebookWordpressOptions class.
 *
 * This file contains the main logic for FacebookWordpressOptions.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressOptions class.
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

use FacebookAds\Object\ServerSide\AdsPixelSettings;
use FacebookPixelPlugin\Core\FacebookPluginUtils;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class FacebookWordpressOptions
 */
class FacebookWordpressOptions {
    /**
     * Options for the FacebookWordpressOptions class.
     *
     * @var array
     */
    private static $options = array();
    /**
     * User information stored in an array.
     *
     * @var array
     */
    private static $user_info = array();
    /**
     * Version information stored in an array.
     *
     * @var array
     */
    private static $version_info = array();
    /**
     * AAM settings stored in an AdsPixelSettings object.
     *
     * @var AdsPixelSettings|null
     */
    private static $aam_settings = null;
    /**
     * Whether CAPI integration is enabled.
     *
     * @var bool|null
     */
    private static $capi_integration_enabled = null;
    /**
     * Filter for CAPI integration events.
     *
     * @var string|null
     */
    private static $capi_integration_events_filter = null;
    /**
     * The caching status for CAPI PII.
     *
     * @var bool|null
     */
    private static $capi_pii_caching_status = null;
    const AAM_SETTINGS_REFRESH_IN_MINUTES   = 20;

    /**
     * Initialize the options class by setting all the relevant options.
     *
     * This method is called by the Plugin class in its constructor.
     *
     * It sets the options, version info, AAM settings, user info,
     * CAPI integration status, CAPI integration events filter, and
     * CAPI PII caching status.
     */
    public static function initialize() {
        self::init_options();
        self::set_version_info();
        self::set_aam_settings();
        self::set_user_info();
        self::set_capi_integration_status();
        self::set_capi_integration_events_filter();
        self::set_capi_pii_caching_status();
    }

    /**
     * Retrieves the plugin options.
     *
     * @return array The plugin options.
     */
    public static function get_options() {
        return self::$options;
    }

    /**
     * Retrieves the plugin options using the old method name for backwards compatibility.
     *
     * @return array The plugin options.
     */
    public static function getOptions() { // phpcs:ignore
        return self::get_options();
    }

    /**
     * Retrieves the CAPI integration status option.
     *
     * Sets the class property $capi_integration_enabled from the option.
     */
    public static function set_capi_integration_status() {
        self::$capi_integration_enabled =
        \get_option( FacebookPluginConfig::CAPI_INTEGRATION_STATUS );
    }

    /**
     * Retrieves the CAPI integration status option.
     *
     * This function is currently enforcing the option to true for all users.
     *
     * @return string The CAPI integration status option.
     */
    public static function get_capi_integration_status() {
        return '1';
    }

    /**
     * Sets the CAPI PII caching status option.
     *
     * Retrieves the value of the CAPI PII caching status from the WordPress
     * options and assigns it to the class property $capi_pii_caching_status.
     */
    public static function set_capi_pii_caching_status() {
        self::$capi_pii_caching_status
        = \get_option( FacebookPluginConfig::CAPI_PII_CACHING_STATUS );
    }

    /**
     * Retrieves the CAPI PII caching status option.
     *
     * This function returns the value of the CAPI PII caching
     * status from the WordPress options. If the value is not set,
     * it returns the default value.
     *
     * @return string The CAPI PII caching status option.
     */
    public static function get_capi_pii_caching_status() {
        return is_null( self::$capi_pii_caching_status ) ?
        FacebookPluginConfig::CAPI_PII_CACHING_STATUS_DEFAULT :
        self::$capi_pii_caching_status;
    }

    /**
     * Sets the CAPI integration events filter option.
     *
     * Retrieves the value of the CAPI integration events
     * filter from the WordPress options and assigns it to the
     * class property $capi_integration_events_filter.
     */
    public static function set_capi_integration_events_filter() {
        self::$capi_integration_events_filter =
        \get_option( FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER );
    }

    /**
     * Retrieves the CAPI integration events filter option.
     *
     * This function returns the value of the CAPI integration events
     * filter from the WordPress options. If the value is not set, it
     * returns the default value.
     *
     * @return string The CAPI integration events filter option.
     */
    public static function get_capi_integration_events_filter() {
        return is_null( self::$capi_integration_events_filter ) ?
        FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER_DEFAULT :
        self::$capi_integration_events_filter;
    }

    /**
     * Checks if PageView is filtered in the CAPI integration events filter.
     *
     * This function is a convenience method to check if the PageView
     * event is filtered in the CAPI integration events filter.
     * It returns true if the PageView event is
     * filtered, false otherwise.
     *
     * @return bool If PageView is filtered in the CAPI integration
     * events filter.
     */
    public static function get_capi_integration_page_view_filtered() {
        return FacebookPluginUtils::string_contains(
            self::get_capi_integration_events_filter(),
            'PageView'
        );
    }

    /**
     * Retrieves the default pixel ID from the FacebookPluginConfig class.
     *
     * If the default pixel ID is not set, this function
     * returns an empty string.
     *
     * @return string The default pixel ID.
     */
    public static function get_default_pixel_id() {
        return is_null( FacebookPluginConfig::DEFAULT_PIXEL_ID ) ?
        '' : FacebookPluginConfig::DEFAULT_PIXEL_ID;
    }

    /**
     * Retrieves the default access token from the FacebookPluginConfig class.
     *
     * If the default access token is not set, this function
     * returns an empty string.
     *
     * @return string The default access token.
     */
    public static function get_default_access_token() {
        return is_null( FacebookPluginConfig::DEFAULT_ACCESS_TOKEN ) ?
        '' : FacebookPluginConfig::DEFAULT_ACCESS_TOKEN;
    }

    /**
     * Retrieves the default external business ID, which is a unique ID
     * generated by prepending the current time to the default external
     * business ID prefix.
     *
     * @return string The default external business ID.
     */
    public static function get_default_external_business_id() {
        return uniqid(
            FacebookPluginConfig::DEFAULT_EXTERNAL_BUSINESS_ID_PREFIX . time() . '_'
        );
    }

    /**
     * Retrieves the default FBE installed status.
     *
     * This method returns the default status indicating whether the
     * Facebook Business Extension is installed.
     *
     * @return bool The default FBE installed status.
     */
    public static function get_default_is_fbe_installed() {
        return FacebookPluginConfig::DEFAULT_IS_FBE_INSTALLED;
    }

    /**
     * Initializes the options class by setting all the relevant options.
     *
     * This method is called by the Plugin class in its constructor.
     *
     * It sets the options by first checking if the new options are saved
     * in WP database, if so, they are used. If not, the old options are used.
     * If the old options are not present, the default values are used.
     */
    private static function init_options() {
        $old_options = \get_option( FacebookPluginConfig::OLD_SETTINGS_KEY );
        $new_options = \get_option( FacebookPluginConfig::SETTINGS_KEY );
        if ( $new_options ) {
            self::$options = $new_options;
        } elseif ( $old_options ) {
            self::$options = array(
                FacebookPluginConfig::EXTERNAL_BUSINESS_ID_KEY =>
                self::get_default_external_business_id(),
                FacebookPluginConfig::IS_FBE_INSTALLED_KEY =>
                self::get_default_is_fbe_installed(),
            );
            if (
            isset( $old_options[ FacebookPluginConfig::OLD_ACCESS_TOKEN_KEY ] )
            && ! empty( $old_options[ FacebookPluginConfig::OLD_ACCESS_TOKEN_KEY ] )
            ) {
                self::$options[ FacebookPluginConfig::ACCESS_TOKEN_KEY ] =
                $old_options[ FacebookPluginConfig::OLD_ACCESS_TOKEN_KEY ];
            } else {
                self::$options[ FacebookPluginConfig::ACCESS_TOKEN_KEY ] =
                self::get_default_access_token();
            }
            if (
            isset( $old_options[ FacebookPluginConfig::OLD_PIXEL_ID_KEY ] )
            && ! empty( $old_options[ FacebookPluginConfig::OLD_PIXEL_ID_KEY ] )
            && is_numeric( $old_options[ FacebookPluginConfig::OLD_PIXEL_ID_KEY ] )
            ) {
                self::$options[ FacebookPluginConfig::PIXEL_ID_KEY ] =
                $old_options[ FacebookPluginConfig::OLD_PIXEL_ID_KEY ];
            } else {
                self::$options[ FacebookPluginConfig::PIXEL_ID_KEY ] =
                self::get_default_pixel_id();
            }
        } else {
            self::$options = \get_option(
                FacebookPluginConfig::SETTINGS_KEY,
                array(
                    FacebookPluginConfig::PIXEL_ID_KEY     =>
                    self::get_default_pixel_id(),
                    FacebookPluginConfig::ACCESS_TOKEN_KEY =>
                    self::get_default_access_token(),
                    FacebookPluginConfig::EXTERNAL_BUSINESS_ID_KEY =>
                    self::get_default_external_business_id(),
                    FacebookPluginConfig::IS_FBE_INSTALLED_KEY =>
                    self::get_default_is_fbe_installed(),
                )
            );
        }
    }

    /**
     * Retrieves the Facebook pixel ID.
     *
     * If the pixel ID is not set in the options, the default pixel
     * ID is returned.
     *
     * @return string The Facebook pixel ID.
     */
    public static function get_pixel_id() {
        if ( isset( self::$options[ FacebookPluginConfig::PIXEL_ID_KEY ] ) ) {
            return self::$options[ FacebookPluginConfig::PIXEL_ID_KEY ];
        }

        return self::get_default_pixel_id();
    }

    /**
     * Retrieves the Facebook pixel ID using the old method name for backwards compatibility.
     *
     * @return string The Facebook pixel ID.
     */
    public static function getPixelId() { // phpcs:ignore
        return self::get_pixel_id();
    }

    /**
     * Retrieves the external business ID.
     *
     * If the external business ID is not set in the options,
     * the default external
     * business ID is returned.
     *
     * @return string The external business ID.
     */
    public static function get_external_business_id() {
        if (
            isset(
                self::$options[ FacebookPluginConfig::EXTERNAL_BUSINESS_ID_KEY ]
            )
            ) {
            return self::$options[ FacebookPluginConfig::EXTERNAL_BUSINESS_ID_KEY ];
        }

        return self::get_default_external_business_id();
    }

    /**
     * Retrieves the status indicating whether the Facebook Business
     * Extension is installed.
     *
     * If the FBE installed status is not set in the options, the
     * default FBE installed status is returned.
     *
     * @return string The FBE installed status.
     */
    public static function get_is_fbe_installed() {
        if (
            isset( self::$options[ FacebookPluginConfig::IS_FBE_INSTALLED_KEY ] )
            ) {
            return self::$options[ FacebookPluginConfig::IS_FBE_INSTALLED_KEY ];
        }

        return self::get_default_is_fbe_installed();
    }

    /**
     * Retrieves the Facebook access token.
     *
     * If the access token is not set in the options, the
     * default access token is returned.
     *
     * @return string The Facebook access token.
     */
    public static function get_access_token() {
        if ( isset( self::$options[ FacebookPluginConfig::ACCESS_TOKEN_KEY ] ) ) {
            return self::$options[ FacebookPluginConfig::ACCESS_TOKEN_KEY ];
        }

        return self::get_default_access_token();
    }

    /**
     * Retrieves the user information.
     *
     * This function returns the user information stored in the class.
     * The user information is an array that may contain keys like 'email',
     * 'first_name', and 'last_name', depending on the data available for the
     * current user.
     *
     * @return array The user information.
     */
    public static function get_user_info() {
        return self::$user_info;
    }

    /**
     * Registers an action hook to set the user information.
     *
     * The 'register_user_info' method is called when the 'init'
     * action is triggered.
     * The method sets the user information stored in the class.
     *
     * @return void
     */
    public static function set_user_info() {
        add_action(
            'init',
            array(
                'FacebookPixelPlugin\\Core\\FacebookWordpressOptions',
                'register_user_info',
            ),
            0
        );
    }

    /**
     * Registers the user information.
     *
     * This function is called when the 'init' action is triggered, and it
     * sets the user information stored in the class. The user information
     * is an array that may contain keys like 'email', 'first_name', and
     * 'last_name', depending on the data available for the current user.
     *
     * @return void
     */
    public static function register_user_info() {
        $current_user = wp_get_current_user();
        if ( 0 === $current_user->ID ) {
            self::$user_info = array();
        } else {
            $user_info       = array_filter(
                array(
                    AAMSettingsFields::EMAIL      => $current_user->user_email,
                    AAMSettingsFields::FIRST_NAME => $current_user->user_firstname,
                    AAMSettingsFields::LAST_NAME  => $current_user->user_lastname,
                ),
                function ( $value ) {
                    return null !== $value && '' !== $value;
                }
            );
            self::$user_info =
            AAMFieldsExtractor::get_normalized_user_data( $user_info );
        }
    }

    /**
     * Retrieves the version information.
     *
     * The version information is an array that contains the keys
     * 'pluginVersion' and 'source'. The 'pluginVersion' key contains
     * the current version of the plugin, and the 'source' key
     * contains the source of the plugin.
     *
     * @return array The version information.
     */
    public static function get_version_info() {
        return self::$version_info;
    }

    /**
     * Sets the version information.
     *
     * The version information is an array that contains the
     * keys 'pluginVersion', 'source', and 'version'. The
     * 'pluginVersion' key contains the current version of the
     * plugin, the 'source' key contains the source of the plugin,
     * and the 'version' key contains the current version of WordPress.
     *
     * @return void
     */
    public static function set_version_info() {
        global $wp_version;

        self::$version_info = array(
            'pluginVersion' => FacebookPluginConfig::PLUGIN_VERSION,
            'source'        => FacebookPluginConfig::SOURCE,
            'version'       => $wp_version,
        );
    }

    /**
     * Constructs the agent string from version information.
     *
     * This function returns a formatted string that combines the source,
     * WordPress version, and plugin version from the version information array.
     *
     * @return string The constructed agent string.
     */
    public static function get_agent_string() {
    return sprintf(
        '%s-%s-%s',
        self::$version_info['source'],
        self::$version_info['version'],
        self::$version_info['pluginVersion']
    );
    }

    /**
     * Retrieves the AdsPixelSettings object from the AAM settings.
     *
     * @return AdsPixelSettings The AdsPixelSettings object
     * containing the AAM settings.
     */
    public static function get_aam_settings() {
        return self::$aam_settings;
    }

    /**
     * Retrieves the AdsPixelSettings object from the AAM settings.
     *
     * This method first checks if there are any AAM settings cached in the
     * WordPress database. If there are, they are converted into an
     * AdsPixelSettings object and returned. If there are no cached
     * settings, the method fetches the settings from the Facebook
     * domain and caches them in the WordPress
     * database if they are not null.
     *
     * @return void
     */
    private static function set_fbe_based_aam_settings() {
        $installed_pixel   = self::get_pixel_id();
        $settings_as_array =
        get_transient( FacebookPluginConfig::AAM_SETTINGS_KEY );
        if ( false !== $settings_as_array ) {
            $aam_settings = new AdsPixelSettings();
            $aam_settings->setPixelId( $settings_as_array['pixelId'] );
            $aam_settings->setEnableAutomaticMatching(
                $settings_as_array['enableAutomaticMatching']
            );
            $aam_settings->setEnabledAutomaticMatchingFields(
                $settings_as_array['enabledAutomaticMatchingFields']
            );
            if ( $installed_pixel == $aam_settings->getPixelId() ) { // phpcs:ignore Universal.Operators.StrictComparisons
            self::$aam_settings = $aam_settings;
            }
        }
        if ( ! self::$aam_settings ) {
            $refresh_interval =
            self::AAM_SETTINGS_REFRESH_IN_MINUTES * MINUTE_IN_SECONDS;
            $aam_settings     =
            AdsPixelSettings::buildFromPixelId( $installed_pixel );
            if ( $aam_settings ) {
                $settings_as_array = array(
                    'pixelId'                        => $aam_settings->getPixelId(),
                    'enableAutomaticMatching'        =>
                    $aam_settings->getEnableAutomaticMatching(),
                    'enabledAutomaticMatchingFields' =>
                    $aam_settings->getEnabledAutomaticMatchingFields(),
                );
                set_transient(
                    FacebookPluginConfig::AAM_SETTINGS_KEY,
                    $settings_as_array,
                    $refresh_interval
                );
                self::$aam_settings = $aam_settings;
            }
        }
    }

    /**
     * If the old settings are present and the user has opted-in to use PII,
     * the AAM settings are set to enable automatic matching and all its fields.
     * Otherwise, the AAM settings are set to disable automatic matching
     * and all its fields.
     */
    private static function set_old_aam_settings() {
        $old_options = \get_option( FacebookPluginConfig::OLD_SETTINGS_KEY );
        if ( $old_options
            && isset( $old_options[ FacebookPluginConfig::OLD_USE_PII ] )
            && $old_options[ FacebookPluginConfig::OLD_USE_PII ] ) {
            self::$aam_settings = new AdsPixelSettings(
                array(
                    'enableAutomaticMatching'        => true,
                    'enabledAutomaticMatchingFields' =>
                    AAMSettingsFields::get_all_fields(),
                )
            );
        } else {
            self::$aam_settings = new AdsPixelSettings(
                array(
                    'enableAutomaticMatching'        => false,
                    'enabledAutomaticMatchingFields' => array(),
                )
            );
        }
    }

    /**
     * Sets the AdsPixelSettings based on the installation status.
     *
     * This method initializes the AAM settings to null and
     * checks if a pixel ID is set.
     * If no pixel ID is found, the method returns early.
     * If the Facebook Business Extension
     * (FBE) is installed, the AAM settings are set using
     * the FBE-based settings.
     * Otherwise, the old AAM settings are used.
     *
     * @return void
     */
    private static function set_aam_settings() {
        self::$aam_settings = null;
        if ( empty( self::get_pixel_id() ) ) {
            return;
        }
        if ( self::get_is_fbe_installed() ) {
            self::set_fbe_based_aam_settings();
        } else {
            self::set_old_aam_settings();
        }
    }
}
