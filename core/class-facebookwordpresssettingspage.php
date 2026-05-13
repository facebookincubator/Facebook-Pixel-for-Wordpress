<?php
/**
 * Facebook Pixel Plugin FacebookWordpressSettingsPage class.
 *
 * This file contains the main logic for FacebookWordpressSettingsPage.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressSettingsPage class.
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

use FacebookPixelPlugin\FacebookAds\ApiConfig;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class FacebookWordpressSettingsPage
 */
class FacebookWordpressSettingsPage {
    /**
     * The options page for the plugin.
     *
     * @var string
     */
    private $options_page = '';

    /**
     * Registers the plugin's settings page, and adds the necessary hooks
     * for registering the plugin's scripts, notices, and menu items.
     *
     * @param string $plugin_name the name of the plugin.
     */
    public function __construct( $plugin_name ) {
        add_filter(
            'plugin_action_links_' . $plugin_name,
            array( $this, 'add_settings_link' )
        );
        add_action( 'admin_menu', array( $this, 'add_menu_fbe' ) );
        add_action( 'admin_init', array( $this, 'dismiss_notices' ) );

        add_action(
            'admin_enqueue_scripts',
            array( $this, 'register_plugin_scripts' )
        );
        add_action( 'current_screen', array( $this, 'register_notices' ) );
        $capi_event = new FacebookCapiEvent();
    }

    /**
     * Registers the plugin's scripts and styles.
     *
     * This function registers the 'fbe_allinone_script',
     * 'meta_settings_page_script', and 'meta_settings_page_style'
     * scripts and styles, and enqueues the style.
     */
    public function register_plugin_scripts() {
        wp_register_script(
            'fbe_allinone_script',
            plugins_url( '../js/fbe_allinone.js', __FILE__ ),
            array(),
            FacebookPluginConfig::PLUGIN_VERSION,
            false
        );
        wp_register_script(
            'meta_settings_page_script',
            plugins_url( '../js/settings_page.js', __FILE__ ),
            array(),
            FacebookPluginConfig::PLUGIN_VERSION,
            false
        );
        wp_register_style(
            'official-facebook-pixel',
            plugins_url( '../css/admin.css', __FILE__ ),
            array(),
            FacebookPluginConfig::PLUGIN_VERSION
        );
        wp_enqueue_style( 'official-facebook-pixel' );
    }

    /**
     * Adds the Facebook Business Extension options page to
     * the WordPress admin menu.
     *
     * This function is called by the WordPress 'admin_menu'
     * action hook.
     *
     * It adds an options page with the title 'Facebook Business Extension' and
     * the slug 'facebook_for_wordpress', and sets the 'add_fbe_box'
     * method as the callback function for rendering the page.
     *
     * It also adds two options to the wp_options table:
     * 'facebook_capi_integration_status' and
     * 'facebook_capi_integration_events_filter',
     *  with default values of '1' and
     * 'all_events', respectively.
     */
    public function add_menu_fbe() {
        $this->options_page = add_options_page(
            FacebookPluginConfig::ADMIN_PAGE_TITLE,
            FacebookPluginConfig::ADMIN_MENU_TITLE,
            FacebookPluginConfig::ADMIN_CAPABILITY,
            FacebookPluginConfig::ADMIN_MENU_SLUG,
            array( $this, 'add_fbe_box' )
        );

        \add_option(
            FacebookPluginConfig::CAPI_INTEGRATION_STATUS,
            FacebookPluginConfig::CAPI_INTEGRATION_STATUS_DEFAULT
        );
        \add_option(
            FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER,
            FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER_DEFAULT
        );
    }

