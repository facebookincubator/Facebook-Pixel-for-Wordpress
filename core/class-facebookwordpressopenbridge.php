<?php
/**
 * Facebook Pixel Plugin FacebookWordpressOpenBridge class.
 *
 * This file contains the main logic for FacebookWordpressOpenBridge.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressOpenBridge class.
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

use FacebookPixelPlugin\Core\AAMSettingsFields;
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class FacebookWordpressOpenBridge
 */
class FacebookWordpressOpenBridge {
    const ADVANCED_MATCHING_LABEL = 'fb.advanced_matching';
    const CUSTOM_DATA_LABEL       = 'custom_data';
    const EXTERNAL_ID_COOKIE      = 'obeid';

    /**
     * The instance of the FacebookWordpressOpenBridge class.
     *
     * @var FacebookWordpressOpenBridge
     */
    private static $instance = null;

    /**
     * The list of blocked events.
     *
     * @var array
     */
    private static $blocked_events = array(
        'SubscribedButtonClick',
        'Microdata',
        'InputData',
    );

    /**
     * Class constructor.
     */
    public function __construct() {
    }

    /**
     * Retrieves the instance of FacebookWordpressOpenBridge class.
     *
     * @return FacebookWordpressOpenBridge The instance of
     * FacebookWordpressOpenBridge class.
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new FacebookWordpressOpenBridge();
        }
        return self::$instance;
    }

    /**
     * Starts a new PHP session if one is not already active.
     *
     * This method checks if a session is already active using `session_id()`.
     * If no session is active, it sets the session cookie parameters based
     * on the PHP version and starts a new session. It also ensures that the
     * EXTERNAL_ID_COOKIE is set in the session, generating a new GUID if
     * necessary.
     *
     * @return void
     */
    private static function start_new_php_session_if_needed() {
        if ( session_id() ) {
            return;
        }

        $secure      = false;
        $httponly    = true;
        $samesite    = 'lax';
        $maxlifetime = 7776000;
        if ( PHP_VERSION_ID < 70300 ) {
            session_set_cookie_params(
                $maxlifetime,
                '/; samesite=' . $samesite,
                isset( $_SERVER['HTTP_HOST'] )
                ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) :
                '',
                $secure,
                $httponly
            );
        } else {
            session_set_cookie_params(
                array(
                    'lifetime' => $maxlifetime,
                    'path'     => '/',
                    'domain'   => isset( $_SERVER['HTTP_HOST'] ) ?
                    sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '',
                    'secure'   => $secure,
                    'httponly' => $httponly,
                    'samesite' => $samesite,
                )
            );
        }

        session_start();

