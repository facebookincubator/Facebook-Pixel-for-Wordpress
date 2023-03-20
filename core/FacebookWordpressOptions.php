<?php
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

/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Core;

use FacebookAds\Object\ServerSide\AdsPixelSettings;
use FacebookPixelPlugin\Core\FacebookPluginUtils;

defined('ABSPATH') or die('Direct access not allowed');

class FacebookWordpressOptions {
  private static $options = array();
  private static $userInfo = array();
  private static $versionInfo = array();
  private static $aamSettings = null;
  private static $capiIntegrationEnabled = null;
  private static $capiIntegrationEventsFilter = null;
  const AAM_SETTINGS_REFRESH_IN_MINUTES = 20;

  public static function initialize() {
    self::initOptions();
    self::setVersionInfo();
    self::setAAMSettings();
    self::setUserInfo();
    self::setCapiIntegrationStatus();
    self::setCapiIntegrationEventsFilter();
  }

  public static function getOptions() {
    return self::$options;
  }

  public static function setCapiIntegrationStatus() {
    self::$capiIntegrationEnabled =
      \get_option(FacebookPluginConfig::CAPI_INTEGRATION_STATUS);
  }

  public static function getCapiIntegrationStatus() {
    return is_null(self::$capiIntegrationEnabled) ?
    (is_null(FacebookPluginConfig::CAPI_INTEGRATION_STATUS_DEFAULT)
      ? '0' : FacebookPluginConfig::CAPI_INTEGRATION_STATUS_DEFAULT ) :
    self::$capiIntegrationEnabled;
  }

  public static function setCapiIntegrationEventsFilter() {
    self::$capiIntegrationEventsFilter =
      \get_option(FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER);
  }

  public static function getCapiIntegrationEventsFilter() {
    return is_null(self::$capiIntegrationEventsFilter) ?
      FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER_DEFAULT :
      self::$capiIntegrationEventsFilter;
  }

  public static function getCapiIntegrationPageViewFiltered() {
    return FacebookPluginUtils::string_contains(
      self::getCapiIntegrationEventsFilter(), 'PageView');
  }

  public static function getDefaultPixelID() {
    return is_null(FacebookPluginConfig::DEFAULT_PIXEL_ID)
              ? '' : FacebookPluginConfig::DEFAULT_PIXEL_ID;
  }

  public static function getDefaultAccessToken() {
    return is_null(FacebookPluginConfig::DEFAULT_ACCESS_TOKEN)
              ? '' : FacebookPluginConfig::DEFAULT_ACCESS_TOKEN;
  }

  public static function getDefaultExternalBusinessId(){
    return uniqid(
      FacebookPluginConfig::DEFAULT_EXTERNAL_BUSINESS_ID_PREFIX.time().'_'
    );
  }

  public static function getDefaultIsFbeInstalled(){
    return FacebookPluginConfig::DEFAULT_IS_FBE_INSTALLED;
  }

  private static function initOptions() {
    $old_options = \get_option(FacebookPluginConfig::OLD_SETTINGS_KEY);
    $new_options = \get_option(FacebookPluginConfig::SETTINGS_KEY);
    // If the new options are saved in WP database, they are used
    if($new_options){
      self::$options = $new_options;
    }
    // Otherwise, the old options can be used
    else{
      // The pixel id and access token will be exported
      if($old_options){
        self::$options = array(
          FacebookPluginConfig::EXTERNAL_BUSINESS_ID_KEY =>
            self::getDefaultExternalBusinessId(),
          FacebookPluginConfig::IS_FBE_INSTALLED_KEY =>
            self::getDefaultIsFbeInstalled(),
        );
        if(
          array_key_exists(FacebookPluginConfig::OLD_ACCESS_TOKEN_KEY,$old_options)
          && !empty($old_options[FacebookPluginConfig::OLD_ACCESS_TOKEN_KEY])
        ){
          self::$options[FacebookPluginConfig::ACCESS_TOKEN_KEY] =
            $old_options[FacebookPluginConfig::OLD_ACCESS_TOKEN_KEY];
        }
        else{
          self::$options[FacebookPluginConfig::ACCESS_TOKEN_KEY] =
            self::getDefaultAccessToken();
        }
        if(
          array_key_exists(FacebookPluginConfig::OLD_PIXEL_ID_KEY,$old_options)
          && !empty($old_options[FacebookPluginConfig::OLD_PIXEL_ID_KEY])
          && is_numeric($old_options[FacebookPluginConfig::OLD_PIXEL_ID_KEY])
        ){
          self::$options[FacebookPluginConfig::PIXEL_ID_KEY] =
            $old_options[FacebookPluginConfig::OLD_PIXEL_ID_KEY];
        }
        else{
          self::$options[FacebookPluginConfig::PIXEL_ID_KEY] =
            self::getDefaultPixelID();
        }
      }
      // If no options are present, the default values are used
      else{
        self::$options = \get_option(
          FacebookPluginConfig::SETTINGS_KEY,
          array(
            FacebookPluginConfig::PIXEL_ID_KEY => self::getDefaultPixelID(),
            FacebookPluginConfig::ACCESS_TOKEN_KEY =>
              self::getDefaultAccessToken(),
            FacebookPluginConfig::EXTERNAL_BUSINESS_ID_KEY =>
              self::getDefaultExternalBusinessId(),
            FacebookPluginConfig::IS_FBE_INSTALLED_KEY =>
              self::getDefaultIsFbeInstalled()
          )
        );
      }
    }
  }