    /**
     * Renders the Facebook Business Extension box on the settings page.
     *
     * This function checks if the current user has the necessary capabilities
     * to access the page. If not, it terminates the script
     * with an error message.
     * If the user has the required permissions, it displays any previous pixel
     * ID message and the FBE browser settings, and enqueues the
     * necessary script
     * for the page.
     *
     * @return void
     */
    public function add_fbe_box() {
        if ( ! current_user_can( FacebookPluginConfig::ADMIN_CAPABILITY ) ) {
            wp_die(
                esc_html__(
                    'You do not have permissions to access this page',
                    'official-facebook-pixel'
                )
            );
        }
        echo $this->get_fbe_browser_settings(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        wp_enqueue_script( 'fbe_allinone_script' );
    }

    /**
     * Generates and returns the browser settings HTML and JavaScript
     * for the Facebook Business Extension.
     *
     * This function constructs and outputs a set of HTML elements and
     * JavaScript code necessary for configuring
     * the Facebook Business Extension in the browser. It includes sections
     *  for advanced configuration, ads creation,
     * and ads insights, as well as functionality for testing
     * conversion API events.
     *
     * The function dynamically generates JSON-encoded configuration
     * data and embeds it into the HTML via
     * data attributes, allowing for the interactive configuration
     * of various Facebook Business Extension features.
     *
     * It also enqueues the necessary scripts and styles for
     * rendering the settings page and handles
     * inline script parameters for AJAX communication
     * and configuration management.
     *
     * @return string The HTML and JavaScript code for the
     * Facebook Business Extension browser settings.
     */
    private function get_fbe_browser_settings() {
        ob_start();
        $fbe_extras = wp_json_encode(
            array(
                'business_config' => array(
                    'business' => array(
                        'name' => 'Solutions_Engineering_Team',
                    ),
                ),
                'setup'           => array(
                    'external_business_id' =>
                        FacebookWordpressOptions::get_external_business_id(),
                    'timezone'             => 'America/Los_Angeles',
                    'currency'             => 'USD',
                    'business_vertical'    => 'ECOMMERCE',
                    'channel'              => 'DEFAULT',
                ),
                'repeat'          => false,
            )
        );
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

    <div class="events-manager-wrapper
    <?php
    echo empty( FacebookWordpressOptions::get_active_access_token() ) ?
    'hidden' : '';
    ?>
    ">
    <h3>Conversion API Tests</h3>

    <div class="events-manager-container">
        <div>
            <h3>Plugin Connected to Meta Events Manager</h3>

            <p>Meta Events Manager is a tool that
                enables you to view and manage your
                event data. In Events Manager, you can
                set up, monitor and troubleshoot issues
                with your integrations, such as the
                Conversions API and Meta pixel.</p>
            <p class="meta-event-manager">
                Visit the
                <a
                href="https://business.facebook.com/events_manager2/list/pixel/
                <?php
                echo esc_html(
                    FacebookWordpressOptions::get_active_pixel_id()
                );
                ?>
                "
                target="_blank">Meta Events Manager</a>
                to view the events being tracked.</p>
        </div>

        <div class="pixel-block events-manager-block">
            <label>Your Pixel ID</label>
            <input
            type="text"
            id="pixel-id"
            placeholder="
            <?php
            echo esc_html(
                FacebookWordpressOptions::get_active_pixel_id()
            );
            ?>
            "
            disabled />
        </div>

        <?php
        echo '<img class="test-form-img" src = ' .
        esc_html( plugin_dir_url( __DIR__ ) ) .
        'assets/event-log-head.png alt="Test form image">';
        ?>

        <div class="test-events-block events-manager-block">
            <form class="test-form" action="javascript:void(0);">
                <div class="test-form-field-wrapper">
                    <div class="test-hints">
                        <div class="test-hints__wrapper">
                            <span>&#63;</span>

                            <p>
                                To obtain the Test Event Code,
                                visit the Test Event section in the
                                <a
                                target="_blank"
                                href=
                                "
                                <?php
                                echo 'https://business.facebook.com'
                                . '/events_manager2/list/pixel/';
                                ?>
                                <?php
                                echo esc_html(
                                    FacebookWordpressOptions::get_active_pixel_id()
                                );
                                ?>
                                /test_events">
                                <b>Events Manager</b></a>.
                            </p>
                        </div>
                    </div>

                    <div class="text-form-inputs">
                        <div class="test-event-code-wrapper">
                            <label>Test Event Code</label>
                            <input
                            type="text"
                            id="event-test-code" placeholder="TEST4039" />
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
                        <span
                        class="advanced-edit-toggle"
                        onclick="toggleAdvancedPayload();">
                        Advanced | Edit Event Data
                            <svg class="advanced-edit-toggle-arrow"
                            width="12" height="8"
                            viewBox="0 0 14 8"
                            fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                d="M2 0L7 5L12 0L14 1L7 8L0 1L2 0Z"
                                fill="#555555"></path>
                            </svg>
                        </span>

                        <span
                        id="populate-payload-button"
                        onclick="populateAdvancedEvent(event);">
                        Click here to load default payload
                    </span>
                    </div>

                    <textarea
                    rows="13"
                    id="advanced-payload"
                    placeholder="Enter payload"></textarea>
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

                        <p class="event-hints__text initial-text">
                            No events logged yet.</p>

                        <span class="event-hints__close-icon hidden">
                            &#x2715;</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <?php
    wp_enqueue_script(
        'facebook-sdk',
        'https://connect.facebook.net/en_US/sdk.js',
        array(),
        '1.0.0',
        false
    );
        ?>
    <div id="meta-ads-plugin">
    <div id="ad-creation-plugin" class="
    <?php
    // Ads Creation only available for MBE connections (requires MBE scopes).
    echo ( 'mbe' !== FacebookWordpressOptions::get_connection_type()
        || empty( FacebookWordpressOptions::get_access_token() ) ) ?
    'hidden' : '';
    ?>
    ">
    <h3 class="mt-5">Ads Creation</h3>
        <div
        class="my-3 p-3 bg-white rounded shadow-sm">
        <div id="ad-creation-plugin-iframe" class="fb-lwi-ads-creation"
        data-lazy=true
        data-hide-manage-button=true
        data-fbe-extras='<?php echo esc_html( $fbe_extras ); ?>'
        data-fbe-scopes=
            'manage_business_extension,business_management,ads_management'
        data-fbe-redirect-uri='https://business.facebook.com/fbe-iframe-handler'
        ></div>
        </div>
    </div>
    <div id="ad-insights-plugin" class="
    <?php
    // Ads Insights only available for MBE connections (requires MBE scopes).
    echo ( 'mbe' !== FacebookWordpressOptions::get_connection_type()
        || empty( FacebookWordpressOptions::get_access_token() ) ) ?
    'hidden' : '';
    ?>
    ">
        <h3 class="mt-5">Ads Insights</h3>
        <div
        class="my-3 p-3 bg-white d-block rounded shadow-sm">
        <div id="ad-insights-plugin-iframe" class="fb-lwi-ads-insights"
        data-lazy=true
        data-fbe-extras='<?php echo esc_html( $fbe_extras ); ?>'
        data-fbe-scopes=
            'manage_business_extension,business_management,ads_management'
        data-fbe-redirect-uri='https://business.facebook.com/fbe-iframe-handler'
        ></div>
        </div>
    </div>
    </div>
</div>

        <?php
        $initial_script   = ob_get_clean();
        $access_token     = FacebookWordpressOptions::get_active_access_token();
        $has_access_token = ! empty( $access_token );

        wp_enqueue_script( 'meta_settings_page_script' );

        wp_add_inline_script(
            'meta_settings_page_script',
            'const meta_wc_params = ' . wp_json_encode(
                $this->get_meta_wc_params()
            ) . ';
            var hasAccessToken = ' . wp_json_encode( $has_access_token ) . ';
            if (!hasAccessToken) {
                jQuery("#fb-adv-conf").attr("data-access-token", "false");
            }',
            'before'
        );
        return $initial_script;
    }

    /**
     * Generates the AJAX route URL for saving FBE settings.
     *
     * This function creates a nonce for the AJAX action to ensure
     * security and constructs a URL for the admin-ajax.php endpoint
     * with the required query arguments, including the action name and nonce.
     *
     * @return string The URL with query arguments for the AJAX action.
     */
    public function get_fbe_save_settings_ajax_route() {
        $nonce_value = wp_create_nonce(
            FacebookPluginConfig::SAVE_FBE_SETTINGS_ACTION_NAME
        );
        $simple_url  = admin_url( 'admin-ajax.php' );
        $args        = array(
            'action'   => FacebookPluginConfig::SAVE_FBE_SETTINGS_ACTION_NAME,
            '_wpnonce' => $nonce_value,
        );
        return add_query_arg( $args, $simple_url );
    }

    /**
     * Generates the AJAX route URL for saving CAPE integration status.
     *
     * This function creates a nonce for the AJAX action to ensure
     * security and constructs a URL for the admin-ajax.php endpoint
     * with the required query arguments, including the action name and nonce.
     *
     * @return string The URL with query arguments for the AJAX action.
     */
    public function get_capi_integration_status_save_url() {
        $nonce_value = wp_create_nonce(
            FacebookPluginConfig::SAVE_CAPI_INTEGRATION_STATUS_ACTION_NAME
        );
        $simple_url  = admin_url( 'admin-ajax.php' );
        $args        = array(
            'action'   =>
            FacebookPluginConfig::SAVE_CAPI_INTEGRATION_STATUS_ACTION_NAME,
            '_wpnonce' => $nonce_value,
        );
        return add_query_arg( $args, $simple_url );
    }

    /**
     * Generates the AJAX route URL for saving CAPE events filter.
     *
     * This function creates a nonce for the AJAX action to ensure
     * security and constructs a URL for the admin-ajax.php endpoint
     * with the required query arguments, including the action name and nonce.
     *
     * @return string The URL with query arguments for the AJAX action.
     */
    public function get_capi_integration_events_filter_save_url() {
        $nonce_value = wp_create_nonce(
            FacebookPluginConfig::SAVE_CAPI_INTEGRATION_EVENTS_FILTER_ACTION_NAME
        );
        $simple_url  = admin_url( 'admin-ajax.php' );
        $args        = array(
            'action'   =>
            FacebookPluginConfig::SAVE_CAPI_INTEGRATION_EVENTS_FILTER_ACTION_NAME,
            '_wpnonce' => $nonce_value,
        );
        return add_query_arg( $args, $simple_url );
    }

    /**
     * Generates the AJAX route URL for saving CAPI PII caching status.
     *
     * This function creates a nonce for the AJAX action to ensure
     * security and constructs a URL for the admin-ajax.php endpoint
     * with the required query arguments, including the action name and nonce.
     *
     * @return string The URL with query arguments for the AJAX action.
     */
    public function get_capi_pii_caching_status_save_url() {
        $nonce_value = wp_create_nonce(
            FacebookPluginConfig::SAVE_CAPI_PII_CACHING_STATUS_ACTION_NAME
        );
        $simple_url  = admin_url( 'admin-ajax.php' );
        $args        = array(
            'action'   =>
            FacebookPluginConfig::SAVE_CAPI_PII_CACHING_STATUS_ACTION_NAME,
            '_wpnonce' => $nonce_value,
        );
        return add_query_arg( $args, $simple_url );
    }

    /**
     * Generates the AJAX route URL for deleting FBE settings.
     *
     * This function creates a nonce for the AJAX action to ensure
     * security and constructs a URL for the admin-ajax.php endpoint
     * with the required query arguments, including the action name and nonce.
     *
     * @return string The URL with query arguments for the AJAX action.
     */
    public function get_delete_fbe_settings_ajax_route() {
        $nonce_value = wp_create_nonce(
            FacebookPluginConfig::DELETE_FBE_SETTINGS_ACTION_NAME
        );
        $simple_url  = admin_url( 'admin-ajax.php' );
        $args        = array(
            'action'   => FacebookPluginConfig::DELETE_FBE_SETTINGS_ACTION_NAME,
            '_wpnonce' => $nonce_value,
        );
        return add_query_arg( $args, $simple_url );
    }

    /**
     * Builds the complete meta_wc_params array for the settings page JS.
     *
     * @return array The configuration parameters.
     */
    private function get_meta_wc_params() {
        $params = array(
            'ajax_url'                               =>
                admin_url( 'admin-ajax.php' ),
            'send_capi_event_nonce'                  =>
                wp_create_nonce( 'send_capi_event_nonce' ),
            'pixelId'                                =>
                FacebookWordpressOptions::get_pixel_id(),
            'setSaveSettingsRoute'                   =>
                $this->get_fbe_save_settings_ajax_route(),
            'externalBusinessId'                     =>
                esc_html( FacebookWordpressOptions::get_external_business_id() ),
            'deleteConfigKeys'                       =>
                $this->get_delete_fbe_settings_ajax_route(),
            'installed'                              =>
                FacebookWordpressOptions::get_is_fbe_installed(),
            'connectionType'                         =>
                FacebookWordpressOptions::get_connection_type(),
            'systemUserName'                         =>
                esc_html( FacebookWordpressOptions::get_external_business_id() ),
            'pixelString'                            =>
                esc_html( FacebookWordpressOptions::get_pixel_id() ),
            'piiCachingStatus'                       =>
                FacebookWordpressOptions::get_capi_pii_caching_status(),
            'fbAdvConfTop'                           =>
                FacebookPluginConfig::CAPI_INTEGRATION_DIV_TOP,
            'capiIntegrationPageViewFiltered'        =>
                wp_json_encode(
                    FacebookWordpressOptions::get_capi_integration_page_view_filtered()
                ),
            'capiPiiCachingStatusSaveUrl'            =>
                $this->get_capi_pii_caching_status_save_url(),
            'capiPiiCachingStatusActionName'         =>
                FacebookPluginConfig::SAVE_CAPI_PII_CACHING_STATUS_ACTION_NAME,
            'capiPiiCachingStatusUpdateError'        =>
                FacebookPluginConfig::CAPI_PII_CACHING_STATUS_UPDATE_ERROR,
            'capiIntegrationEventsFilterSaveUrl'     =>
                $this->get_capi_integration_events_filter_save_url(),
            'capiIntegrationEventsFilterActionName'  =>
                FacebookPluginConfig::SAVE_CAPI_INTEGRATION_EVENTS_FILTER_ACTION_NAME,
            'capiIntegrationEventsFilterUpdateError' =>
                FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER_UPDATE_ERROR,
        );

        // FBL4B config — only included if app_id is provisioned.
        $fbl4b_app_id    = defined( 'FB_FBL4B_APP_ID' )
            ? FB_FBL4B_APP_ID
            : FacebookPluginConfig::FBL4B_APP_ID;
        $fbl4b_config_id = defined( 'FB_FBL4B_CONFIG_ID' )
            ? FB_FBL4B_CONFIG_ID
            : FacebookPluginConfig::FBL4B_CONFIG_ID;

        if ( ! empty( $fbl4b_app_id ) ) {
            $params['fbl4bAppId']                = $fbl4b_app_id;
            $params['fbl4bConfigId']             = $fbl4b_config_id;
            $params['fbl4bPopupOrigin']          =
                $this->get_fbl4b_popup_origin();
            $params['fbl4bIframeUrl']            =
                $this->get_fbl4b_iframe_url( $fbl4b_app_id, $fbl4b_config_id );
            $params['fbl4bSaveSettingsRoute']    =
                $this->get_fbl4b_save_settings_ajax_route();
            $params['fbl4bDeleteSettingsRoute']  =
                $this->get_fbl4b_delete_settings_ajax_route();
            $params['fbl4bFetchBusinessIdRoute'] =
                $this->get_fbl4b_fetch_business_id_route();
            $params['fbl4bFetchPixelsRoute']     =
                $this->get_fbl4b_fetch_pixels_route();
            $params['fbl4bValidateTokenRoute']   =
                $this->get_fbl4b_validate_token_route();
            $params['fbl4bClearPixelRoute']      =
                $this->get_fbl4b_clear_pixel_route();
            $params['fbl4bPixelId']              =
                FacebookWordpressOptions::get_fbl4b_pixel_id();
            $params['fbl4bPixelName']            =
                FacebookWordpressOptions::get_fbl4b_pixel_name();
            $params['fbl4bBusinessId']           =
                FacebookWordpressOptions::get_fbl4b_business_id();
        } else {
            $params['fbl4bAppId'] = '';
        }

        return $params;
    }

    /**
     * Returns the FBL4B popup origin URL.
     *
     * @return string The popup origin URL.
     */
    private function get_fbl4b_popup_origin() {
        if ( defined( 'META_PIXEL_BASE_DOMAIN' ) ) {
            return 'https://business.' . META_PIXEL_BASE_DOMAIN;
        }
        return 'https://business.facebook.com';
    }

    /**
     * Builds the FBL4B iframe URL with app_id and config_id query params.
     *
     * @param string $app_id    The Meta app ID.
     * @param string $config_id The FBL4B config ID (must be SUAT type).
     * @return string The full iframe URL.
     */
    private function get_fbl4b_iframe_url( $app_id, $config_id ) {
        $base = defined( 'META_PIXEL_BASE_DOMAIN' )
            ? 'https://business.' . META_PIXEL_BASE_DOMAIN
            : 'https://business.facebook.com';
        return $base . '/fbl4b-iframe-get-started/?app_id='
            . rawurlencode( $app_id ) . '&config_id=' . rawurlencode( $config_id );
    }

    /**
     * Generates AJAX route for saving FBL4B settings.
     *
     * @return string The URL with query arguments.
     */
    public function get_fbl4b_save_settings_ajax_route() {
        return add_query_arg(
            array(
                'action'   => 'save_fbl4b_settings',
                '_wpnonce' => wp_create_nonce( 'save_fbl4b_settings' ),
            ),
            admin_url( 'admin-ajax.php' )
        );
    }

    /**
     * Generates AJAX route for deleting FBL4B settings.
     *
     * @return string The URL with query arguments.
     */
    public function get_fbl4b_delete_settings_ajax_route() {
        return add_query_arg(
            array(
                'action'   => 'delete_fbl4b_settings',
                '_wpnonce' => wp_create_nonce( 'delete_fbl4b_settings' ),
            ),
            admin_url( 'admin-ajax.php' )
        );
    }

    /**
     * Generates AJAX route for fetching FBL4B business ID.
     *
     * @return string The URL with query arguments.
     */
    public function get_fbl4b_fetch_business_id_route() {
        return add_query_arg(
            array(
                'action'   => 'fbl4b_fetch_business_id',
                '_wpnonce' => wp_create_nonce( 'fbl4b_fetch_business_id' ),
            ),
            admin_url( 'admin-ajax.php' )
        );
    }

    /**
     * Generates AJAX route for fetching FBL4B pixels.
     *
     * @return string The URL with query arguments.
     */
    public function get_fbl4b_fetch_pixels_route() {
        return add_query_arg(
            array(
                'action'   => 'fbl4b_fetch_pixels',
                '_wpnonce' => wp_create_nonce( 'fbl4b_fetch_pixels' ),
            ),
            admin_url( 'admin-ajax.php' )
        );
    }

    /**
     * Generates AJAX route for validating FBL4B token.
     *
     * @return string The URL with query arguments.
     */
    public function get_fbl4b_validate_token_route() {
        return add_query_arg(
            array(
                'action'   => 'fbl4b_validate_token',
                '_wpnonce' => wp_create_nonce( 'fbl4b_validate_token' ),
            ),
            admin_url( 'admin-ajax.php' )
        );
    }

    /**
     * Generates AJAX route for clearing FBL4B pixel selection.
     *
     * @return string The URL with query arguments.
     */
    public function get_fbl4b_clear_pixel_route() {
        return add_query_arg(
            array(
                'action'   => 'fbl4b_clear_pixel',
                '_wpnonce' => wp_create_nonce( 'fbl4b_clear_pixel' ),
            ),
            admin_url( 'admin-ajax.php' )
        );
    }

    /**
     * Adds a settings link to the plugin action links.
     *
     * This function appends a "Settings" link to the given
     * array of plugin action links.
     * The link directs the user to the Facebook Business
     * Extension settings page.
     *
     * @param array $links An array of existing plugin action links.
     * @return array The modified array of plugin action
     * links with the settings link added.
     */
    public function add_settings_link( $links ) {
        $settings = array(
            'settings' => sprintf(
                '<a href="%s">%s</a>',
                admin_url(
                    'options-general.php?page=' .
                    FacebookPluginConfig::ADMIN_MENU_SLUG
                ),
                'Settings'
            ),
        );
        return array_merge( $settings, $links );
    }

    /**
     * Registers admin notices for the Facebook Business Extension.
     *
     * This function determines whether the Facebook Business
     * Extension is installed
     * and whether the user has dismissed the notice. If the
     * extension is not installed
     * and the user has not dismissed the notice, it
     * registers the 'fbe_not_installed_notice'
     * function to display the notice. If the extension is
     * installed and the user has not
     * dismissed the review notice, it registers the
     * 'plugin_review_notice' function to
     * display the review notice.
     */
    public function register_notices() {
        $is_fbe_installed  = FacebookWordpressOptions::get_is_fbe_installed();
        $current_screen_id = get_current_screen()->id;

        if ( current_user_can( FacebookPluginConfig::ADMIN_CAPABILITY ) &&
        in_array(
            $current_screen_id,
            array( 'dashboard', 'plugins' ),
            true
        )
        ) {
            if ( $this->should_show_wpcom_update_notice() ) {
                add_action(
                    'admin_notices',
                    array( $this, 'wpcom_update_notice' )
                );
            }
            if ( '0' == $is_fbe_installed && ! get_user_meta( // phpcs:ignore Universal.Operators.StrictComparisons
                get_current_user_id(),
                FacebookPluginConfig::ADMIN_IGNORE_FBE_NOT_INSTALLED_NOTICE,
                true
            ) ) {
                add_action(
                    'admin_notices',
                    array( $this, 'fbe_not_installed_notice' )
                );
            }
            if ( '1' == $is_fbe_installed && ! get_user_meta( // phpcs:ignore Universal.Operators.StrictComparisons
                get_current_user_id(),
                FacebookPluginConfig::ADMIN_IGNORE_PLUGIN_REVIEW_NOTICE,
                true
            ) ) {
                add_action(
                    'admin_notices',
                    array( $this, 'plugin_review_notice' )
                );
            }
        }

        // Show FBL4B upgrade banner on dashboard, plugins, AND settings page.
        $fbl4b_screens = array(
            'dashboard',
            'plugins',
            'settings_page_' . FacebookPluginConfig::ADMIN_MENU_SLUG,
        );
        if ( current_user_can( FacebookPluginConfig::ADMIN_CAPABILITY )
            && in_array( $current_screen_id, $fbl4b_screens, true )
        ) {
            $fbl4b_app_id = defined( 'FB_FBL4B_APP_ID' )
                ? FB_FBL4B_APP_ID
                : FacebookPluginConfig::FBL4B_APP_ID;
            if ( 'mbe' === FacebookWordpressOptions::get_connection_type()
                && ! empty( $fbl4b_app_id )
                && ! get_user_meta(
                    get_current_user_id(),
                    FacebookPluginConfig::ADMIN_IGNORE_FBL4B_UPGRADE_NOTICE,
                    true
                )
            ) {
                add_action(
                    'admin_notices',
                    array( $this, 'fbl4b_upgrade_notice' )
                );
            }
        }
    }

    /**
     * Returns a customized message for the Facebook Business
     * Extension not installed notice.
     *
     * This function determines if a valid pixel ID and access
     * token are set. If both are set, it
     * suggests using the plugin to manage the connection to Meta.
     * If only the pixel ID is set, it
     * highlights the Conversions API feature. If neither is set,
     * it suggests completing the setup
     * steps.
     *
     * @return string The customized message.
     */
    public function get_customized_fbe_not_installed_notice() {
        $valid_pixel_id     = ! empty(
            FacebookWordpressOptions::get_pixel_id()
        );
        $valid_access_token = ! empty(
            FacebookWordPressOptions::get_access_token()
        );
        $message            = '';
        $plugin_name_tag    = sprintf(
            '<strong>%s</strong>',
            FacebookPluginConfig::PLUGIN_NAME
        );
        if ( $valid_pixel_id ) {
            if ( $valid_access_token ) {
                $message = sprintf(
                    'Easily manage your connection to Meta with %s.',
                    $plugin_name_tag
                );
            } else {
                $message = sprintf(
                    '%s gives you access to the Conversions API.',
                    $plugin_name_tag
                );
            }
        } else {
            $message = sprintf( '%s is almost ready.', $plugin_name_tag );
        }
        return $message . ' To complete your configuration, ' .
        '<a href="%s">follow the setup steps.</a>';
    }

    /**
     * Determines whether the WordPress.com update notice should be shown.
     *
     * @return bool Whether the WordPress.com update notice should be shown.
     */
    public function should_show_wpcom_update_notice() {
        if ( 1 !== (int) get_option( 'is_wordpress_com_hosted' ) ) {
            return false;
        }

        return ! get_user_meta(
            get_current_user_id(),
            FacebookPluginConfig::ADMIN_IGNORE_WPCOM_UPDATE_NOTICE,
            true
        );
    }

    /**
     * Returns the WordPress.com update notice message.
     *
     * @return string The WordPress.com update notice message.
     */
    public function get_wpcom_update_notice_message() {
        $plugin_url = 'https://wordpress.org/plugins/' . FacebookPluginConfig::TEXT_DOMAIN . '/';
        return sprintf(
            /* translators: %s: WordPress.org plugin page URL */
            __(
                'Meta Pixel for WordPress is expected to be removed from the WordPress.com marketplace soon. When this happens, it will no longer receive automatic updates on WordPress.com-hosted websites. <a href="%s">Download the latest version from WordPress.org</a> and install it manually via <strong>Plugins</strong> &rsaquo; <strong>Add New Plugin</strong> &rsaquo; <strong>Upload Plugin</strong> to ensure you receive future updates.',
                'official-facebook-pixel'
            ),
            esc_url( $plugin_url )
        );
    }

    /**
     * Displays a WordPress admin notice with a dismiss button.
     *
     * This function generates an HTML notice with the
     * specified content and type,
     * which can be dismissed by the user. It constructs a URL for the settings
     * page and includes a button to dismiss the notice.
     *
     * @param string $notice The content of the notice,
     * with a placeholder for the settings URL.
     * @param array  $dismiss_config Configuration for
     * the dismissal URL query arguments.
     * @param string $notice_type The type of notice
     * to display (e.g., 'warning', 'info').
     */
    public function set_notice( $notice, $dismiss_config, $notice_type ) {
        $url = admin_url(
            'options-general.php?page=' .
            FacebookPluginConfig::ADMIN_MENU_SLUG
        );

        $link = sprintf(
            $notice,
            esc_url( $url )
        );
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
            esc_html( $notice_type ),
            wp_kses_post( $link ),
            esc_url( add_query_arg( $dismiss_config, '' ) ),
            esc_html__(
                'Dismiss this notice.',
                'official-facebook-pixel'
            )
        );
    }

