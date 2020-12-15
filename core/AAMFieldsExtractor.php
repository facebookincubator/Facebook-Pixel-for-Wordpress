<?php
/**
 * Copyright (C) 2015-present, Facebook, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */

namespace FacebookPixelPlugin\Core;

use FacebookAds\Object\ServerSide\Normalizer;

final class AAMFieldsExtractor {
  /**
   * Filters the passed user data using the AAM settings of the pixel
   * @param string[] $user_data_array
   * @return string[]
   */
  public static function getNormalizedUserData($user_data_array) {
    $aam_setttings = FacebookWordpressOptions::getAAMSettings();
    if(!$user_data_array || !$aam_setttings ||
      !$aam_setttings->getEnableAutomaticMatching()){
      return array();
    }

    //Removing fields not enabled in AAM settings
    foreach ($user_data_array as $key => $value) {
      if(!in_array($key, $aam_setttings->getEnabledAutomaticMatchingFields())){
        unset($user_data_array[$key]);
      }
    }

    // Normalizing gender and date of birth
    // According to https://developers.facebook.com/docs/facebook-pixel/advanced/advanced-matching
    if(
      array_key_exists(AAMSettingsFields::GENDER, $user_data_array)
      && !empty($user_data_array[AAMSettingsFields::GENDER])
    ){
      $user_data_array[AAMSettingsFields::GENDER] =
        $user_data_array[AAMSettingsFields::GENDER][0];
    }
    if(
      array_key_exists(AAMSettingsFields::DATE_OF_BIRTH, $user_data_array)
    ){
      // strtotime() and date() return false for invalid parameters
      $unix_timestamp =
        strtotime($user_data_array[AAMSettingsFields::DATE_OF_BIRTH]);
      if(!$unix_timestamp){
        unset($user_data_array[AAMSettingsFields::DATE_OF_BIRTH]);
      } else {
        $formatted_date = date("Ymd", $unix_timestamp);
        if(!$formatted_date){
          unset($user_data_array[AAMSettingsFields::DATE_OF_BIRTH]);
        } else {
          $user_data_array[AAMSettingsFields::DATE_OF_BIRTH] = $formatted_date;
        }
      }
    }
    // Given that the format of advanced matching fields is the same in
    // the Pixel and the Conversions API,
    // we can use the business sdk for normalization
    // Compare the documentation:
    // https://developers.facebook.com/docs/marketing-api/conversions-api/parameters/customer-information-parameters
    // https://developers.facebook.com/docs/facebook-pixel/advanced/advanced-matching
    foreach($user_data_array as $field => $data){
      try{
        $normalized_value = Normalizer::normalize($field, $data);
        $user_data_array[$field] = $normalized_value;
      }
      catch(\Exception $e){
        unset($user_data_array[$field]);
      }
    }

    return $user_data_array;
  }
}
