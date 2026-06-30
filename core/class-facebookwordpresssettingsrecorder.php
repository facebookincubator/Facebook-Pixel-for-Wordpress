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
     * Mapper used for the form field mapping AJAX handlers.
     *
     * @var FormFieldMapper
     */
    private $field_mapper;

    /**
     * Constructor.
     *
     * @param FormFieldMapper|null $field_mapper Optional mapper. Defaults to a
     *                                           WordPress option-backed mapper;
     *                                           inject a fake in tests.
     */
    public function __construct( $field_mapper = null ) {
        $this->field_mapper = $field_mapper instanceof FormFieldMapper
            ? $field_mapper
            : new FormFieldMapper();
    }

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
        add_action(
            'wp_ajax_save_capig',
            array(
                $this,
                'save_capig',
            )
        );
        // FBL4B AJAX actions.
        add_action(
            'wp_ajax_save_fbl4b_settings',
            array( $this, 'save_fbl4b_settings' )
        );
        add_action(
            'wp_ajax_delete_fbl4b_settings',
            array( $this, 'delete_fbl4b_settings' )
        );
        add_action(
            'wp_ajax_fbl4b_validate_token',
            array( $this, 'fbl4b_validate_token' )
        );
        add_action(
            'wp_ajax_fbl4b_fetch_business_id',
            array( $this, 'fbl4b_fetch_business_id' )
        );
        add_action(
            'wp_ajax_fbl4b_fetch_pixels',
            array( $this, 'fbl4b_fetch_pixels' )
        );
        add_action(
            'wp_ajax_fbl4b_clear_pixel',
            array( $this, 'fbl4b_clear_pixel' )
        );

        // Form field mapping AJAX actions.
        add_action(
            'wp_ajax_' . FacebookPluginConfig::GET_MAPPING_FORMS_ACTION_NAME,
            array( $this, 'get_mapping_forms' )
        );
        add_action(
            'wp_ajax_' . FacebookPluginConfig::GET_MAPPING_FIELDS_ACTION_NAME,
            array( $this, 'get_mapping_fields' )
        );
        add_action(
            'wp_ajax_' . FacebookPluginConfig::SAVE_FIELD_MAPPING_ACTION_NAME,
            array( $this, 'save_field_mapping' )
        );
        add_action(
            'wp_ajax_' . FacebookPluginConfig::DELETE_FIELD_MAPPING_ACTION_NAME,
            array( $this, 'delete_field_mapping' )
        );
    }

    /**
     * Resolves the integration class for a tracking name received over AJAX.
     *
     * Only integrations that support the Field Mapping screen are eligible,
     * preventing arbitrary class resolution from request input.
     *
     * @param string $tracking_name The integration tracking name.
     * @return string|null The fully-qualified class name, or null if invalid.
     */
    private function resolve_mapping_integration( $tracking_name ) {
        $integrations =
            FacebookPluginConfig::get_field_mapping_integrations();
        return isset( $integrations[ $tracking_name ] )
            ? $integrations[ $tracking_name ]
            : null;
    }

    /**
     * Returns the list of forms for the requested integration.
     *
     * @return array response data
     */
    public function get_mapping_forms() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->handle_unauthorized_request();
        }
        check_admin_referer(
            FacebookPluginConfig::GET_MAPPING_FORMS_ACTION_NAME
        );

        $tracking_name = sanitize_text_field(
            isset( $_POST['integration'] ) ?
            wp_unslash( $_POST['integration'] ) : ''
        );
        $class         = $this->resolve_mapping_integration( $tracking_name );
        if ( null === $class ) {
            return $this->handle_invalid_request();
        }

        return $this->handle_success_request(
            array( 'forms' => $class::get_forms() )
        );
    }

    /**
     * Returns the fields for a form plus its currently saved mapping.
     *
     * @return array response data
     */
    public function get_mapping_fields() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->handle_unauthorized_request();
        }
        check_admin_referer(
            FacebookPluginConfig::GET_MAPPING_FIELDS_ACTION_NAME
        );

        $tracking_name = sanitize_text_field(
            isset( $_POST['integration'] ) ?
            wp_unslash( $_POST['integration'] ) : ''
        );
        $form_id       = sanitize_text_field(
            isset( $_POST['form_id'] ) ?
            wp_unslash( $_POST['form_id'] ) : ''
        );
        $class         = $this->resolve_mapping_integration( $tracking_name );
        if ( null === $class || '' === $form_id ) {
            return $this->handle_invalid_request();
        }

        return $this->handle_success_request(
            array(
                'fields'  => $class::get_form_fields( $form_id ),
                'mapping' => $this->field_mapper->get_mappings(
                    $tracking_name,
                    $form_id
                ),
            )
        );
    }

    /**
     * Persists a form's field mapping (upsert; one mapping per form).
     *
     * Expects POST: integration, form_id, form_title, and mapping as a JSON
     * object of field_id => standard_field. Invalid targets are dropped by
     * FormFieldMapper.
     *
     * @return array response data
     */
    public function save_field_mapping() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->handle_unauthorized_request();
        }
        check_admin_referer(
            FacebookPluginConfig::SAVE_FIELD_MAPPING_ACTION_NAME
        );

        $tracking_name = sanitize_text_field(
            isset( $_POST['integration'] ) ?
            wp_unslash( $_POST['integration'] ) : ''
        );
        $form_id       = sanitize_text_field(
            isset( $_POST['form_id'] ) ?
            wp_unslash( $_POST['form_id'] ) : ''
        );
        $form_title    = sanitize_text_field(
            isset( $_POST['form_title'] ) ?
            wp_unslash( $_POST['form_title'] ) : ''
        );
        $class         = $this->resolve_mapping_integration( $tracking_name );
        if ( null === $class || '' === $form_id ) {
            return $this->handle_invalid_request();
        }

        $raw_mapping = isset( $_POST['mapping'] )
            ? json_decode(
                sanitize_textarea_field( wp_unslash( $_POST['mapping'] ) ),
                true
            )
            : array();
        if ( ! is_array( $raw_mapping ) ) {
            return $this->handle_invalid_request();
        }

        $mapping = array();
        foreach ( $raw_mapping as $field_id => $standard ) {
            $field_id = sanitize_text_field( (string) $field_id );
            $standard = sanitize_text_field( (string) $standard );
            if ( '' === $field_id || '' === $standard ) {
                continue;
            }
            $mapping[ $field_id ] = $standard;
        }

        $this->field_mapper->save_form_mapping(
            $tracking_name,
            $form_id,
            $form_title,
            $mapping
        );

        return $this->handle_success_request(
            array( 'list' => $this->field_mapper->get_flat_list() )
        );
    }

    /**
     * Deletes a form's saved field mapping.
     *
     * @return array response data
     */
    public function delete_field_mapping() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->handle_unauthorized_request();
        }
        check_admin_referer(
            FacebookPluginConfig::DELETE_FIELD_MAPPING_ACTION_NAME
        );

        $tracking_name = sanitize_text_field(
            isset( $_POST['integration'] ) ?
            wp_unslash( $_POST['integration'] ) : ''
        );
        $form_id       = sanitize_text_field(
            isset( $_POST['form_id'] ) ?
            wp_unslash( $_POST['form_id'] ) : ''
        );
        if ( '' === $tracking_name || '' === $form_id ) {
            return $this->handle_invalid_request();
        }

        $this->field_mapper->delete_form_mapping( $tracking_name, $form_id );

        return $this->handle_success_request(
            array( 'list' => $this->field_mapper->get_flat_list() )
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
        \delete_transient(
            FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT
        );
        delete_metadata(
            'user',
            0,
            FacebookPluginConfig::ADMIN_IGNORE_CONNECTION_INVALID_NOTICE,
            '',
            true
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

        if ( empty( FacebookWordpressOptions::get_active_pixel_id() ) ) {
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

        if ( empty( FacebookWordpressOptions::get_active_pixel_id() ) ) {
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

        if ( empty( FacebookWordpressOptions::get_active_pixel_id() ) ) {
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
     * Persists the Conversions API Gateway (CAPIG) admin toggle.
     *
     * Accepts '1' (default — fbq optinMetaEnabledCapi command is emitted) or
     * '0' (suppress the command). Admin-only.
     *
     * @return array response data
     */
    public function save_capig() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->handle_unauthorized_request();
        }

        if ( empty( FacebookWordpressOptions::get_active_pixel_id() ) ) {
            \update_option(
                FacebookPluginConfig::CAPIG,
                FacebookPluginConfig::CAPIG_DEFAULT
            );
            return $this->handle_invalid_request();
        }

        check_admin_referer(
            FacebookPluginConfig::SAVE_CAPIG_ACTION_NAME
        );
        $val = sanitize_text_field(
            isset( $_POST['val'] ) ?
            wp_unslash( $_POST['val'] ) : ''
        );

        if ( ! ( '0' === $val || '1' === $val ) ) {
            return $this->handle_invalid_request();
        }

        \update_option( FacebookPluginConfig::CAPIG, $val );
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

    /**
     * Returns the Graph API base URL.
     *
     * @return string The Graph API base URL.
     */
    private function get_graph_api_base_url() {
        return 'https://graph.facebook.com/' . FacebookPluginConfig::GRAPH_API_VERSION;
    }

    /**
     * Saves FBL4B settings. On initial save, encrypts and stores the
     * access token. On partial update (pixel selection), merges with
     * existing settings.
     *
     * @return array Response data.
     */
    public function save_fbl4b_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->handle_unauthorized_request();
        }
        check_admin_referer( 'save_fbl4b_settings' );

        $access_token = '';
        // Read access token from Authorization: Bearer header.
        $auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) )
            : '';
        if ( 0 === strpos( $auth_header, 'Bearer ' ) ) {
            $access_token = substr( $auth_header, 7 );
        }
        $pixel_id    = sanitize_text_field(
            isset( $_POST['pixelId'] ) ?
            wp_unslash( $_POST['pixelId'] ) : ''
        );
        $pixel_name  = sanitize_text_field(
            isset( $_POST['pixelName'] ) ?
            wp_unslash( $_POST['pixelName'] ) : ''
        );
        $business_id = sanitize_text_field(
            isset( $_POST['businessId'] ) ?
            wp_unslash( $_POST['businessId'] ) : ''
        );

        if ( empty( $access_token ) ) {
            // Partial update (e.g., pixel selection after initial save).
            $existing = \get_option(
                FacebookPluginConfig::FBL4B_SETTINGS_KEY,
                array()
            );
            if ( empty( $existing ) ) {
                return $this->handle_invalid_request();
            }
            if ( ! empty( $pixel_id ) ) {
                $existing[ FacebookPluginConfig::FBL4B_PIXEL_ID_KEY ] = $pixel_id;
            }
            if ( ! empty( $pixel_name ) ) {
                $existing[ FacebookPluginConfig::FBL4B_PIXEL_NAME_KEY ] =
                    $pixel_name;
            }
            if ( ! empty( $business_id ) ) {
                $existing[ FacebookPluginConfig::FBL4B_BUSINESS_ID_KEY ] =
                    $business_id;
            }
            \update_option(
                FacebookPluginConfig::FBL4B_SETTINGS_KEY,
                $existing
            );
            // If FBL4B now has a pixel, mark MBE as not installed so
            // Connection doesn't fall back to MBE after FBL4B disconnect.
            if ( ! empty( $pixel_id ) ) {
                $mbe_settings = \get_option(
                    FacebookPluginConfig::SETTINGS_KEY,
                    array()
                );
                if ( ! empty( $mbe_settings ) ) {
                    $mbe_settings[ FacebookPluginConfig::IS_FBE_INSTALLED_KEY ] = '0';
                    \update_option(
                        FacebookPluginConfig::SETTINGS_KEY,
                        $mbe_settings
                    );
                }
            }
            \delete_transient(
                FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT
            );
            delete_metadata(
                'user',
                0,
                FacebookPluginConfig::ADMIN_IGNORE_CONNECTION_INVALID_NOTICE,
                '',
                true
            );
            return $this->handle_success_request( $existing );
        }

        // Initial save — encrypt and store the access token.
        $fbl4b_settings = array(
            FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY =>
                FacebookWordpressOptions::encrypt_token( $access_token ),
            FacebookPluginConfig::FBL4B_PIXEL_ID_KEY     => $pixel_id,
            FacebookPluginConfig::FBL4B_PIXEL_NAME_KEY   => $pixel_name,
            FacebookPluginConfig::FBL4B_BUSINESS_ID_KEY  => $business_id,
        );
        \update_option(
            FacebookPluginConfig::FBL4B_SETTINGS_KEY,
            $fbl4b_settings
        );
        \delete_transient(
            FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT
        );
        delete_metadata(
            'user',
            0,
            FacebookPluginConfig::ADMIN_IGNORE_CONNECTION_INVALID_NOTICE,
            '',
            true
        );

        return $this->handle_success_request( 'FBL4B settings saved' );
    }

    /**
     * Deletes all FBL4B settings.
     *
     * @return array Response data.
     */
    public function delete_fbl4b_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->handle_unauthorized_request();
        }
        check_admin_referer( 'delete_fbl4b_settings' );

        \delete_option( FacebookPluginConfig::FBL4B_SETTINGS_KEY );
        \delete_transient( FacebookPluginConfig::AAM_SETTINGS_KEY );
        \delete_user_meta(
            get_current_user_id(),
            FacebookPluginConfig::ADMIN_IGNORE_FBL4B_UPGRADE_NOTICE
        );

        return $this->handle_success_request( 'FBL4B settings deleted' );
    }

    /**
     * Clears the stored pixel ID and pixel name from FBL4B settings.
     * Keeps the access token and business ID intact so the user can
     * re-select a different pixel without re-authenticating.
     *
     * @return array Response data.
     */
    public function fbl4b_clear_pixel() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->handle_unauthorized_request();
        }
        check_admin_referer( 'fbl4b_clear_pixel' );

        $existing = \get_option(
            FacebookPluginConfig::FBL4B_SETTINGS_KEY,
            array()
        );
        if ( empty( $existing ) ) {
            return $this->handle_invalid_request();
        }

        unset( $existing[ FacebookPluginConfig::FBL4B_PIXEL_ID_KEY ] );
        unset( $existing[ FacebookPluginConfig::FBL4B_PIXEL_NAME_KEY ] );
        \update_option(
            FacebookPluginConfig::FBL4B_SETTINGS_KEY,
            $existing
        );
        \delete_transient( FacebookPluginConfig::AAM_SETTINGS_KEY );

        return $this->handle_success_request( 'Pixel cleared' );
    }

    /**
     * Proxies a Graph API call to validate the FBL4B access token.
     * Returns whether the token is valid and the client_business_id.
     */
    public function fbl4b_validate_token() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
            return;
        }
        check_admin_referer( 'fbl4b_validate_token' );

        $access_token = FacebookWordpressOptions::get_fbl4b_access_token();
        if ( empty( $access_token ) ) {
            wp_send_json_success(
                array(
                    'valid'              => false,
                    'client_business_id' => null,
                )
            );
            return;
        }

        $response = wp_remote_get(
            $this->get_graph_api_base_url() . '/me?fields=client_business_id',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                ),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_success(
                array(
                    'valid'              => false,
                    'client_business_id' => null,
                )
            );
            return;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code >= 400 ) {
            wp_send_json_success(
                array(
                    'valid'              => false,
                    'client_business_id' => null,
                )
            );
            return;
        }

        wp_send_json_success(
            array(
                'valid'              => ! empty( $body['client_business_id'] ),
                'client_business_id' => isset( $body['client_business_id'] )
                    ? $body['client_business_id'] : null,
            )
        );
    }

    /**
     * Proxies a Graph API call to fetch the client_business_id.
     * Token is read server-side from the database.
     */
    public function fbl4b_fetch_business_id() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
            return;
        }
        check_admin_referer( 'fbl4b_fetch_business_id' );

        $access_token = FacebookWordpressOptions::get_fbl4b_access_token();
        if ( empty( $access_token ) ) {
            wp_send_json_error( 'No access token stored', 400 );
            return;
        }

        $response = wp_remote_get(
            $this->get_graph_api_base_url() . '/me?fields=client_business_id',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                ),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
            return;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code >= 400 ) {
            $error_msg = isset( $body['error']['message'] )
                ? $body['error']['message'] : 'Graph API error';
            wp_send_json_error( $error_msg, $status_code );
            return;
        }

        wp_send_json_success( $body );
    }

    /**
     * Proxies Graph API calls to fetch owned + client pixels for a business.
     * Deduplicates by pixel ID, owned pixels first.
     */
    public function fbl4b_fetch_pixels() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
            return;
        }
        check_admin_referer( 'fbl4b_fetch_pixels' );

        $access_token = FacebookWordpressOptions::get_fbl4b_access_token();
        $business_id  = sanitize_text_field(
            isset( $_POST['businessId'] ) ?
            wp_unslash( $_POST['businessId'] ) : ''
        );

        if ( empty( $access_token ) || empty( $business_id ) ) {
            wp_send_json_error( 'Missing parameters', 400 );
            return;
        }

        $base_url = $this->get_graph_api_base_url();
        $headers  = array( 'Authorization' => 'Bearer ' . $access_token );

        $owned_url  = $base_url . '/' . $business_id
            . '/owned_pixels?fields=id,name&limit=100';
        $client_url = $base_url . '/' . $business_id
            . '/client_pixels?fields=id,name&limit=100';

        $owned_response  = wp_remote_get(
            $owned_url,
            array(
            'headers' => $headers,
            'timeout' => 15,
            )
        );
        $client_response = wp_remote_get(
            $client_url,
            array(
            'headers' => $headers,
            'timeout' => 15,
            )
        );

        $pixels   = array();
        $seen_ids = array();

        if ( ! is_wp_error( $owned_response )
            && 200 === wp_remote_retrieve_response_code( $owned_response ) ) {
            $owned_data = json_decode(
                wp_remote_retrieve_body( $owned_response ),
                true
            );
            if ( isset( $owned_data['data'] ) ) {
                foreach ( $owned_data['data'] as $pixel ) {
                    $pixels[]   = $pixel;
                    $seen_ids[] = $pixel['id'];
                }
            }
        }

        if ( ! is_wp_error( $client_response )
            && 200 === wp_remote_retrieve_response_code( $client_response ) ) {
            $client_data = json_decode(
                wp_remote_retrieve_body( $client_response ),
                true
            );
            if ( isset( $client_data['data'] ) ) {
                foreach ( $client_data['data'] as $pixel ) {
                    if ( ! in_array( $pixel['id'], $seen_ids, true ) ) {
                        $pixels[] = $pixel;
                    }
                }
            }
        }

        if ( empty( $pixels ) ) {
            wp_send_json_error(
                array(
                    'message' => 'No pixels found for this business.',
                    'code'    => 'no_pixels',
                )
            );
            return;
        }

        wp_send_json_success( array( 'data' => $pixels ) );
    }
}
