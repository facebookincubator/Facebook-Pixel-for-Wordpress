<?php
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

/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Core;

defined('ABSPATH') or die('Direct access not allowed');

class FacebookPluginConfig {
  const PLUGIN_VERSION = '1.7.25';
  const SOURCE = 'wordpress';
  const TEXT_DOMAIN = 'official-facebook-pixel';

  const ADMIN_CAPABILITY = 'manage_options';
  const ADMIN_DISMISS_PIXEL_ID_NOTICE = 'dismiss_pixel_id_notice';
  const ADMIN_IGNORE_PIXEL_ID_NOTICE = 'ignore_pixel_id_notice';
  const ADMIN_MENU_SLUG = 'facebook_pixel_options';
  const ADMIN_MENU_TITLE = 'Facebook Pixel';
  const ADMIN_OPTION_GROUP = 'facebook_option_group';
  const ADMIN_PAGE_TITLE = 'Facebook Pixel Settings';
  const ADMIN_PRIVACY_URL = 'https://developers.facebook.com/docs/privacy/';
  const ADMIN_SECTION_ID = 'facebook_settings_section';

  const DEFAULT_PIXEL_ID = null;
  const PIXEL_ID_KEY = 'pixel_id';
  const SETTINGS_KEY = 'facebook_config';
  const USE_PII_KEY = 'use_pii';
  const USE_ADVANCED_MATCHING_DEFAULT = '1';

  const IS_PIXEL_RENDERED = 'is_pixel_rendered';
  const IS_NOSCRIPT_RENDERED = 'is_noscript_rendered';

  // integration config: INTEGRATION_KEY => PLUGIN_CLASS
  public static function integrationConfig() {
    return array(
      'CALDERA_FORM' => 'FacebookWordpressCalderaForm',
      'CONTACT_FORM_7' => 'FacebookWordpressContactForm7',
      'EASY_DIGITAL_DOWNLOAD' => 'FacebookWordpressEasyDigitalDownloads',
      'FORMIDABLE_FORM' => 'FacebookWordpressFormidableForm',
      'GRAVITY_FORMS' => 'FacebookWordpressGravityForms',
      'MAILCHIMP_FOR_WP' => 'FacebookWordpressMailchimpForWp',
      'NINJA_FORMS' => 'FacebookWordpressNinjaForms',
      'WPFORMS' => 'FacebookWordpressWPForms',
      'WP_E_COMMERCE' => 'FacebookWordpressWPECommerce',
    );
  }
}
