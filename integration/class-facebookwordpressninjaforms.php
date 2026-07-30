<?php
/**
 * Facebook Pixel Plugin FacebookWordpressNinjaForms class.
 *
 * This file contains the tracking integration for Ninja Forms.
 *
 * @package FacebookPixelPlugin
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

namespace FacebookPixelPlugin\Integration;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Utils\StringUtils;
use FacebookPixelPlugin\Utils\WordPressUtils;
use Override;

/**
 * Tracking integration for the Ninja Forms plugin.
 */
class FacebookWordpressNinjaForms extends TrackableLeadFormIntegrationBase {

    const PLUGIN_FILE      = 'ninja-forms/ninja-forms.php';
    const INTEGRATION_NAME = 'ninja-forms';

    const AJAX_PIXEL_CONTAINER = 'ninja_forms_pxl_container';

    /**
     * Initialize the integration.
     *
     * @return void
     */
    protected function set_up_tracking() {
        if ( FacebookPluginUtils::is_internal_user() ) {
            return;
        }
        add_action(
            'ninja_forms_submission_actions',
            array( $this, 'capture_submitted_form' ),
            10,
            3
        );

        $this->register_ajax_container( $this->get_listener_js() );
    }

    /**
     * Captures a submitted form on success and delivers the Lead event, then
     * returns the form actions unchanged.
     *
     * @param mixed ...$args The Ninja Forms submit-hook arguments
     *                       (actions, form cache, form data).
     * @return array The form actions, passed through unchanged.
     */
    public function capture_submitted_form( ...$args ) {
        $actions = $args[0];

        foreach ( $actions as $action ) {
            if ( ! isset( $action['settings'] ) || ! isset( $action['settings']['type'] ) ) {
                continue;
            }

            $type = $action['settings']['type'];
            if ( ! is_string( $type ) ) {
                continue;
            }

            if ( 'successmessage' !== $type ) {
                continue;
            }

            // Ninja Forms submits over AJAX, so the browser event rides the response.
            $event = $this->generate_event( static::EVENT_NAME, $this->extract_lead_data( ...$args ) );
            $this->deliver( $event, self::BROWSER_AJAX, self::SERVER_SYNC, array( $event ) );
        }

        return $actions;
    }

    /**
     * Injects the Lead pixel code into the Ninja Forms AJAX response so the
     * footer listener can eval it.
     *
     * @param array $args Arguments from deliver(); $args[0] is the Lead event.
     * @throws \Exception When the request is not AJAX or no event is provided.
     * @return void
     */
    #[Override]
    protected function deliver_ajax_browser_event( $args ) {
        $is_ajax = wp_doing_ajax();
        if ( ! $is_ajax ) {
            throw new \Exception( 'This request is not AJAX.' );
        }
        if ( empty( $args ) ) {
            throw new \Exception( '$args cannot be empty.' );
        }
        $event        = $args[0];
        $pixel_code   = $this->generate_pixel_script_for_ajax( $event, false );
        $response_key = self::AJAX_PIXEL_CONTAINER;
        WordPressUtils::register_filter_hooks(
            array( 'ninja_forms_post_run_action_type_successmessage' ),
            function ( $response ) use ( $pixel_code, $response_key ) {
                return $this->add_tracking_code_to_ajax_response( $response, $pixel_code, $response_key );
            }
        );
    }

    /**
     * Returns the Ninja Forms listener JavaScript. Ninja Forms exposes its
     * submit response both through a Backbone radio channel and a jQuery event;
     * both paths hand the response object to the shared fbHandleResponse()
     * helper.
     *
     * @return string The listener JavaScript.
     */
    private function get_listener_js() {
        $response_key = self::AJAX_PIXEL_CONTAINER;
        return <<<JS
        (function () {
            function fbHandleResponse(response) {
                var code = response && response['{$response_key}'];
                if (!code) {
                    return;
                }
                try {
                    new Function(code)();
                } catch (e) {
                    if (window.console && console.warn) {
                        console.warn('Meta Pixel eval failed. Please check if your pixel is connected.', e);
                    }
                }
            }
            var radio = window.nfRadio || (window.Backbone && window.Backbone.Radio);
            if (radio && radio.channel) {
                radio.channel('forms').on('submit:response', function (response) {
                    fbHandleResponse(response && response.data);
                });
            }
            if (window.jQuery) {
                window.jQuery(document).on('nfFormSubmitResponse', function (event, data) {
                    fbHandleResponse(data && data.response && data.response.data);
                });
            }
        })();
        JS;
    }

    /**
     * Reads the form id from the plugin submit-hook arguments.
     *
     * @param mixed ...$args The Ninja Forms submit-hook arguments.
     * @return mixed The form identifier.
     */
    protected function get_form_id( ...$args ) {
        $form_data = $args[2];
        return $form_data['id'];
    }

    /**
     * Yields the submitted form parameters as normalized rows.
     *
     * @param mixed ...$args The Ninja Forms submit-hook arguments.
     * @return \Generator Rows of ['name'=>, 'value'=>].
     */
    protected function get_form_param_iterator( ...$args ) {
        $form_data = $args[2];
        if ( ! isset( $form_data['fields'] ) ) {
            return;
        } else {
            foreach ( $form_data['fields'] as $field ) {
                if ( isset( $field['key'] ) && isset( $field['value'] ) ) {
                    yield self::get_iterator_yield_output( $field['key'], '', $field['value'] );
                }
            }
        }
    }

    /**
     * Hard-coded extraction used when no mapping is configured for the form.
     *
     * @param iterable $form_param_iterator Rows of ['name'=>, 'value'=>].
     * @return array Normalized lead data keyed by Lead parameter name.
     */
    protected function extract_lead_data_fallback( $form_param_iterator ) {
        $result = array();
        foreach ( $form_param_iterator as $form_data ) {
            $name  = $form_data['name'];
            $value = $form_data['value'];
            if ( StringUtils::starts_with( $name, 'email' ) ) {
                $result['email'] = $value;
            } elseif ( StringUtils::starts_with( $name, 'name' ) ) {
                $val = StringUtils::split_name( $value );
                if ( empty( $val ) ) {
                    continue;
                }
                $result['first_name'] = $val[0];
                $result['last_name']  = $val[1];
            } elseif ( StringUtils::starts_with( $name, 'firstname' ) ) {
                $result['first_name'] = $value;
            } elseif ( StringUtils::starts_with( $name, 'lastname' ) ) {
                $result['last_name'] = $value;
            } elseif ( StringUtils::starts_with( $name, 'phone' ) ) {
                $result['phone'] = $value;
            } elseif ( StringUtils::starts_with( $name, 'city' ) ) {
                $result['city'] = $value;
            } elseif ( StringUtils::starts_with( $name, 'zip' ) ) {
                $result['zip'] = $value;
            } elseif ( StringUtils::starts_with( $name, 'liststate' ) ) {
                $result['state'] = $value;
            } elseif ( StringUtils::starts_with( $name, 'listcountry' ) ) {
                $result['country'] = $value;
            } elseif ( StringUtils::starts_with( $name, 'gender' ) ) {
                $result['gender'] = $value;
            }
        }
        return $result;
    }
}
