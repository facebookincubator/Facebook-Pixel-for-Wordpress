<?php
/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Core;

defined('ABSPATH') or die('Direct access not allowed');

class FacebookPluginConfig {
  const PLUGIN_VERSION = '1.7.13';
  const SOURCE = 'wordpress';
  const TEXT_DOMAIN = 'official-facebook-pixel';

  const ADMIN_CAPABILITY = 'manage_options';
  const ADMIN_DISMISS_PIXEL_ID_NOTICE = 'dismiss_pixel_id_notice';
  const ADMIN_IGNORE_PIXEL_ID_NOTICE = 'ignore_pixel_id_notice';
  const ADMIN_MENU_SLUG = 'facebook_pixel_settings';
  const ADMIN_MENU_TITLE = 'Facebook Pixel';
  const ADMIN_OPTION_GROUP = 'facebook_option_group';
  const ADMIN_PAGE_TITLE = 'Facebook Pixel Settings';
  const ADMIN_PRIVACY_URL = 'https://developers.facebook.com/docs/privacy/';
  const ADMIN_SECTION_ID = 'facebook_settings_section';

  const DEFAULT_PIXEL_ID = null;
  const PIXEL_ID_KEY = 'pixel_id';
  const SETTINGS_KEY = 'facebook_config';
  const USE_PII_KEY = 'use_pii';

  const IS_PIXEL_RENDERED = 'is_pixel_rendered';
  const IS_NOSCRIPT_RENDERED = 'is_noscript_rendered';

  // integration config: INTEGRATION_KEY => PLUGIN_CLASS
  const INTEGRATION_CONFIG = array(
    'CONTACT_FORM_7' => 'FacebookWordpressContactForm7',
    'EASY_DIGITAL_DOWNLOAD' => 'FacebookWordpressEasyDigitalDownloads',
    'NINJA_FORMS' => 'FacebookWordpressNinjaForms',
    'WPFORMS' => 'FacebookWordpressWPForms',
  );
}