        $_SESSION[ self::EXTERNAL_ID_COOKIE ] = isset(
            $_SESSION[ self::EXTERNAL_ID_COOKIE ]
        ) ? sanitize_text_field( $_SESSION[ self::EXTERNAL_ID_COOKIE ] ) :
        FacebookPluginUtils::new_guid();
    }

    /**
     * Handles an incoming Open Bridge request from the front-end.
     *
     * Starts a new PHP session if one is not already active, and extracts the
     * event name, event ID, and event data from the request. If the event name
     * is in the list of blocked events, the method returns early without taking
     * any action. Otherwise, it creates a ServerEvent using the event name and
     * data, and sends it to the Facebook pixel servers.
     *
     * @param array $data The event data, including the event name and ID.
     *
     * @return void
     */
    public function handle_open_bridge_req( $data ) {

        self::start_new_php_session_if_needed();

        $event_name = $data['event_name'];
        if ( in_array( $event_name, self::$blocked_events, true ) ) {
            return;
        }
        $event = ServerEventFactory::safe_create_event(
            $event_name,
            array( $this, 'extract_from_databag' ),
            array( $data ),
            'wp-cloudbridge-plugin',
            true
        );
        $event->setEventId( $data['event_id'] );
        FacebookServerSideEvent::send( array( $event ) );
    }

    /**
     * Extracts the user data and custom data from the given databag.
     *
     * @param array $databag The databag containing the event data.
     *
     * @return array The extracted data, including user data and custom data.
     */
    public function extract_from_databag( $databag ) {
        $current_user = self::get_pii_from_session();

        $event_data = array(
            'email'            => self::get_email( $current_user, $databag ),
            'first_name'       =>
            self::get_first_name( $current_user, $databag ),
            'last_name'        =>
            self::get_last_name( $current_user, $databag ),
            'external_id'      =>
            self::get_external_id( $current_user, $databag ),
            'phone'            => self::get_phone( $current_user, $databag ),
            'state'            => self::get_state( $current_user, $databag ),
            'country'          => self::get_country( $current_user, $databag ),
            'city'             => self::get_city( $current_user, $databag ),
            'zip'              => self::get_zip( $current_user, $databag ),
            'gender'           =>
            self::get_aam_field( AAMSettingsFields::GENDER, $databag ),
            'date_of_birth'    =>
            self::get_aam_field( AAMSettingsFields::DATE_OF_BIRTH, $databag ),
            'currency'         => self::get_custom_data( 'currency', $databag ),
            'value'            => self::get_custom_data( 'value', $databag ),
            'content_type'     =>
            self::get_custom_data( 'content_type', $databag ),
            'content_name'     =>
            self::get_custom_data( 'content_name', $databag ),
            'content_ids'      =>
            self::get_custom_data_array( 'content_ids', $databag ),
            'content_category' =>
            self::get_custom_data( 'content_category', $databag ),
        );
        if ( isset( $databag['fb.fbp'] ) ) {
            $event_data['fbp'] = $databag['fb.fbp'];
        }
        if ( isset( $databag['fb.clickID'] ) ) {
            $event_data['fbc'] = $databag['fb.clickID'];
        }
        return $event_data;
    }

    /**
     * Retrieves PII from the logged in user's session.
     *
     * This function retrieves PII data (email, first name, last name, phone
     * number, city, state, zip, country) from the logged in user's session.
     * If the data is not available in the session, it retrieves the data from
     * the WordPress user meta table.
     *
     * @return array The user's PII data.
     *
     * @since 1.0.0
     */
    private static function get_pii_from_session() {
        $current_user            = array_filter(
            FacebookPluginUtils::get_logged_in_user_info()
        );
        $capi_pii_caching_status =
        FacebookWordpressOptions::get_capi_pii_caching_status();

        if ( empty( $current_user ) && '1' === $capi_pii_caching_status ) {

          if ( isset( $_SESSION[ AAMSettingsFields::EMAIL ] ) ) {
            $current_user['email'] = sanitize_text_field(
                $_SESSION[ AAMSettingsFields::EMAIL ]
            );
          }

          if ( isset( $_SESSION[ AAMSettingsFields::FIRST_NAME ] ) ) {
              $current_user['first_name'] =
              sanitize_text_field( $_SESSION[ AAMSettingsFields::FIRST_NAME ] );
          }

          if ( isset( $_SESSION[ AAMSettingsFields::LAST_NAME ] ) ) {
              $current_user['last_name'] =
              sanitize_text_field( $_SESSION[ AAMSettingsFields::LAST_NAME ] );
          }

          if ( isset( $_SESSION[ AAMSettingsFields::PHONE ] ) ) {
              $current_user['phone'] =
              sanitize_text_field( $_SESSION[ AAMSettingsFields::PHONE ] );
          }

          return array_filter( $current_user );
        }

        $user_id = get_current_user_id();
        if ( 0 !== $user_id ) {
            $current_user['city']    = get_user_meta(
                $user_id,
                'billing_city',
                true
            );
            $current_user['zip']     = get_user_meta(
                $user_id,
                'billing_postcode',
                true
            );
            $current_user['country'] = get_user_meta(
                $user_id,
                'billing_country',
                true
            );
            $current_user['state']   = get_user_meta(
                $user_id,
                'billing_state',
                true
            );
            $current_user['phone']   = get_user_meta(
                $user_id,
                'billing_phone',
                true
            );
        }
        return array_filter( $current_user );
    }

    /**
     * Get the user's email address.
     *
     * If the user data contains an email, use that. Otherwise
     *  use the email from the AAM settings.
     *
     * @param array $current_user_data The user data.
     * @param array $pixel_data The AAM settings.
     *
     * @return string The user's email address.
     */
    private static function get_email( $current_user_data, $pixel_data ) {
        if ( isset( $current_user_data['email'] ) ) {
            return $current_user_data['email'];
        }
        return self::get_aam_field( AAMSettingsFields::EMAIL, $pixel_data );
    }

    /**
     * Get the user's first name.
     *
     * If the user data contains a first name, use that. Otherwise use
     * the first name from the AAM settings.
     *
     * @param array $current_user_data The user data.
     * @param array $pixel_data The AAM settings.
     *
     * @return string The user's first name.
     */
    private static function get_first_name( $current_user_data, $pixel_data ) {
        if ( isset( $current_user_data['first_name'] ) ) {
            return $current_user_data['first_name'];
        }
        return self::get_aam_field(
            AAMSettingsFields::FIRST_NAME,
            $pixel_data
        );
    }

    /**
     * Get the user's last name.
     *
     * If the user data contains a last name, use that. Otherwise
     * use the last name from the AAM settings.
     *
     * @param array $current_user_data The user data.
     * @param array $pixel_data The AAM settings.
     *
     * @return string The user's last name.
     */
    private static function get_last_name( $current_user_data, $pixel_data ) {
        if ( isset( $current_user_data['last_name'] ) ) {
            return $current_user_data['last_name'];
        }
        return self::get_aam_field( AAMSettingsFields::LAST_NAME, $pixel_data );
    }

    /**
     * Get the user's external ID.
     *
     * If the user data contains an ID, use that. Otherwise use the
     * external ID from the AAM settings.
     * If the external ID is set in the session, use that as well.
     *
     * @param array $current_user_data The user data.
     * @param array $pixel_data The AAM settings.
     *
     * @return string[] The user's external ID.
     */
    private static function get_external_id( $current_user_data, $pixel_data ) {
        $external_ids = array();

        if ( isset( $current_user_data['id'] ) ) {
            $external_ids[] = (string) $current_user_data['id'];
        }

        $temp_external_id = self::get_aam_field(
            AAMSettingsFields::EXTERNAL_ID,
            $pixel_data
        );

        if ( $temp_external_id ) {
            $external_ids[] = $temp_external_id;
        }

        if ( isset( $_SESSION[ self::EXTERNAL_ID_COOKIE ] ) ) {
            $external_ids[] = sanitize_text_field(
                $_SESSION[ self::EXTERNAL_ID_COOKIE ]
            );
        }
        return $external_ids;
    }

    /**
     * Gets the user's phone.
     *
     * If the phone is set in the current user data, use that. Otherwise use the
     * value from the AAM settings.
     *
     * @param array $current_user_data The user data.
     * @param array $pixel_data The AAM settings.
     *
     * @return string The user's phone.
     */
    private static function get_phone( $current_user_data, $pixel_data ) {
        if ( isset( $current_user_data['phone'] ) ) {
            return $current_user_data['phone'];
        }
        return self::get_aam_field( AAMSettingsFields::PHONE, $pixel_data );
    }

    /**
     * Gets the user's city.
     *
     * If the city is set in the current user data, use that. Otherwise use the
     * value from the AAM settings.
     *
     * @param array $current_user_data The user data.
     * @param array $pixel_data The AAM settings.
     *
     * @return string The user's city.
     */
    private static function get_city( $current_user_data, $pixel_data ) {
        if ( isset( $current_user_data['city'] ) ) {
            return $current_user_data['city'];
        }
        return self::get_aam_field( AAMSettingsFields::CITY, $pixel_data );
    }

    /**
     * Gets the user's zip code.
     *
     * If the user data contains a zip code, use that. Otherwise
     * use the zip code from the AAM settings.
     *
     * @param array $current_user_data The user data.
     * @param array $pixel_data The AAM settings.
     *
     * @return string The user's zip code.
     */
    private static function get_zip( $current_user_data, $pixel_data ) {
        if ( isset( $current_user_data['zip'] ) ) {
            return $current_user_data['zip'];
        }
        return self::get_aam_field( AAMSettingsFields::ZIP_CODE, $pixel_data );
    }

    /**
     * Gets the user's country.
     *
     * If the country is set in the current user data, use that.
     * Otherwise use the value from the AAM settings.
     *
     * @param array $current_user_data The user data.
     * @param array $pixel_data The AAM settings.
     *
     * @return string The user's country.
     */
    private static function get_country( $current_user_data, $pixel_data ) {
        if ( isset( $current_user_data['country'] ) ) {
            return $current_user_data['country'];
        }
        return self::get_aam_field( AAMSettingsFields::COUNTRY, $pixel_data );
    }

    /**
     * Gets the user's state.
     *
     * If the user data contains a state, use that. Otherwise use
     * the state from the AAM settings.
     *
     * @param array $current_user_data The user data.
     * @param array $pixel_data The AAM settings.
     *
     * @return string The user's state.
     */
    private static function get_state( $current_user_data, $pixel_data ) {
        if ( isset( $current_user_data['state'] ) ) {
            return $current_user_data['state'];
        }
        return self::get_aam_field( AAMSettingsFields::STATE, $pixel_data );
    }

    /**
     * Retrieves a value from the advanced matching settings.
     *
     * Retrieves a value from the advanced matching settings array and
     * stores it in the session. If the key is not found in the advanced
     * matching settings, an empty string is returned.
     *
     * @param string $key The key of the value to retrieve.
     * @param array  $pixel_data The array containing the
     * advanced matching settings.
     *
     * @return string The value associated with the given key
     * if found, otherwise an empty string.
     */
    private static function get_aam_field( $key, $pixel_data ) {
        if ( ! isset( $pixel_data[ self::ADVANCED_MATCHING_LABEL ] ) ) {
            return '';
        }
        if ( isset( $pixel_data[ self::ADVANCED_MATCHING_LABEL ][ $key ] ) ) {
            $value            =
            $pixel_data[ self::ADVANCED_MATCHING_LABEL ][ $key ];
            $_SESSION[ $key ] = $value;
            return $value;
        }
        return '';
    }

    /**
     * Retrieves a custom data value from the given pixel data.
     *
     * @param string $key The key of the custom data value.
     * @param array  $pixel_data The array containing the custom data.
     *
     * @return string The custom data value if found, otherwise an empty string.
     */
    private static function get_custom_data( $key, $pixel_data ) {
        if ( ! isset( $pixel_data[ self::CUSTOM_DATA_LABEL ] ) ) {
            return '';
        }
        if ( isset( $pixel_data[ self::CUSTOM_DATA_LABEL ][ $key ] ) ) {
            return $pixel_data[ self::CUSTOM_DATA_LABEL ][ $key ];
        }
        return '';
    }

    /**
     * Retrieves an array of custom data based on the provided key.
     *
     * This function checks if the custom data label exists
     * within the pixel data.
     * If the specified key is found, it returns the corresponding
     * custom data array.
     * If the key is not found, it returns an empty array.
     *
     * @param string $key The key to retrieve the custom data array for.
     * @param array  $pixel_data The array containing the custom data.
     *
     * @return array|string The custom data array if the key is
     * found, otherwise an empty array.
     */
    private static function get_custom_data_array( $key, $pixel_data ) {
        if ( ! isset( $pixel_data[ self::CUSTOM_DATA_LABEL ] ) ) {
            return '';
        }
        if ( isset( $pixel_data[ self::CUSTOM_DATA_LABEL ][ $key ] ) ) {
            return $pixel_data[ self::CUSTOM_DATA_LABEL ][ $key ];
        }
        return array();
    }
}