  public static function getPixelId() {
    if (array_key_exists(FacebookPluginConfig::PIXEL_ID_KEY, self::$options)) {
      return self::$options[FacebookPluginConfig::PIXEL_ID_KEY];
    }

    return self::getDefaultPixelID();
  }

  public static function getExternalBusinessId() {
    if(
      array_key_exists(FacebookPluginConfig::EXTERNAL_BUSINESS_ID_KEY,
        self::$options)
    ){
      return self::$options[FacebookPluginConfig::EXTERNAL_BUSINESS_ID_KEY];
    }

    return self::getDefaultExternalBusinessId();
  }

  public static function getIsFbeInstalled(){
    if(
      array_key_exists(FacebookPluginConfig::IS_FBE_INSTALLED_KEY,
        self::$options)
    ){
      return self::$options[FacebookPluginConfig::IS_FBE_INSTALLED_KEY];
    }

    return self::getDefaultIsFbeInstalled();
  }

  public static function getAccessToken() {
    if (array_key_exists(
      FacebookPluginConfig::ACCESS_TOKEN_KEY, self::$options)) {
      return self::$options[FacebookPluginConfig::ACCESS_TOKEN_KEY];
    }

    return self::getDefaultAccessToken();
  }

  public static function getUserInfo() {
    return self::$userInfo;
  }

  public static function setUserInfo() {
    add_action(
      'init',
      array(
        'FacebookPixelPlugin\\Core\\FacebookWordpressOptions',
        'registerUserInfo'
      ),
      0);
  }

  public static function registerUserInfo() {
    $current_user = wp_get_current_user();
    if (0 === $current_user->ID ) {
      // User not logged in
      self::$userInfo = array();
    } else {
      $user_info = array_filter(
        array(
          // Keys documented in
          // https://developers.facebook.com/docs/facebook-pixel/pixel-with-ads/conversion-tracking#advanced_match
          AAMSettingsFields::EMAIL => $current_user->user_email,
          AAMSettingsFields::FIRST_NAME => $current_user->user_firstname,
          AAMSettingsFields::LAST_NAME => $current_user->user_lastname
        ),
        function ($value) { return $value !== null && $value !== ''; });
      self::$userInfo = AAMFieldsExtractor::getNormalizedUserData($user_info);
    }
  }

  public static function getVersionInfo() {
    return self::$versionInfo;
  }

  public static function setVersionInfo() {
    global $wp_version;

    self::$versionInfo = array(
      'pluginVersion' => FacebookPluginConfig::PLUGIN_VERSION,
      'source' => FacebookPluginConfig::SOURCE,
      'version' => $wp_version
    );
  }

  public static function getAgentString() {
    return sprintf(
      '%s-%s-%s',
      self::$versionInfo['source'],
      self::$versionInfo['version'],
      self::$versionInfo['pluginVersion']);
  }

  public static function getAAMSettings(){
    return self::$aamSettings;
  }

  private static function setFbeBasedAAMSettings(){
    $installed_pixel = self::getPixelId();
    $settings_as_array = get_transient(FacebookPluginConfig::AAM_SETTINGS_KEY);
    // If AAM_SETTINGS_KEY is present in the DB and corresponds to the installed
    // pixel, it is converted into an AdsPixelSettings object
    if( $settings_as_array !== false ){
      $aam_settings = new AdsPixelSettings();
      $aam_settings->setPixelId($settings_as_array['pixelId']);
      $aam_settings->setEnableAutomaticMatching($settings_as_array['enableAutomaticMatching']);
      $aam_settings->setEnabledAutomaticMatchingFields($settings_as_array['enabledAutomaticMatchingFields']);
      if($installed_pixel == $aam_settings->getPixelId()){
        self::$aamSettings = $aam_settings;
      }
    }
    // If the settings are not present
    // they are fetched from Meta domain
    // and cached in WP database if they are not null
    if(!self::$aamSettings){
      $refresh_interval =
        self::AAM_SETTINGS_REFRESH_IN_MINUTES*MINUTE_IN_SECONDS;
      $aam_settings = AdsPixelSettings::buildFromPixelId( $installed_pixel );
      if($aam_settings){
        $settings_as_array = array(
          'pixelId' => $aam_settings->getPixelId(),
          'enableAutomaticMatching' =>
            $aam_settings->getEnableAutomaticMatching(),
          'enabledAutomaticMatchingFields' =>
            $aam_settings->getEnabledAutomaticMatchingFields(),
        );
        set_transient(FacebookPluginConfig::AAM_SETTINGS_KEY,
        $settings_as_array, $refresh_interval);
        self::$aamSettings = $aam_settings;
      }
    }
  }

  private static function setOldAAMSettings(){
    $old_options = \get_option(FacebookPluginConfig::OLD_SETTINGS_KEY);
    if($old_options
      && array_key_exists(FacebookPluginConfig::OLD_USE_PII, $old_options)
      && $old_options[FacebookPluginConfig::OLD_USE_PII]){
        self::$aamSettings = new AdsPixelSettings(
          array(
            'enableAutomaticMatching' => true,
            'enabledAutomaticMatchingFields' =>
              AAMSettingsFields::getAllFields(),
          )
        );
    } else {
      self::$aamSettings = new AdsPixelSettings(
        array(
          'enableAutomaticMatching' => false,
          'enabledAutomaticMatchingFields' => array(),
        )
      );
    }
  }

  private static function setAAMSettings(){
    self::$aamSettings = null;
    if( empty(self::getPixelId()) ){
      return;
    }
    if(self::getIsFbeInstalled()){
      self::setFbeBasedAAMSettings();
    } else {
      self::setOldAAMSettings();
    }
  }
}