    /**
     * Displays a notice asking the user to leave a review for the plugin.
     *
     * If the user has not dismissed the review notice, this function generates
     * an HTML notice with a link to the plugin's
     * review page and a dismiss button.
     *
     * @since 3.0.0
     */
    public function plugin_review_notice() {
        $message = sprintf(
        /* translators: %1$s: Plugin name, %2$s: Review page URL */
            __(
                'Let us know what you think about <strong>%1$s</strong>.
                Leave a review on <a href="%2$s" target="_blank">
                this page</a>.',
                'official-facebook-pixel'
            ),
            FacebookPluginConfig::PLUGIN_NAME,
            FacebookPluginConfig::PLUGIN_REVIEW_PAGE
        );
        $this->set_notice(
            $message,
            FacebookPluginConfig::ADMIN_DISMISS_PLUGIN_REVIEW_NOTICE,
            'info'
        );
    }

    /**
     * Displays a notice about WordPress.com automatic updates ending.
     *
     * @return void
     */
    public function wpcom_update_notice() {
        $this->set_notice(
            $this->get_wpcom_update_notice_message(),
            FacebookPluginConfig::ADMIN_DISMISS_WPCOM_UPDATE_NOTICE,
            'warning'
        );
    }

    /**
     * Displays a notice indicating that the Facebook
     * Business Extension is not installed.
     *
     * This function retrieves a customized message for the
     * Facebook Business Extension not installed notice
     * and uses it to generate an HTML admin notice.
     * The notice is dismissible and marked as a warning type.
     *
     * @since 3.0.0
     */
    public function fbe_not_installed_notice() {
        $message = $this->get_customized_fbe_not_installed_notice();
        $this->set_notice(
            $message,
            FacebookPluginConfig::ADMIN_DISMISS_FBE_NOT_INSTALLED_NOTICE,
            'warning'
        );
    }

