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

use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\UserData;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\CustomData;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Content;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Builds the plugin's single backend event representation: a ServerSide\Event.
 *
 * Two entry points:
 *  - create()            from detected activity (a standard-keyed data array).
 *  - create_from_array() from a full event array (Graph-API shape).
 *
 * The current request's user identifiers (IP, user agent, fbp, fbc) come from
 * UserPiiResolver; this class only assembles Events.
 */
class EventFactory {
    /**
     * Builds an Event from a standard-keyed data array (detected activity).
     *
     * The caller extracts the data (running any integration callback itself)
     * and passes the resulting array here. User fields are AAM-normalized;
     * custom fields are set as-is.
     *
     * @param string  $event_name    The name of the event.
     * @param array   $data          Standard-keyed event data. Defaults to an
     *                               empty array to build a bare base event.
     * @param string  $tracking_name The integration tracking name. When empty,
     *                               no fb_integration_tracking property is set.
     * @param boolean $prefer_referrer Whether to prefer the referrer URL over
     *                                 the current request URL as event source.
     *
     * @return Event The event object.
     */
    public static function create(
        $event_name,
        $data = array(),
        $tracking_name = '',
        $prefer_referrer = false
    ) {
        $event = self::new_event( $event_name, $prefer_referrer );

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
        if ( ! empty( $tracking_name ) ) {
            $custom_data->addCustomProperty(
                'fb_integration_tracking',
                $tracking_name
            );
        }

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
     * Converts a raw event array (Graph API format) into an Event object.
     *
     * @param array $event_as_array The event in array form.
     * @return Event
     */
    public static function create_from_array( $event_as_array ) {
        $event = new Event( $event_as_array );
        if ( isset( $event_as_array['user_data'] ) ) {
            $user_data = new UserData(
                self::map_user_data_keys( $event_as_array['user_data'] )
            );
            $event->setUserData( $user_data );
        }
        if ( isset( $event_as_array['custom_data'] ) ) {
            $custom_data = new CustomData( $event_as_array['custom_data'] );
            if ( isset( $event_as_array['custom_data']['contents'] ) ) {
                $contents = array();
                foreach (
                    $event_as_array['custom_data']['contents']
                    as $contents_as_array
                ) {
                    if ( isset( $contents_as_array['id'] ) ) {
                        $contents_as_array['product_id'] =
                            $contents_as_array['id'];
                    }
                    $contents[] = new Content( $contents_as_array );
                }
                $custom_data->setContents( $contents );
            }
            if ( isset(
                $event_as_array['custom_data']['fb_integration_tracking']
            ) ) {
                $custom_data->addCustomProperty(
                    'fb_integration_tracking',
                    $event_as_array['custom_data']['fb_integration_tracking']
                );
            }
            $event->setCustomData( $custom_data );
        }
        return $event;
    }

    /**
     * Returns a new base Event for the given name, populated with the current
     * request's user agent, IP address, fbp/fbc, the current time and a unique
     * event ID.
     *
     * @param string  $event_name      The name of the event to create.
     * @param boolean $prefer_referrer Whether to prefer the referrer URL over
     *                                 the current request URL as event source.
     *
     * @return Event
     */
    private static function new_event(
        $event_name,
        $prefer_referrer = false
    ) {
        $user_data = ( new UserData() )
                    ->setClientIpAddress( UserPiiResolver::get_ip_address() )
                    ->setClientUserAgent( UserPiiResolver::get_http_user_agent() )
                    ->setFbp( UserPiiResolver::get_fbp() )
                    ->setFbc( UserPiiResolver::get_fbc() );

        $event = ( new Event() )
                ->setEventName( $event_name )
                ->setEventTime( time() )
                ->setEventId( EventIdGenerator::guidv4() )
            ->setEventSourceUrl(
                self::get_request_uri( $prefer_referrer )
            )
                ->setActionSource( 'website' )
                ->setUserData( $user_data )
                ->setCustomData( new CustomData() );

        return $event;
    }

    /**
     * Retrieves the request URI for the current HTTP request.
     *
     * This function constructs the full request URI by considering
     * the protocol, host, and request path. If the
     * $prefer_referrer parameter is true and a referrer
     * URL is present in the HTTP headers, it returns the referrer URL instead.
     *
     * @param boolean $prefer_referrer Whether to prefer the referrer URL over
     *                                 the current request URL.
     *
     * @return string The constructed request URI or the referrer
     * URL if preferred.
     */
    private static function get_request_uri( $prefer_referrer ) {
        if ( $prefer_referrer
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
     * Maps normalized user-data keys to UserData constructor keys.
     *
     * @param array $user_data_normalized Normalized user data.
     * @return array Mapped user data.
     */
    private static function map_user_data_keys( $user_data_normalized ) {
        $norm_key_to_key = array(
            AAMSettingsFields::EMAIL         => 'emails',
            AAMSettingsFields::FIRST_NAME    => 'first_names',
            AAMSettingsFields::LAST_NAME     => 'last_names',
            AAMSettingsFields::GENDER        => 'genders',
            AAMSettingsFields::DATE_OF_BIRTH => 'dates_of_birth',
            AAMSettingsFields::EXTERNAL_ID   => 'external_ids',
            AAMSettingsFields::PHONE         => 'phones',
            AAMSettingsFields::CITY          => 'cities',
            AAMSettingsFields::STATE         => 'states',
            AAMSettingsFields::ZIP_CODE      => 'zip_codes',
            AAMSettingsFields::COUNTRY       => 'country_codes',
        );
        $user_data       = array();
        foreach ( $user_data_normalized as $norm_key => $field ) {
            if ( isset( $norm_key_to_key[ $norm_key ] ) ) {
                $user_data[ $norm_key_to_key[ $norm_key ] ] = $field;
            } else {
                $user_data[ $norm_key ] = $field;
            }
        }
        return $user_data;
    }
}
