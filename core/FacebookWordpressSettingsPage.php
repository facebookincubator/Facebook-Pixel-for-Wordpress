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

  // Adding option to save Capig Integration settings in wp_options
  \add_option(FacebookPluginConfig::CAPI_INTEGRATION_STATUS,
    FacebookPluginConfig::CAPI_INTEGRATION_STATUS_DEFAULT);
  \add_option(FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER,
    FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER_DEFAULT);

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
    $fbe_extras = json_encode(array(
      "business_config" => array(
         "business" =>  array(
           "name" => "Solutions_Engineering_Team"
         ),
      ),
       "setup" => array(
         "external_business_id" =>
          FacebookWordpressOptions::getExternalBusinessId(),
         "timezone" => 'America/Los_Angeles',
         "currency" => "USD",
         "business_vertical" => "ECOMMERCE",
         "channel" => "DEFAULT"
       ),
       "repeat" => false,
     ));
    ?>
<div>
  <div id="fbe-iframe">
  </div>
  <div id="fb-adv-conf" class="fb-adv-conf" style="display: none;">
    <div class="fb-adv-conf-title">Meta Advanced Configuration</div>
    <div>
      <input type="checkbox" id="capi-cb" name="capi-cb">
      <label class="fb-capi-title" for="capi-cb">
        Send website events to Meta using Conversions API
      </label>
      <span id="fb-capi-se" class="fb-capi-se"></span>
      <br/>
      <div class="fb-capi-desc">
        Enrich website event data using Openbridge javascript.
      </div>
    </div>
    <div id="fb-capi-ef" style="display: none;">
      <input type="checkbox" id="capi-ef" name="capi-ef">
      <label class="fb-capi-title" for="capi-ef">Filter PageView Event</label>
      <span id="fb-capi-ef-se" class="fb-capi-se"></span>
      <br/>
      <div class="fb-capi-desc">
          Enable checkbox to filter PageView events from sending.
      </div>
    </div>
    <div id="fb-capi-ch">
      <input type="checkbox" id="capi-ch" name="capi-ch">
      <label class="fb-capi-title" for="capi-ch">Enable Events Enrichment</label>
      <span id="fb-capi-ef-se" class="fb-capi-se"></span>
      <br/>
      <div class="fb-capi-desc">
          When turned on, PII will be cached for non logged in users.
      </div>
    </div>
  </div>

  <script async defer src="https://connect.facebook.net/en_US/sdk.js"></script>
  <div id="meta-ads-plugin">
  <div id="ad-creation-plugin">
  <h3 class="mt-5">Ads Creation</h3>
      <div
        class="my-3 p-3 bg-white rounded shadow-sm"
        style="background-color: white">
        <div id="ad-creation-plugin-iframe" class="fb-lwi-ads-creation"
        data-lazy=true
        data-hide-manage-button=true
        data-fbe-extras='<?php echo $fbe_extras; ?>'
        data-fbe-scopes=
          'manage_business_extension,business_management,ads_management'
        data-fbe-redirect-uri='https://business.facebook.com/fbe-iframe-handler' ></div>
      </div>
  </div>
  <div id="ad-insights-plugin">
      <h3 class="mt-5">Ads Insights</h3>
      <div
        class="my-3 p-3 bg-white d-block rounded shadow-sm"
        style="background-color: white">
        <div id="ad-insights-plugin-iframe" class="fb-lwi-ads-insights"
        data-lazy=true
        data-fbe-extras='<?php echo $fbe_extras; ?>'
        data-fbe-scopes=
          'manage_business_extension,business_management,ads_management'
        data-fbe-redirect-uri='https://business.facebook.com/fbe-iframe-handler' ></div>
      </div>
  </div>
  </div>
</div>

