<?php
/**
 * Copyright (C) 2017-present, Meta, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the License.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Core;

use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\UserData;
use FacebookAds\Object\ServerSide\CustomData;
use FacebookAds\Object\ServerSide\Normalizer;

use FacebookPixelPlugin\Core\AAMFieldsExtractor;
use FacebookPixelPlugin\Core\AAMSettingsFields;
use FacebookPixelPlugin\Core\EventIdGenerator;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class ServerEventFactory
 */
class ServerEventFactory {
    /**
     * Returns a new Event with the given name, populated
     * with the current request's
     *
     * User agent, IP address, and Facebook Browser IDs,
     * as well as the current time and a unique event ID.
     *
     * @param string  $event_name The name of the event to create.
     * @param boolean $prefer_referrer_for_event_src Whether to
     * prefer the referrer URL over the current request URL
     * as the event source URL.
     *
     * @return Event
     */
    public static function new_event(
        $event_name,
        $prefer_referrer_for_event_src = false
    ) {
        $user_data = ( new UserData() )
                    ->setClientIpAddress( self::get_ip_address() )
                    ->setClientUserAgent( self::get_http_user_agent() )
                    ->setFbp( self::get_fbp() )
                    ->setFbc( self::get_fbc() );

        $event = ( new Event() )
                ->setEventName( $event_name )
                ->setEventTime( time() )
                ->setEventId( EventIdGenerator::guidv4() )
            ->setEventSourceUrl(
                self::get_request_uri( $prefer_referrer_for_event_src )
            )
                ->setActionSource( 'website' )
                ->setUserData( $user_data )
                ->setCustomData( new CustomData() );

        return $event;
    }

    /**
     * Scans the HTTP headers for the first valid IP address it can find.
     *
     * @return string|null The first valid IP address found, or null if none
     *                     were found.
     */
    private static function get_ip_address() {
        $headers_to_scan = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );

