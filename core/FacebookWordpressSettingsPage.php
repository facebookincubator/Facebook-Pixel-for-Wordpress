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

class FacebookWordpressSettingsPage {
  private $optionsPage = '';

  public function __construct($plugin_name) {
    add_action('admin_menu', array($this, 'addMenu'));
    add_action('admin_init', array($this, 'registerSettingsPage'));
    add_action('admin_init', array($this, 'dismissNotices'));
    add_action('admin_enqueue_scripts', array($this, 'registerPluginStyles'));
    add_action('current_screen', array($this, 'registerNotices'));
    add_filter(
      'plugin_action_links_'.$plugin_name,
      array($this, 'addSettingsLink'));
  }

  public function addMenu() {
    $this->optionsPage = add_options_page(
      FacebookPluginConfig::ADMIN_PAGE_TITLE,
      FacebookPluginConfig::ADMIN_MENU_TITLE,
      FacebookPluginConfig::ADMIN_CAPABILITY,
      FacebookPluginConfig::ADMIN_MENU_SLUG,
      array($this, 'createMenuPage'));
  }

  public function createMenuPage() {
    if (!current_user_can(FacebookPluginConfig::ADMIN_CAPABILITY)) {
      wp_die(__(
        'You do not have sufficient permissions to access this page',
        FacebookPluginConfig::TEXT_DOMAIN));
    }

    printf(
      '
<div class="wrap">
  <h2>%s</h2>
  <form action="options.php" method="POST">
      ',
      FacebookPluginConfig::ADMIN_PAGE_TITLE);
    settings_fields(FacebookPluginConfig::ADMIN_OPTION_GROUP);
    do_settings_sections(FacebookPluginConfig::ADMIN_MENU_SLUG);
    submit_button();
    printf(
      '
  </form>
</div>
      ');
  }

  public function registerSettingsPage() {
    register_setting(
      FacebookPluginConfig::ADMIN_OPTION_GROUP,
      FacebookPluginConfig::SETTINGS_KEY,
      array($this, 'sanitizeInput'));
    add_settings_section(
      FacebookPluginConfig::ADMIN_SECTION_ID,
      null,
      array($this, 'sectionSubTitle'),
      FacebookPluginConfig::ADMIN_MENU_SLUG);
    add_settings_field(
      FacebookPluginConfig::PIXEL_ID_KEY,
      'Pixel ID',
      array($this, 'pixelIdFormField'),
      FacebookPluginConfig::ADMIN_MENU_SLUG,
      FacebookPluginConfig::ADMIN_SECTION_ID);
    add_settings_field(
      FacebookPluginConfig::USE_PII_KEY,
      'Use Advanced Matching on pixel?',
      array($this, 'usePiiFormField'),
      FacebookPluginConfig::ADMIN_MENU_SLUG,
      FacebookPluginConfig::ADMIN_SECTION_ID);
  }

  public function sanitizeInput($input) {
    $input[FacebookPluginConfig::USE_PII_KEY] =
      !empty($input[FacebookPluginConfig::USE_PII_KEY])
        ? '1'
        : '0';
    $input[FacebookPluginConfig::PIXEL_ID_KEY] =
      !empty($input[FacebookPluginConfig::PIXEL_ID_KEY])
        ? FacebookPluginUtils::isPositiveInteger($input[FacebookPluginConfig::PIXEL_ID_KEY])
          ? $input[FacebookPluginConfig::PIXEL_ID_KEY]
          : ''
        : FacebookPixel::getPixelId();
    return $input;
  }

  public function sectionSubTitle() {
    printf(
      esc_html__(
        'Please note that we are now also supporting lower funnel pixel events for Contact Form 7, Easy Digital Downloads, Ninja Forms and WP Forms',
        FacebookPluginConfig::TEXT_DOMAIN));
  }

  public function pixelIdFormField() {
    $description = esc_html__(
      'The unique identifier for your Facebook pixel.',
      FacebookPluginConfig::TEXT_DOMAIN);

    $pixel_id = FacebookWordpressOptions::getPixelId();
    printf(
      '
<input name="%s" id="%s" value="%s" />
<p class="description">%s</p>
      ',
      FacebookPluginConfig::SETTINGS_KEY . '[' . FacebookPluginConfig::PIXEL_ID_KEY . ']',
      FacebookPluginConfig::PIXEL_ID_KEY,
      isset($pixel_id)
        ? esc_attr($pixel_id)
        : '',
      $description);
  }

  public function usePiiFormField() {
    $link = sprintf(
      wp_kses(
        __(
          'For businesses that operate in the European Union, you may need to take additional action. Read the <a href="%s" target="_blank">Cookie Consent Guide for Sites and Apps</a> for suggestions on complying with EU privacy requirements.',
          FacebookPluginConfig::TEXT_DOMAIN),
        array('a' => array('href' => array(), 'target' => array()))),
      esc_url(FacebookPluginConfig::ADMIN_PRIVACY_URL));
    printf(
      '
<label for="%s">
  <input
    type="checkbox"
    name="%s"
    id="%s"
    value="1"
      ',
      FacebookPluginConfig::USE_PII_KEY,
      FacebookPluginConfig::SETTINGS_KEY . '[' . FacebookPluginConfig::USE_PII_KEY . ']',
      FacebookPluginConfig::USE_PII_KEY);
    checked(1, FacebookWordpressOptions::getUsePii());
    printf(
      '
  />
  %s
</label>
<p class="description">%s</p>
      ',
      esc_html__(
        'Enabling Advanced Matching improves audience building.',
        FacebookPluginConfig::TEXT_DOMAIN),
      $link);
  }

  public function registerNotices() {
    // Update class field
    $pixel_id = FacebookWordpressOptions::getPixelId();
    $current_screen_id = get_current_screen()->id;
    if (
      !FacebookPluginUtils::isPositiveInteger($pixel_id)
      && current_user_can(FacebookPluginConfig::ADMIN_CAPABILITY)
      && in_array(
        $current_screen_id,
        array('dashboard', 'plugins', $this->optionsPage),
        true)
      && !get_user_meta(
        get_current_user_id(),
        FacebookPluginConfig::ADMIN_IGNORE_PIXEL_ID_NOTICE,
        true)
    ) {
      add_action('admin_notices', array($this, 'pixelIdNotSetNotice'));
    }
  }

  public function pixelIdNotSetNotice() {
    $url = admin_url('options-general.php?page='.FacebookPluginConfig::ADMIN_MENU_SLUG);
    $link = sprintf(
      wp_kses(
        __(
          'The Facebook Pixel plugin requires a Pixel ID. Click <a href="%s">here</a> to configure the plugin.',
          FacebookPluginConfig::TEXT_DOMAIN),
        array('a' => array('href' => array()))),
      esc_url($url));
    printf(
      '
<div class="notice notice-warning is-dismissible hide-last-button">
  <p>%s</p>
  <button
    type="button"
    class="notice-dismiss"
    onClick="location.href=\'%s\'">
    <span class="screen-reader-text">%s</span>
  </button>
</div>
      ',
      $link,
      esc_url(add_query_arg(FacebookPluginConfig::ADMIN_DISMISS_PIXEL_ID_NOTICE, '')),
      esc_html__(
        'Dismiss this notice.',
        FacebookPluginConfig::TEXT_DOMAIN));
  }

  public function dismissNotices() {
    $user_id = get_current_user_id();
    if (isset($_GET[FacebookPluginConfig::ADMIN_DISMISS_PIXEL_ID_NOTICE])) {
      update_user_meta($user_id, FacebookPluginConfig::ADMIN_IGNORE_PIXEL_ID_NOTICE, true);
    }
  }

  public function registerPluginStyles() {
    wp_register_style(
      FacebookPluginConfig::TEXT_DOMAIN,
      plugins_url('../css/admin.css', __FILE__));
    wp_enqueue_style(FacebookPluginConfig::TEXT_DOMAIN);
  }

  public function addSettingsLink($links) {
    $settings = array(
      'settings' => sprintf(
        '<a href="%s">%s</a>',
        admin_url('options-general.php?page='.FacebookPluginConfig::ADMIN_MENU_SLUG),
        'Settings')
    );
    return array_merge($settings, $links);
  }
}