<script>
     window.fbAsyncInit = function() {
      FB.init({
        appId            : '221646389321681',
        autoLogAppEvents : true,
        xfbml            : true,
        version          : 'v13.0'
      });
    };
    window.facebookBusinessExtensionConfig = {
      pixelId: '<?php echo esc_html(FacebookWordpressOptions::getPixelId()) ?>'
      ,popupOrigin: "https://business.facebook.com"
      ,setSaveSettingsRoute:
        '<?php echo $this->getFbeSaveSettingsAjaxRoute() ?>'
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
      ,channel: 'DEFAULT'
    };
    console.log(JSON.stringify(window.facebookBusinessExtensionConfig));

    var pixelString =
      '<?php echo esc_html(FacebookWordpressOptions::getPixelId()) ?>';

    if (!pixelString.trim()) {
      jQuery('#fb-adv-conf').hide();
    } else {
      // Set advanced configuration top relative to fbe iframe
      setFbAdvConfTop();
      jQuery('#fb-adv-conf').show();
      var enableCapiCheckbox = document.getElementById("capi-cb");
      var enablePiiCachingCheckbox = document.getElementById("capi-ch");
      var currentCapiIntegrationStatus =
      '<?php echo FacebookWordpressOptions::getCapiIntegrationStatus() ?>';
      updateCapiIntegrationCheckbox(currentCapiIntegrationStatus);

      var piiCachingStatus =
      '<?php echo FacebookWordpressOptions::getCapiPiiCachingStatus() ?>';
      console.log("getCapiPiiCachingStatus returned: "+piiCachingStatus);
      updateCapiPiiCachingCheckbox(piiCachingStatus);

      enableCapiCheckbox.addEventListener('change', function() {
        if (this.checked) {
          saveCapiIntegrationStatus('1');
        } else {
          saveCapiIntegrationStatus('0');
        }
      });

      enablePiiCachingCheckbox.addEventListener('change', function() {
        if (this.checked) {
          console.log("Enabled caching");
          saveCapiPiiCachingStatus('1');
        } else {
          console.log("Disabled caching");
          saveCapiPiiCachingStatus('0');
        }
      });

      function setFbAdvConfTop() {
        var fbeIframeTop = 0;
        // Add try catch to handle any error and avoid breaking js
        try {
          fbeIframeTop = jQuery('#fbe-iframe')[0].getBoundingClientRect().top;
        } catch (e){}

        var fbAdvConfTop = <?php
          echo FacebookPluginConfig::CAPI_INTEGRATION_DIV_TOP
          ?> + fbeIframeTop;
        jQuery('#fb-adv-conf').css({'top' : fbAdvConfTop + 'px'});
      }

      function updateCapiIntegrationCheckbox(val) {
        if (val === '1') {
          enableCapiCheckbox.checked = true;
          jQuery('#fb-capi-ef').show();
        } else {
          enableCapiCheckbox.checked = false;
          jQuery('#fb-capi-ef').hide();
        }
      }

      function updateCapiPiiCachingCheckbox(val) {
        if (val === '1') {
          enablePiiCachingCheckbox.checked = true;
        } else {
          enablePiiCachingCheckbox.checked = false;
        }
      }

      function saveCapiIntegrationStatus(new_val) {
        jQuery.ajax({
          type : "post",
          dataType : "json",
          url : '<?php echo $this->getCapiIntegrationStatusSaveUrl() ?>',
          data : {action:
            '<?php
            echo FacebookPluginConfig::SAVE_CAPI_INTEGRATION_STATUS_ACTION_NAME
            ?>',
            val : new_val},
            success: function(response) {
              // This is needed to refresh Events Filter checkbox
              updateCapiIntegrationCheckbox(new_val);
        }}).fail(function (jqXHR, textStatus, error) {
          jQuery('#fb-capi-se').text('<?php
          echo FacebookPluginConfig::CAPI_INTEGRATION_STATUS_UPDATE_ERROR
          ?>');
          jQuery("#fb-capi-se").show().delay(3000).fadeOut();
          updateCapiIntegrationCheckbox((new_val === '1') ? '0' : '1');
        });
      }

      function saveCapiPiiCachingStatus(new_val) {
        jQuery.ajax({
          type : "post",
          dataType : "json",
          url : '<?php echo $this->getCapiPiiCachingStatusSaveUrl() ?>',
          data : {action:
            '<?php
            echo FacebookPluginConfig::SAVE_CAPI_PII_CACHING_STATUS_ACTION_NAME
            ?>',
            val : new_val},
            success: function(response) {
              updateCapiPiiCachingCheckbox(new_val);
        }}).fail(function (jqXHR, textStatus, error) {
          jQuery('#fb-capi-se').text('<?php
          echo FacebookPluginConfig::CAPI_PII_CACHING_STATUS_UPDATE_ERROR
          ?>');
          jQuery("#fb-capi-se").show().delay(3000).fadeOut();
          updateCapiPiiCachingCheckbox((new_val === '1') ? '0' : '1');
        });
      }

      var enablePageViewFilterCheckBox = document.getElementById("capi-ef");
      var capiIntegrationPageViewFiltered =
      ('<?php echo
      json_encode(FacebookWordpressOptions::getCapiIntegrationPageViewFiltered()
      )?>' === 'true') ? '1' : '0';
      updateCapiIntegrationEventsFilter(capiIntegrationPageViewFiltered);
      enablePageViewFilterCheckBox.addEventListener('change', function() {
        saveCapiIntegrationEventsFilter(this.checked ? '1' : '0');
      });

      function updateCapiIntegrationEventsFilter(val) {
        enablePageViewFilterCheckBox.checked = (val === '1') ? true : false;
      }

      function saveCapiIntegrationEventsFilter(new_val) {
        jQuery.ajax({
          type : "post",
          dataType : "json",
          url : '<?php echo $this->getCapiIntegrationEventsFilterSaveUrl() ?>',
          data : {action:
          '<?php
           echo
           FacebookPluginConfig::SAVE_CAPI_INTEGRATION_EVENTS_FILTER_ACTION_NAME
          ?>',
          val : new_val},
          success: function(response) {
        }}).fail(function (jqXHR, textStatus, error) {
          jQuery('#fb-capi-ef-se').text('<?php
          echo FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER_UPDATE_ERROR
          ?>');
          jQuery("#fb-capi-ef-se").show().delay(3000).fadeOut();
          updateCapiIntegrationEventsFilter((new_val === '1') ? '0' : '1');
        });
      }
    }
    var currentFBEInstalledStatus =
      <?php echo FacebookWordpressOptions::getIsFbeInstalled() ?>;
    jQuery('#ad-creation-plugin-iframe')
      .attr('data-fbe-extras', getFBEExtras());
    jQuery('#ad-insights-plugin-iframe')
      .attr('data-fbe-extras', getFBEExtras());
    updateAdInsightsPlugin(currentFBEInstalledStatus);

    function getFBEExtras() {
      $fbeConfig = window.facebookBusinessExtensionConfig;
      return JSON.stringify({
      business_config: {
         business:  {
           name: $fbeConfig.businessName
         },
      },
       setup: {
         external_business_id: $fbeConfig.externalBusinessId,
         timezone: $fbeConfig.timeZone,
         currency: $fbeConfig.currency,
         business_vertical: $fbeConfig.businessVertical,
         channel: $fbeConfig.channel
       },
       repeat: false,
      });
    }
    function updateAdInsightsPlugin(isFBEInstalled) {
      if (isFBEInstalled)
      {
        jQuery('#meta-ads-plugin').show();
      } else {
        jQuery('#meta-ads-plugin').hide();
      }
    }
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

  public function getCapiIntegrationStatusSaveUrl() {
    $nonce_value = wp_create_nonce(
      FacebookPluginConfig::SAVE_CAPI_INTEGRATION_STATUS_ACTION_NAME
    );
    $simple_url = admin_url('admin-ajax.php');
    $args = array(
      'action' =>
        FacebookPluginConfig::SAVE_CAPI_INTEGRATION_STATUS_ACTION_NAME,
      '_wpnonce' => $nonce_value
    );
    return add_query_arg($args, $simple_url);
  }

  public function getCapiIntegrationEventsFilterSaveUrl() {
    $nonce_value = wp_create_nonce(
      FacebookPluginConfig::SAVE_CAPI_INTEGRATION_EVENTS_FILTER_ACTION_NAME
    );
    $simple_url = admin_url('admin-ajax.php');
    $args = array(
      'action' =>
        FacebookPluginConfig::SAVE_CAPI_INTEGRATION_EVENTS_FILTER_ACTION_NAME,
      '_wpnonce' => $nonce_value
    );
    return add_query_arg($args, $simple_url);
  }

  public function getCapiPiiCachingStatusSaveUrl() {
    $nonce_value = wp_create_nonce(
      FacebookPluginConfig::SAVE_CAPI_PII_CACHING_STATUS_ACTION_NAME
    );
    $simple_url = admin_url('admin-ajax.php');
    $args = array(
      'action' =>
        FacebookPluginConfig::SAVE_CAPI_PII_CACHING_STATUS_ACTION_NAME,
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
        $message = sprintf('Easily manage your connection to Meta with %s.',
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