        foreach ( $headers_to_scan as $header ) {
            if ( isset( $_SERVER[ $header ] ) ) {
                $ip_list = explode( ',', $_SERVER[ $header ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
                foreach ( $ip_list as $ip ) {
                    $trimmed_ip = trim( $ip );
                    if ( self::is_valid_ip_address( $trimmed_ip ) ) {
                        return $trimmed_ip;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Retrieves the User-Agent string from the HTTP request headers.
     *
     * @return string|null The User-Agent string, or null if it was not found.
     */
    private static function get_http_user_agent() {
        $user_agent = null;

        if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $user_agent = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        }

        return $user_agent;
    }

    /**
     * Retrieves the request URI for the current HTTP request.
     *
     * This function constructs the full request URI by considering
     * the protocol, host, and request path. If the
     * $prefer_referrer_for_event_src parameter is true and a referrer
     * URL is present in the HTTP headers, it returns the referrer URL instead.
     *
     * @param boolean $prefer_referrer_for_event_src Whether to
     * prefer the referrer URL over the current request URL.
     *
     * @return string The constructed request URI or the referrer
     * URL if preferred.
     */
    private static function get_request_uri( $prefer_referrer_for_event_src ) {
        if ( $prefer_referrer_for_event_src
        && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
            return sanitize_text_field( $_SERVER['HTTP_REFERER'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        }

            $url = 'http://';
        if ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) {
            $url = 'https://';
        }

        if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
            $url .= sanitize_text_field( $_SERVER['HTTP_HOST'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        }

        if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
            $url .= sanitize_text_field( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        }

        return $url;
    }

    /**
     * Retrieves the Facebook Browser ID (FBP) cookie.
     *
     * This function returns the value of the FBP cookie, which
     * is a unique identifier assigned to a user by Facebook.
     * The FBP cookie is used by the Facebook pixel to track
     * user behavior across multiple sites and sessions.
     *
     * @return string|null The value of the FBP cookie, or
     * null if the cookie is not present.
     */
    private static function get_fbp() {
      $fbp = null;

      if ( ! empty( $_COOKIE['_fbp'] ) ) {
          $fbp = sanitize_text_field( wp_unslash( $_COOKIE['_fbp'] ) );
      }

      return $fbp;
  }

    /**
     * Retrieves the Facebook Click ID (FBC) cookie or session variable.
     *
     * This function returns the value of the FBC cookie or session variable,
     * which is a unique identifier assigned to a user by Facebook. The FBC
     * cookie is used by the Facebook pixel to track user behavior across
     * multiple sites and sessions. If the FBC cookie is not present, the
     * function will attempt to generate an FBC value from the fbclid query
     * parameter, if present.
     *
     * @return string|null The value of the FBC cookie or session variable, or
     *                     null if neither is present.
     */
    private static function get_fbc() {
        $fbc = null;

        if ( ! empty( $_COOKIE['_fbc'] ) ) {
            $fbc              = sanitize_text_field(
                wp_unslash( $_COOKIE['_fbc'] )
            );
            $_SESSION['_fbc'] = $fbc;
        }

        if ( ! $fbc && isset( $_GET['fbclid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $fbclid   = sanitize_text_field( wp_unslash( $_GET['fbclid'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
            $cur_time = (int) ( microtime( true ) * 1000 );
            $fbc      = 'fb.1.' . $cur_time . '.' . rawurldecode( $fbclid );
        }

        if ( ! $fbc && isset( $_SESSION['_fbc'] ) ) {
            $fbc = sanitize_text_field( $_SESSION['_fbc'] );
        }

        if ( $fbc ) {
            $_SESSION['_fbc'] = $fbc;
        }

        return $fbc;
    }

    /**
     * Validates an IP address.
     *
     * This function takes an IP address and returns true if it is valid,
     * false otherwise. The function uses the filter_var function to validate
     * the IP address, and it filters out private and reserved IP addresses.
     *
     * @param string $ip_address The IP address to validate.
     * @return bool True if the IP address is valid, false otherwise.
     */
    private static function is_valid_ip_address( $ip_address ) {
        return filter_var(
            $ip_address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 |
            FILTER_FLAG_IPV6 |
            FILTER_FLAG_NO_PRIV_RANGE |
                FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Given that the data extracted by the integration classes is a mix of
     * user data and custom data,
     * this function splits these fields in two arrays
     * and user data is formatted with the AAM field setting
     *
     * @param array $data Data extracted by the integration.
     * @return array
     */
    private static function split_user_data_and_custom_data( $data ) {
        $user_data        = array();
        $custom_data      = array();
        $key_to_aam_field = array(
            'email'         => AAMSettingsFields::EMAIL,
            'first_name'    => AAMSettingsFields::FIRST_NAME,
            'last_name'     => AAMSettingsFields::LAST_NAME,
            'phone'         => AAMSettingsFields::PHONE,
            'state'         => AAMSettingsFields::STATE,
            'country'       => AAMSettingsFields::COUNTRY,
            'city'          => AAMSettingsFields::CITY,
            'zip'           => AAMSettingsFields::ZIP_CODE,
            'gender'        => AAMSettingsFields::GENDER,
            'date_of_birth' => AAMSettingsFields::DATE_OF_BIRTH,
            'external_id'   => AAMSettingsFields::EXTERNAL_ID,
        );
        foreach ( $data as $key => $value ) {
            if ( isset( $key_to_aam_field[ $key ] ) ) {
                $user_data[ $key_to_aam_field[ $key ] ] = $value;
            } else {
                $custom_data[ $key ] = $value;
            }
        }
        return array(
            'user_data'   => $user_data,
            'custom_data' => $custom_data,
        );
    }

    /**
     * Given a callback and its arguments, it calls the callback
     * with the arguments and extracts the user data and custom
     * data from the result.
     *
     * It uses the AAM setting to normalize the user data and the custom data
     * is used as is.
     *
     * If an exception is thrown in the callback, it's caught and logged,
     * and the function returns an empty Event object.
     *
     * @param string   $event_name The name of the event.
     * @param callable $callback The callback to call.
     * @param array    $arguments The arguments to pass to the callback.
     * @param string   $integration The integration name.
     * @param boolean  $prefer_referrer_for_event_src Whether to prefer
     * the referrer URL over the current request URL as the event source URL.
     *
     * @return Event The event object.
     *
     * @throws \Exception If there was an preprocessing error.
     */
    public static function safe_create_event(
        $event_name,
        $callback,
        $arguments,
        $integration,
        $prefer_referrer_for_event_src = false
    ) {
        $event = self::new_event( $event_name, $prefer_referrer_for_event_src );

        $data              = call_user_func_array( $callback, $arguments );
        $data_split        = self::split_user_data_and_custom_data( $data );
        $user_data_array   = $data_split['user_data'];
        $custom_data_array = $data_split['custom_data'];
        $user_data_array   = AAMFieldsExtractor::get_normalized_user_data(
            $user_data_array
        );

        $user_data = $event->getUserData();
        if ( isset( $data['fbp'] ) ) {
            $user_data->setFbp( $data['fbp'] );
        }
        if ( isset( $data['fbc'] ) ) {
            $user_data->setFbc( $data['fbc'] );
        }
        if (
        isset( $user_data_array[ AAMSettingsFields::EMAIL ] )
        ) {
            $user_data->setEmail(
                $user_data_array[ AAMSettingsFields::EMAIL ]
            );
        }
        if (
        isset( $user_data_array[ AAMSettingsFields::FIRST_NAME ] )
        ) {
            $user_data->setFirstName(
                $user_data_array[ AAMSettingsFields::FIRST_NAME ]
            );
        }
        if (
        isset( $user_data_array[ AAMSettingsFields::LAST_NAME ] )
        ) {
            $user_data->setLastName(
                $user_data_array[ AAMSettingsFields::LAST_NAME ]
            );
        }
        if (
        isset( $user_data_array[ AAMSettingsFields::GENDER ] )
        ) {
            $user_data->setGender(
                $user_data_array[ AAMSettingsFields::GENDER ]
            );
        }
        if (
        isset( $user_data_array[ AAMSettingsFields::DATE_OF_BIRTH ] )
        ) {
            $user_data->setDateOfBirth(
                $user_data_array[ AAMSettingsFields::DATE_OF_BIRTH ]
            );
        }
        if (
        isset( $user_data_array[ AAMSettingsFields::EXTERNAL_ID ] ) &&
        ! is_null( $user_data_array[ AAMSettingsFields::EXTERNAL_ID ] )
        ) {
            if ( is_array( $user_data_array[ AAMSettingsFields::EXTERNAL_ID ] ) ) {
                $external_ids = $user_data_array[ AAMSettingsFields::EXTERNAL_ID ];
                $hashed_eids  = array();
                foreach ( $external_ids as $k => $v ) {
                    $hashed_eids[ $k ] = hash( 'sha256', $v );
                }
                $user_data->setExternalIds( $hashed_eids );
            } else {
                $user_data->setExternalId(
                    hash(
                        'sha256',
                        $user_data_array[ AAMSettingsFields::EXTERNAL_ID ]
                    )
                );
            }
        }
        if (
        isset( $user_data_array[ AAMSettingsFields::PHONE ] )
        ) {
            $user_data->setPhone(
                $user_data_array[ AAMSettingsFields::PHONE ]
            );
        }
        if (
        isset( $user_data_array[ AAMSettingsFields::CITY ] )
        ) {
            $user_data->setCity(
                $user_data_array[ AAMSettingsFields::CITY ]
            );
        }
        if (
        isset( $user_data_array[ AAMSettingsFields::STATE ] )
        ) {
            $user_data->setState(
                $user_data_array[ AAMSettingsFields::STATE ]
            );
        }
        if (
        isset( $user_data_array[ AAMSettingsFields::ZIP_CODE ] )
        ) {
            $user_data->setZipCode(
                $user_data_array[ AAMSettingsFields::ZIP_CODE ]
            );
        }
        if (
        isset( $user_data_array[ AAMSettingsFields::COUNTRY ] )
        ) {
            $user_data->setCountryCode(
                $user_data_array[ AAMSettingsFields::COUNTRY ]
            );
        }

        $custom_data = $event->getCustomData();
        $custom_data->addCustomProperty(
            'fb_integration_tracking',
            $integration
        );

        if ( ! empty( $data['currency'] ) ) {
            $custom_data->setCurrency( $custom_data_array['currency'] );
        }

        if ( ! empty( $data['value'] ) ) {
            $custom_data->setValue( $custom_data_array['value'] );
        }

        if ( ! empty( $data['contents'] ) ) {
            $custom_data->setContents( $custom_data_array['contents'] );
        }

        if ( ! empty( $data['content_ids'] ) ) {
            $custom_data->setContentIds( $custom_data_array['content_ids'] );
        }

        if ( ! empty( $data['content_type'] ) ) {
            $custom_data->setContentType( $custom_data_array['content_type'] );
        }

        if ( ! empty( $data['num_items'] ) ) {
            $custom_data->setNumItems( $custom_data_array['num_items'] );
        }

        if ( ! empty( $data['content_name'] ) ) {
            $custom_data->setContentName( $custom_data_array['content_name'] );
        }

        if ( ! empty( $data['content_category'] ) ) {
        $custom_data->setContentCategory(
            $custom_data_array['content_category']
        );
        }

        return $event;
    }

    /**
     * Split a full name string into an array containing the first name
     * and last name.
     *
     * If the name contains a space, it will be split into a first name and
     * last name. Otherwise, the entire name will be considered the first
     * name and the last name will be null.
     *
     * @param string $name The full name to split.
     * @return array An array containing the first name and last name.
     */
    public static function split_name( $name ) {
        $first_name = $name;
        $last_name  = null;
        $index      = strpos( $name, ' ' );
        if ( false !== $index ) {
            $first_name = substr( $name, 0, $index );
            $last_name  = substr( $name, $index + 1 );
        }

        return array( $first_name, $last_name );
    }
}
