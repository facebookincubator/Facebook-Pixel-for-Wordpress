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

use FacebookAds\ApiConfig;

defined('ABSPATH') or die('Direct access not allowed');

class FacebookWordpressSettingsPage {
  private $optionsPage = '';

  public function __construct($plugin_name) {
    add_filter(
      'plugin_action_links_'.$plugin_name,
      array($this, 'addSettingsLink'));
    add_action('admin_menu', array($this, 'addMenuFbe'));
    add_action('admin_init', array($this, 'dismissNotices'));
    add_action('admin_enqueue_scripts', array($this, 'registerPluginScripts'));
    add_action('current_screen', array($this, 'registerNotices'));
  }

  public function registerPluginScripts(){
    wp_register_script('fbe_allinone_script',
      plugins_url('../js/fbe_allinone.js', __FILE__));
    wp_register_style(
      FacebookPluginConfig::TEXT_DOMAIN,
      plugins_url('../css/admin.css', __FILE__));
    wp_enqueue_style(FacebookPluginConfig::TEXT_DOMAIN);
  }

  public function addMenuFbe() {
    $this->optionsPage = add_options_page(
      FacebookPluginConfig::ADMIN_PAGE_TITLE,
      FacebookPluginConfig::ADMIN_MENU_TITLE,
      FacebookPluginConfig::ADMIN_CAPABILITY,
      FacebookPluginConfig::ADMIN_MENU_SLUG,
      array($this, 'addFbeBox'));
  }

  public function addFbeBox(){
    if (!current_user_can(FacebookPluginConfig::ADMIN_CAPABILITY)) {
      wp_die(__(
        'You do not have sufficient permissions to access this page',
        FacebookPluginConfig::TEXT_DOMAIN));
    }
    $pixel_id_message = $this->getPreviousPixelIdMessage();
    if($pixel_id_message){
      echo $pixel_id_message;
    }
    echo $this->getFbeBrowserSettings();
    wp_enqueue_script('fbe_allinone_script');
  }

  private function getPreviousPixelIdMessage(){
    if(FacebookWordpressOptions::getIsFbeInstalled()){
      return null;
    }
    $pixel_id = FacebookWordPressOptions::getPixelId();
    if(empty($pixel_id)){
      return null;
    }
    $message =
      sprintf('<p>Reuse the pixel id from your previous setup: '.
        '<strong>%s</strong></p>',
        $pixel_id
    );
    return $message;
  }

  private function getFbeBrowserSettings(){
    ob_start();
    ?>
<div>
  <div id="fbe-iframe">
  </div>
</div>

<script>
    window.facebookBusinessExtensionConfig = {
      pixelId: '<?php echo esc_html(FacebookWordpressOptions::getPixelId()) ?>'
      ,popupOrigin: "https://business.facebook.com"
      ,setSaveSettingsRoute: '<?php echo $this->getFbeSaveSettingsAjaxRoute() ?>'
      ,externalBusinessId: '<?php echo esc_html(
        FacebookWordpressOptions::getExternalBusinessId()
      )?>'
      ,fbeLoginUrl: "https://business.facebook.com/fbe-iframe-get-started/?"
      ,deleteConfigKeys: '<?php echo $this->getDeleteFbeSettingsAjaxRoute() ?>'
      ,appId: '221646389321681'
      ,timeZone: 'America/Los_Angeles'
      ,installed: '<?php echo FacebookWordpressOptions::getIsFbeInstalled() ?>'
      ,systemUserName: '<?php echo esc_html(
        FacebookWordpressOptions::getExternalBusinessId()
        )  ?>' + '_system_user'
      ,businessVertical: 'ECOMMERCE'
      ,version: 'v8.0'
      ,currency: 'USD'
      ,businessName: 'Solutions Engineering Team'
      ,debug: true
      ,channel: 'CONVERSIONS_API'
    };
    console.log(JSON.stringify(window.facebookBusinessExtensionConfig));