    /**
     * Handles dismissals of admin notices.
     *
     * This function checks for the presence of
     * query arguments that indicate a notice
     * should be dismissed. If such an argument
     * is present, it updates the corresponding
     * user meta value to true, indicating that
     * the notice should no longer be shown.
     *
     * @since 3.0.0
     */
    public function dismiss_notices() {
        $user_id = get_current_user_id();
        if ( isset(
            $_GET[ FacebookPluginConfig::ADMIN_DISMISS_FBE_NOT_INSTALLED_NOTICE ] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ) ) {
            update_user_meta(
                $user_id,
                FacebookPluginConfig::ADMIN_IGNORE_FBE_NOT_INSTALLED_NOTICE,
                true
            );
        }
        if ( isset(
            $_GET[ FacebookPluginConfig::ADMIN_DISMISS_PLUGIN_REVIEW_NOTICE ] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ) ) {
            update_user_meta(
                $user_id,
                FacebookPluginConfig::ADMIN_IGNORE_PLUGIN_REVIEW_NOTICE,
                true
            );
        }
        if ( isset(
            $_GET[ FacebookPluginConfig::ADMIN_DISMISS_WPCOM_UPDATE_NOTICE ] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ) ) {
            update_user_meta(
                $user_id,
                FacebookPluginConfig::ADMIN_IGNORE_WPCOM_UPDATE_NOTICE,
                true
            );
        }
        if ( isset(
            $_GET[ FacebookPluginConfig::ADMIN_DISMISS_FBL4B_UPGRADE_NOTICE ] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ) ) {
            update_user_meta(
                $user_id,
                FacebookPluginConfig::ADMIN_IGNORE_FBL4B_UPGRADE_NOTICE,
                true
            );
        }
    }

