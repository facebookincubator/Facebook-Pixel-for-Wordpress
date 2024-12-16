<?php
/**
 * Copyright (C) 2017-present, Meta, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the License.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Core;

/**
 * Class FacebookWordpressSettingsRecorder
 */
class FacebookWordpressSettingsRecorder {

    /**
     * Registers ajax actions for saving FBE,
     * CAPI integration status, CAPI events filter
     * and CAPI PII caching status.
     */
    public function init() {
        add_action(
            'wp_ajax_save_fbe_settings',
            array( $this, 'save_fbe_settings' )
        );
        add_action(
            'wp_ajax_delete_fbe_settings',
            array(
                $this,
                'delete_fbe_settings',
            )
        );
        add_action(
            'wp_ajax_save_capi_integration_status',
            array(
                $this,
                'save_capi_integration_status',
            )
        );
        add_action(
            'wp_ajax_save_capi_integration_events_filter',
            array(
                $this,
                'save_capi_integration_events_filter',
            )
        );
        add_action(
            'wp_ajax_save_capi_pii_caching_status',
            array(
                $this,
                'save_capi_pii_caching_status',
            )
        );
    }

    /**
     * Sends a successful response to the AJAX request.
     *
     * @param string $body A message to be sent in the response body.
     *
     * @return array Response data.
     */
    private function handle_success_request( $body ) {
        $res = array(
            'success' => true,
            'msg'     => $body,
        );
        wp_send_json( $res );
        return $res;
    }

    /**
     * Handles unauthorized request by sending a 403 response
     * with 'success' => false and 'msg' => 'Unauthorized user'.
     *
     * @return array response data
     */
    private function handle_unauthorized_request() {
        $res = array(
            'success' => false,
            'msg'     => 'Unauthorized user',
        );
        wp_send_json( $res, 403 );
        return $res;
    }

    /**
     * Handles invalid request by sending a 400 response
     * with 'success' => false and 'msg' => 'Invalid values'.
     *
     * @return array response data
     */
    private function handle_invalid_request() {
        $res = array(
            'success' => false,
            'msg'     => 'Invalid values',
        );
        wp_send_json( $res, 400 );
        return $res;
    }