</script>
    <?php
    $initialScript = ob_get_clean();
    return $initialScript;
  }

  public function getFbeSaveSettingsAjaxRoute(){
    $nonce_value = wp_create_nonce(
      FacebookPluginConfig::SAVE_FBE_SETTINGS_ACTION_NAME
    );
    $simple_url = admin_url('admin-ajax.php');
    $args = array(
      'action' => FacebookPluginConfig::SAVE_FBE_SETTINGS_ACTION_NAME,
      '_wpnonce' => $nonce_value
    );
    return add_query_arg($args, $simple_url);
  }

  public function getDeleteFbeSettingsAjaxRoute(){
    $nonce_value = wp_create_nonce(
      FacebookPluginConfig::DELETE_FBE_SETTINGS_ACTION_NAME
    );
    $simple_url = admin_url('admin-ajax.php');
    $args = array(
      'action' => FacebookPluginConfig::DELETE_FBE_SETTINGS_ACTION_NAME,
      '_wpnonce' => $nonce_value
    );
    return add_query_arg($args, $simple_url);
  }

  public function addSettingsLink($links) {
    $settings = array(
      'settings' => sprintf(
        '<a href="%s">%s</a>',
        admin_url('options-general.php?page=' .
          FacebookPluginConfig::ADMIN_MENU_SLUG),
        'Settings')
    );
    return array_merge($settings, $links);
  }

  public function registerNotices() {
    $is_fbe_installed = FacebookWordpressOptions::getIsFbeInstalled();
    $current_screen_id = get_current_screen()->id;

    if (current_user_can(FacebookPluginConfig::ADMIN_CAPABILITY) &&
        in_array($current_screen_id, array('dashboard', 'plugins'), true)){
      if( $is_fbe_installed == '0' && !get_user_meta(
        get_current_user_id(),
        FacebookPluginConfig::ADMIN_IGNORE_FBE_NOT_INSTALLED_NOTICE,
        true)){
        add_action('admin_notices', array($this, 'fbeNotInstalledNotice'));
      }
      if( $is_fbe_installed == '1' && !get_user_meta(
        get_current_user_id(),
        FacebookPluginConfig::ADMIN_IGNORE_PLUGIN_REVIEW_NOTICE,
        true)){
        add_action('admin_notices', array($this, 'pluginReviewNotice'));
      }
    }
  }

  public function getCustomizedFbeNotInstalledNotice(){
    $valid_pixel_id = !empty(FacebookWordPressOptions:: getPixelId());
    $valid_access_token = !empty(FacebookWordPressOptions::getAccessToken());
    $message = '';
    $plugin_name_tag = sprintf('<strong>%s</strong>',
      FacebookPluginConfig::PLUGIN_NAME);
    if($valid_pixel_id){
      if($valid_access_token){
        $message = sprintf('Easily manage your connection to Facebook with %s.',
          $plugin_name_tag);
      }
      else{
        $message = sprintf('%s gives you access to the Conversions API.',
          $plugin_name_tag);
      }
    }
    else{
      $message = sprintf('%s is almost ready.', $plugin_name_tag);
    }
    return $message.' To complete your configuration, '.
      '<a href="%s">follow the setup steps.</a>';
  }

  public function setNotice($notice, $dismiss_config, $notice_type) {
    $url = admin_url('options-general.php?page=' .
        FacebookPluginConfig::ADMIN_MENU_SLUG);

    $link = sprintf(
      $notice,
      esc_url($url));
    printf(
      '
<div class="notice notice-%s is-dismissible">
  <p>%s</p>
  <button
    type="button"
    class="notice-dismiss"
    onClick="location.href=\'%s\'">
    <span class="screen-reader-text">%s</span>
  </button>
</div>
      ',
      $notice_type,
      $link,
      esc_url(add_query_arg($dismiss_config, '')),
      esc_html__(
        'Dismiss this notice.',
        FacebookPluginConfig::TEXT_DOMAIN));
  }

  public function pluginReviewNotice(){
    $message = sprintf('Let us know what you think about <strong>%s</strong>. '.
      'Leave a review on <a href="%s" target="_blank">this page</a>.',
      FacebookPluginConfig::PLUGIN_NAME,
      FacebookPluginConfig::PLUGIN_REVIEW_PAGE
    );
    $this->setNotice(
      __(
        $message,
        FacebookPluginConfig::TEXT_DOMAIN),
      FacebookPluginConfig::ADMIN_DISMISS_PLUGIN_REVIEW_NOTICE,
      'info'
    );
  }

  public function fbeNotInstalledNotice() {
    $message = $this->getCustomizedFbeNotInstalledNotice();
    $this->setNotice(
      __(
        $message,
        FacebookPluginConfig::TEXT_DOMAIN),
      FacebookPluginConfig::ADMIN_DISMISS_FBE_NOT_INSTALLED_NOTICE,
      'warning'
    );
  }

  public function dismissNotices() {
    $user_id = get_current_user_id();
    if (isset(
      $_GET[FacebookPluginConfig::ADMIN_DISMISS_FBE_NOT_INSTALLED_NOTICE]
    )){
      update_user_meta($user_id,
        FacebookPluginConfig::ADMIN_IGNORE_FBE_NOT_INSTALLED_NOTICE,
        true);
    }
    if (isset(
      $_GET[FacebookPluginConfig::ADMIN_DISMISS_PLUGIN_REVIEW_NOTICE]
    )){
      update_user_meta($user_id,
        FacebookPluginConfig::ADMIN_IGNORE_PLUGIN_REVIEW_NOTICE,
        true);
    }
  }
}