    /**
     * Renders the FBL4B upgrade notice for existing MBE users.
     *
     * Shows a banner prompting MBE users to upgrade to FBL4B
     * with an "Upgrade Now" button and dismiss option.
     */
    public function fbl4b_upgrade_notice() {
        $dismiss_url  = add_query_arg(
            FacebookPluginConfig::ADMIN_DISMISS_FBL4B_UPGRADE_NOTICE,
            '1'
        );
        $settings_url = admin_url(
            'options-general.php?page='
            . FacebookPluginConfig::ADMIN_MENU_SLUG
        );
        $upgrade_url  = add_query_arg( 'upgrade_to_fbl4b', '1', $settings_url );
        ?>
        <div class="notice notice-info fbl4b-upgrade-notice">
            <div class="fbl4b-upgrade-notice-content">
                <p><strong>Important Update: Meta Pixel for WordPress</strong></p>
                <p>
                    We are transitioning to a more secure connection method,
                    and the previous method will soon be deprecated.
                    Upgrade to Facebook Login for Business now to avoid future interruptions.
                    Your current setup will not be affected during the upgrade.
                </p>
                <p>
                    <a href="<?php echo esc_url( $upgrade_url ); ?>"
                       class="button button-primary" style="margin-right: 8px;">Upgrade Now</a>
                    <a href="<?php echo esc_url( $dismiss_url ); ?>">Dismiss</a>
                </p>
            </div>
        </div>
        <?php
    }
}