    /**
     * Handles saving Facebook Business Extension settings.
     *
     * This function handles saving the Facebook Business Extension settings,
     * such as the pixel ID, access token, and external business ID.
     * It checks if the current user is an administrator, and if not,
     * it will return an unauthorized request response.
     * If the request is valid, it will save the settings to the WordPress
     * options table and return a success response.
     *
     * @return array response data
     */
    public function save_fbe_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->handle_unauthorized_request();
        }
        check_admin_referer(
            FacebookPluginConfig::SAVE_FBE_SETTINGS_ACTION_NAME
        );
        $pixel_id             = sanitize_text_field(
            isset( $_POST['pixelId'] ) ?
            wp_unslash( $_POST['pixelId'] ) : ''
        );
        $access_token         = sanitize_text_field(
            isset( $_POST['accessToken'] ) ?
            wp_unslash( $_POST['accessToken'] ) : ''
        );
        $external_business_id = sanitize_text_field(
            isset( $_POST['externalBusinessId'] ) ?
            wp_unslash( $_POST['externalBusinessId'] ) : ''
        );
        if ( empty( $pixel_id )
                || empty( $access_token )
                || empty( $external_business_id ) ) {
            return $this->handle_invalid_request();
        }
        $settings = array(
            FacebookPluginConfig::PIXEL_ID_KEY             => $pixel_id,
            FacebookPluginConfig::ACCESS_TOKEN_KEY         => $access_token,
            FacebookPluginConfig::EXTERNAL_BUSINESS_ID_KEY =>
            $external_business_id,
            FacebookPluginConfig::IS_FBE_INSTALLED_KEY     => '1',
        );
        \update_option(
            FacebookPluginConfig::SETTINGS_KEY,
            $settings
        );
        return $this->handle_success_request( $settings );
    }

    /**
     * Handles saving the CAPI integration status.
     *
     * This function handles saving the CAPI integration status, which indicates
     * whether the CAPI integration is enabled or disabled.
     * It checks if the current user is an administrator, and if not,
     * it will return an unauthorized request response.
     * If the request is valid, it will save the status to the WordPress
     * options table and return a success response.
     *
     * @return array response data
     */
    public function save_capi_integration_status() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->handle_unauthorized_request();
        }

        if ( empty( FacebookWordpressOptions::get_pixel_id() ) ) {
            \update_option(
                FacebookPluginConfig::CAPI_INTEGRATION_STATUS,
                FacebookPluginConfig::CAPI_INTEGRATION_STATUS_DEFAULT
            );
            return $this->handle_invalid_request();
        }

        check_admin_referer(
            FacebookPluginConfig::SAVE_CAPI_INTEGRATION_STATUS_ACTION_NAME
        );
        $val = sanitize_text_field(
            isset( $_POST['val'] ) ?
            wp_unslash( $_POST['val'] ) : ''
        );

        if ( ! ( '0' === $val || '1' === $val ) ) {
            return $this->handle_invalid_request();
        }

        \update_option( FacebookPluginConfig::CAPI_INTEGRATION_STATUS, $val );
        return $this->handle_success_request( $val );
    }

    /**
     * Handles saving the CAPI integration events filter.
     *
     * This function handles saving the CAPI integration events filter, which
     * determines which events are sent to the CAPI integration. It checks if
     * the current user is an administrator, and if not, it will return an
     * unauthorized request response.
     * If the request is valid, it will save the filter to the WordPress
     * options table and return a success response.
     *
     * @return array response data
     */
    public function save_capi_integration_events_filter() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->handle_unauthorized_request();
        }

        if ( empty( FacebookWordpressOptions::get_pixel_id() ) ) {
            \update_option(
                FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER,
                FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER_DEFAULT
            );
            return $this->handle_invalid_request();
        }

        check_admin_referer(
            FacebookPluginConfig::SAVE_CAPI_INTEGRATION_EVENTS_FILTER_ACTION_NAME
        );
        $val                    = sanitize_text_field(
            isset( $_POST['val'] ) ?
            wp_unslash( $_POST['val'] ) : ''
        );
        $const_filter_page_view =
        FacebookPluginConfig::CAPI_INTEGRATION_FILTER_PAGE_VIEW_EVENT;
        $const_keep_page_view   =
        FacebookPluginConfig::CAPI_INTEGRATION_KEEP_PAGE_VIEW_EVENT;

        if ( ! ( $val === $const_filter_page_view
        || $val === $const_keep_page_view ) ) {
            return $this->handle_invalid_request();
        }

        $page_view_filtered =
        FacebookWordpressOptions::get_capi_integration_page_view_filtered();

        if ( $val === $const_keep_page_view && $page_view_filtered ) {
            \update_option(
                FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER,
                FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER_DEFAULT
            );
        } elseif ( $val === $const_filter_page_view && ! $page_view_filtered ) {
            \update_option(
                FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER,
                FacebookPluginConfig::CAPI_INTEGRATION_EVENTS_FILTER_DEFAULT .
                    ',PageView'
            );
        }

        return $this->handle_success_request( $val );
    }

    /**
     * Handles saving the CAPI PII caching status.
     *
     * This function handles saving the CAPI PII caching status, which indicates
     * whether the CAPI PII caching is enabled or disabled.
     * It checks if the current user is an administrator, and if not,
     * it will return an unauthorized request response.
     * If the request is valid, it will save the status to the WordPress
     * options table and return a success response.
     *
     * @return array response data
     */
    public function save_capi_pii_caching_status() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->handle_unauthorized_request();
        }

        if ( empty( FacebookWordpressOptions::get_pixel_id() ) ) {
            \update_option(
                FacebookPluginConfig::CAPI_PII_CACHING_STATUS,
                FacebookPluginConfig::CAPI_PII_CACHING_STATUS_DEFAULT
            );
            return $this->handle_invalid_request();
        }

        check_admin_referer(
            FacebookPluginConfig::SAVE_CAPI_PII_CACHING_STATUS_ACTION_NAME
        );
        $val = sanitize_text_field(
            isset( $_POST['val'] ) ?
            wp_unslash( $_POST['val'] ) : ''
        );

        if ( ! ( '0' === $val || '1' === $val ) ) {
            return $this->handle_invalid_request();
        }

        \update_option( FacebookPluginConfig::CAPI_PII_CACHING_STATUS, $val );
        return $this->handle_success_request( $val );
    }

    /**
     * Deletes the Facebook Business Extension settings.
     *
     * This function deletes the Facebook Business
     * Extension settings from the WordPress
     * options table. It checks if the current user
     * is an administrator, and if not,
     * it will return an unauthorized request response.
     * If the request is valid, it will delete the
     * settings and return a success response.
     *
     * @return array response data
     */
    public function delete_fbe_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->handle_unauthorized_request();
        }
        check_admin_referer(
            FacebookPluginConfig::DELETE_FBE_SETTINGS_ACTION_NAME
        );
        \delete_option( FacebookPluginConfig::SETTINGS_KEY );
        \delete_transient( FacebookPluginConfig::AAM_SETTINGS_KEY );

        return $this->handle_success_request( 'Done' );
    }
}
