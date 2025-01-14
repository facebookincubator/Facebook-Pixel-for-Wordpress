<?php
/**
 * Facebook Pixel Plugin AAMFieldsExtractor class.
 *
 * This file contains the main logic for AAMFieldsExtractor.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define AAMFieldsExtractor class.
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

use FacebookAds\Object\ServerSide\Normalizer;

/**
 * Class AAMFieldsExtractor
 */
final class AAMFieldsExtractor {
  /**
   * Filters the passed user data using the AAM settings of the pixel
   *
   * @param string[] $user_data_array The user data.
   * @return string[]
   */
  public static function get_normalized_user_data( $user_data_array ) {
    $aam_setttings = FacebookWordpressOptions::get_aam_settings();
    if ( ! $user_data_array || ! $aam_setttings ||
      ! $aam_setttings->getEnableAutomaticMatching() ) {
      return array();
    }

    foreach ( $user_data_array as $key => $value ) {
    if ( ! in_array(
        $key,
        $aam_setttings->getEnabledAutomaticMatchingFields(),
        true
    ) ) {
        unset( $user_data_array[ $key ] );
      }
    }

    if (
        isset( $user_data_array[ AAMSettingsFields::GENDER ] ) &&
        ! empty( $user_data_array[ AAMSettingsFields::GENDER ] )
        ) {
        $user_data_array[ AAMSettingsFields::GENDER ] =
        $user_data_array[ AAMSettingsFields::GENDER ][0];
    }
    if (
        isset( $user_data_array[ AAMSettingsFields::DATE_OF_BIRTH ] )
        ) {

        $date_time      = \DateTime::createFromFormat(
            'Y-m-d',
            $user_data_array[ AAMSettingsFields::DATE_OF_BIRTH ],
            new \DateTimeZone( 'GMT' )
        );
        $unix_timestamp = $date_time ? $date_time->getTimestamp() : false;

        if ( ! $unix_timestamp ) {
            unset( $user_data_array[ AAMSettingsFields::DATE_OF_BIRTH ] );
        } else {
            $formatted_date = gmdate( 'Ymd', $unix_timestamp );
        if ( ! $formatted_date ) {
            unset( $user_data_array[ AAMSettingsFields::DATE_OF_BIRTH ] );
        } else {
            $user_data_array[ AAMSettingsFields::DATE_OF_BIRTH ] =
            $formatted_date;
        }
        }
    }

    foreach ( $user_data_array as $field => $data ) {
        try {
        if ( is_array( $data ) ) {
            $res = array();
            foreach ( $data as $key => $value ) {
                $normalized_value = Normalizer::normalize( $field, $value );
                $res[ $key ]      = $normalized_value;
            }
            $user_data_array[ $field ] = $res;
        } else {
            $normalized_value          = Normalizer::normalize( $field, $data );
            $user_data_array[ $field ] = $normalized_value;
        }
        } catch ( \Exception $e ) {
            unset( $user_data_array[ $field ] );
        }
    }

        return $user_data_array;
    }
}
