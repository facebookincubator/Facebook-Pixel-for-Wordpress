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
    $capi_event = new FacebookCapiEvent;
  }

  public function registerPluginScripts(){
    wp_register_script('fbe_allinone_script',
      plugins_url('../js/fbe_allinone.js', __FILE__));
    wp_register_script('meta_settings_page_script',
      plugins_url('../js/settings_page.js', __FILE__));
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
  <div id="fb-adv-conf" class="fb-adv-conf">
    <div class="fb-adv-conf-title">Meta Advanced Configuration</div>
    <div id="fb-capi-ef">
      <input type="checkbox" id="capi-ef" name="capi-ef">
      <label class="fb-capi-title" for="capi-ef">Filter PageView Event</label>
      <span id="fb-capi-ef-se" class="fb-capi-se"></span>
      <br/>
      <div class="fb-capi-desc">
          Enable checkbox to filter PageView events from sending.
      </div>
      <div id="fb-capi-ch">
        <input type="checkbox" id="capi-ch" name="capi-ch">
        <label class="fb-capi-title" for="capi-ch">
          Enable Events Enrichment
        </label>
        <span id="fb-capi-ef-se" class="fb-capi-se"></span>
        <br/>
        <div class="fb-capi-desc">
          When turned on, PII will be cached for non logged in users.
        </div>
      </div>
    </div>
  </div>

  <div class="events-manager-wrapper <?php echo empty( FacebookWordpressOptions::getPixelId() ) ? 'hidden' : ''; ?>">
	<h3>Conversion API Tests</h3>

	<div class="events-manager-container">
		<div>
			<h3>Plugin Connected to Meta Events Manager</h3>

			<p>Meta Events Manager is a tool that enables you to view and manage your event data. In Events Manager, you can set up, monitor and troubleshoot issues with your integrations, such as the Conversions API and Meta pixel.</p>
			<p class="meta-event-manager">Visit the <a href="https://business.facebook.com/events_manager2/list/pixel/<?php echo FacebookWordpressOptions::getPixelId(); ?>" target="_blank">Meta Events Manager</a> to view the events being tracked.</p>
		</div>

		<div class="pixel-block events-manager-block">
			<label>Your Pixel ID</label>
			<input type="text" id="pixel-id" placeholder="<?php echo FacebookWordpressOptions::getPixelId(); ?>" disabled />
		</div>

		<?php echo '<img class="test-form-img" src = ' . plugin_dir_url( __DIR__ ) . 'assets/event-log-head.png alt="Test form image">'; ?>

		<div class="test-events-block events-manager-block">
			<form class="test-form" action="javascript:void(0);">
				<div class="test-form-field-wrapper">
                    <div class="test-hints">
                        <div class="test-hints__wrapper">
                            <span>&#63;</span>

                            <p>To obtain the Test Event Code, visit the Test Event section in the <a target="_blank" href="https://business.facebook.com/events_manager2/list/pixel/<?php echo FacebookWordpressOptions::getPixelId(); ?>/test_events"><b>Events Manager</b></a>.</p>
                        </div>
                    </div>

					<div class="text-form-inputs">
						<div class="test-event-code-wrapper">
							<label>Test Event Code</label>
							<input type="text" id="event-test-code" placeholder="TEST4039" />
						</div>

						<div>
							<label for="event-type">Event Type</label>

							<select name="event-type" id="test-event-name">
								<option>Purchase</option>
								<option>PageView</option>
								<option>AddToCart</option>
								<option>AddToWishlist</option>
								<option>ViewContent</option>
								<option>Subscribe</option>
								<option>Search</option>
								<option>AddPaymentInfo</option>
								<option>CompleteRegistration</option>
								<option>Contact</option>
								<option>CustomizeProduct</option>
								<option>Donate</option>
								<option>FindLocation</option>
								<option>InitiateCheckout</option>
								<option>Lead</option>
								<option>Schedule</option>
								<option>StartTrial</option>
								<option>SubmitApplication</option>
							</select>
						</div>
					</div>

					<div class="advanced-payload-controls-wrapper">
						<span class="advanced-edit-toggle" onclick="toggleAdvancedPayload();">Advanced | Edit Event Data
                            <svg class="advanced-edit-toggle-arrow" width="12" height="8" viewBox="0 0 14 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2 0L7 5L12 0L14 1L7 8L0 1L2 0Z" fill="#555555"></path>
                            </svg>
						</span>

						<span id="populate-payload-button" onclick="populateAdvancedEvent(event);">Click here to load default payload</span>
					</div>

					<textarea rows="13" id="advanced-payload" placeholder="Enter payload"></textarea>
				</div>

				<button onclick="sendTestEvent(event);">Submit Event</button>
			</form>

			<div class="event-log-block">
				<h4>Event Log</h4>

				<table>
					<thead class="event-log-block__head">
						<tr>
							<td>Code/Message</td>
							<td>Event Type</td>
							<td>Status</td>
						</tr>
					</thead>          
					<tbody></tbody>
				</table>

                <div class="event-hints">
                    <div class="event-hints__wrapper">
                        <span>&#8505;</span>

                        <p class="event-hints__text initial-text">No events logged yet.</p>

                        <span class="event-hints__close-icon hidden">&#x2715;</span>
                    </div>
                </div>
			</div>
		</div>
	</div>
  </div>

  <script async defer src="https://connect.facebook.net/en_US/sdk.js"></script>
  <div id="meta-ads-plugin">
  <div id="ad-creation-plugin">
  <h3 class="mt-5">Ads Creation</h3>
      <div
        class="my-3 p-3 bg-white rounded shadow-sm">
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
        class="my-3 p-3 bg-white d-block rounded shadow-sm">
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

    <?php
    $initialScript = ob_get_clean();
    wp_enqueue_script( 'meta_settings_page_script' );

    wp_add_inline_script(
      'meta_settings_page_script',
			'const meta_wc_params = ' . wp_json_encode(
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
          'send_capi_event_nonce' => wp_create_nonce( 'send_capi_event_nonce' ),
          'pixelId' => FacebookWordPressOptions::getPixelId(),
          'setSaveSettingsRoute'  => $this->getFbeSaveSettingsAjaxRoute(),
          'externalBusinessId' => esc_html( FacebookWordpressOptions::getExternalBusinessId() ),
          'deleteConfigKeys' => $this->getDeleteFbeSettingsAjaxRoute(),
          'installed' => FacebookWordpressOptions::getIsFbeInstalled(),
          'systemUserName' => esc_html( FacebookWordpressOptions::getExternalBusinessId() ),
          'pixelString' => esc_html( FacebookWordpressOptions::getPixelId() ),
          'piiCachingStatus' => FacebookWordpressOptions::getCapiPiiCachingStatus(),
          'fbAdvConfTop' => FacebookPluginConfig::CAPI_INTEGRATION_DIV_TOP,
          'capiIntegrationPageViewFiltered' => json_encode( FacebookWordpressOptions::getCapiIntegrationPageViewFiltered() ),
          'capiPiiCachingStatusSaveUrl' => $this->getCapiPiiCachingStatusSaveUrl(), 
          'capiPiiCachingStatusActionName' => FacebookPluginConfig::SAVE_CAPI_PII_CACHING_STATUS_ACTION_NAME,
          'capiPiiCachingStatusUpdateError' => FacebookPluginConfig::CAPI_PII_CACHING_STATUS_UPDATE_ERROR,
          'capiIntegrationEventsFilterSaveUrl' => $this->getCapiIntegrationEventsFilterSaveUrl(),
          'capiIntegrationEventsFilterActionName' => FacebookPluginConfig::SAVE_CAPI_INTEGRATION_EVENTS_FILTER_ACTION_NAME,
          'capiIntegrationEventsFilterUpdateError' => FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER_UPDATE_ERROR,
				)
			),
			'before'
		);
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
