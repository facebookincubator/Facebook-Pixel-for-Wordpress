<?php
/**
 * Facebook Pixel Plugin FacebookWordpressWPForms class.
 *
 * This file contains the tracking integration for WPForms.
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
 * Tracking integration for the WPForms plugin.
 */
class FacebookWordpressWPForms extends TrackableLeadFormIntegrationBase {

    const PLUGIN_FILE      = 'wpforms-lite/wpforms.php';
    const INTEGRATION_NAME = 'wpforms-lite';

    const AJAX_PIXEL_CONTAINER = 'wp_form_pxl_container';
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
            'wpforms_process_before',
            array( $this, 'capture_submitted_form' ),
            20,
            2
        );

        $this->register_ajax_container( $this->get_listener_js() );
    }

    /**
     * Captures a submitted form and delivers the Lead event (AJAX submits via
     * the footer listener, non-AJAX via the footer flush).
     *
     * @param mixed ...$args The WPForms submit-hook arguments (entries, form
     *                       data).
     * @return void
     */
    public function capture_submitted_form( ...$args ) {
        $event = $this->generate_event( self::EVENT_NAME, $this->extract_lead_data( ...$args ) );

        // WPForms fires this hook for both AJAX and non-AJAX submits.
        $is_ajax = wp_doing_ajax();
        if ( $is_ajax ) {
            $this->deliver( $event, self::BROWSER_AJAX, self::SERVER_SYNC, array( $event ) );
        } else {
            $this->deliver( $event );
        }
    }

    /**
     * Injects the Lead pixel code into the WPForms AJAX response so the footer
     * listener can eval it.
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
            array( 'wpforms_ajax_submit_success_response', 'wpforms_ajax_submit_redirect' ),
            function ( $response ) use ( $pixel_code, $response_key ) {
                return $this->add_tracking_code_to_ajax_response( $response, $pixel_code, $response_key );
            }
        );
    }

    /**
     * Returns the WPForms listener JavaScript: on wpformsAjaxSubmitSuccess it
     * hands the response object to the shared fbHandleResponse() helper.
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
            if (window.jQuery) {
                window.jQuery(document).on('wpformsAjaxSubmitSuccess', function (event, data) {
                    if (data) {
                        fbHandleResponse(data.data);
                    }
                });
            }
        })();
        JS;
    }

    /**
     * Reads the form id from the plugin submit-hook arguments.
     *
     * @param mixed ...$args The WPForms submit-hook arguments.
     * @return mixed The form identifier.
     */
    protected function get_form_id( ...$args ) {
        $form_data = $args[1];
        return $form_data['id'];
    }

    /**
     * Yields the submitted form parameters as normalized rows.
     *
     * @param mixed ...$args The WPForms submit-hook arguments (entries, form
     *                       data).
     * @return \Generator|array Rows of ['name'=>, 'type'=>, 'value'=>, 'format'=>,
     *                          'scheme'=>], or an empty array when arguments are
     *                          missing.
     */
    protected function get_form_param_iterator( ...$args ) {
        if ( empty( $args ) || empty( $args[0] ) || empty( $args[1] ) ) {
            return array();
        }
        $entries   = $args[0];
        $form_data = $args[1];
        if ( ! isset( $entries['fields'] ) ) {
            return;
        } else {
            foreach ( $entries['fields'] as $field_id => $field_value ) {
                if ( ! isset( $form_data['fields'][ $field_id ] ) ) {
                    continue;
                }
                if ( empty( $field_value ) ) {
                    continue;
                }
                $label  = $this->get_if_isset( $form_data['fields'][ $field_id ], 'label' );
                $name   = empty( $label ) ? '' : strtolower( trim( $label ) );
                $type   = $this->get_if_isset( $form_data['fields'][ $field_id ], 'type' );
                $scheme = $this->get_if_isset( $form_data['fields'][ $field_id ], 'scheme' );
                $format = $this->get_if_isset( $form_data['fields'][ $field_id ], 'format' );
                yield self::get_iterator_yield_output( $name, $type, $field_value, 'format', $format, 'scheme', $scheme );
            }
        }
    }

    /**
     * Returns an array value by key when set, otherwise null.
     *
     * @param array  $input      The array to read from.
     * @param string $param_name The key to look up.
     * @return mixed The value when set, otherwise null.
     */
    private static function get_if_isset( $input, $param_name ) {
        if ( isset( $input[ $param_name ] ) ) {
            return $input[ $param_name ];
        }
        return null;
    }

    /**
     * Hard-coded extraction used when no mapping is configured for the form.
     *
     * @param iterable $form_param_iterator Rows of ['name'=>, 'type'=>, 'value'=>,
     *                                      'format'=>, 'scheme'=>].
     * @return array Normalized lead data keyed by Lead parameter name.
     */
    protected function extract_lead_data_fallback( $form_param_iterator ) {
        $result = array();
        foreach ( $form_param_iterator as $param ) {
            $name   = $param['name'];
            $type   = $param['type'];
            $value  = $param['value'];
            $format = $param['format'];
            $scheme = $param['scheme'];

            if ( 'name' === $type ) {
                if ( 'simple' === $format ) {
                    $temp                 = StringUtils::split_name( $value );
                    $result['first_name'] = $temp[0];
                    $result['last_name']  = $temp[1];
                } elseif ( 'first-last' === $format ) {
                    $result['first_name'] = $value['first'];
                    $result['last_name']  = $value['last'];
                }
            } elseif ( 'email' === $type ) {
                $result['email'] = $value;
            } elseif ( 'phone' === $type ) {
                if ( ! empty( $value ) ) {
                    $result['phone'] = $value;
                }
            } elseif ( 'address' === $type ) {
                if ( ! empty( $value ) ) {
                    $address_inputs = $this->get_address_param_breakdown( $value );
                    $result         = array_merge( $result, $address_inputs );
                    if ( ! isset( $result['country'] ) && ! empty( $scheme ) && 2 === strlen( $scheme ) ) {
                        $result['country'] = strtoupper( $scheme );
                    }
                }
            } elseif ( in_array( $name, array( 'city', 'town' ), true ) && ! empty( $value ) ) {
                $result['city'] = $value;
            } elseif ( in_array( $name, array( 'state', 'province', 'region', 'county' ), true ) && ! empty( $value ) ) {
                $result['state'] = $value;
            } elseif ( in_array( $name, array( 'zip', 'postal', 'postcode', 'zip code' ), true ) && ! empty( $value ) ) {
                $result['zip'] = $value;
            } elseif ( in_array( $name, array( 'country', 'country/region' ), true ) && ! empty( $value ) ) {
                $result['country'] = $value;
            }
            if ( ! empty( $name ) && in_array( $name, array( 'phone', 'tel', 'telephone', 'mobile' ), true ) ) {
                $result['phone'] = $value;
            }
        }
        return $result;
    }

    /**
     * Breaks an address field value into individual city/state/zip/country
     * lead parameters.
     *
     * @param array $address_field_value The address field value, keyed by
     *                                   address component.
     * @return array Normalized address lead data keyed by Lead parameter name.
     */
    private function get_address_param_breakdown( $address_field_value ) {
        $result = array();
        $fn     = function ( $input_param_name, $input, $result_param_name = null ) use ( &$result ) {
            if ( isset( $input[ $input_param_name ] ) ) {
                if ( empty( $result_param_name ) ) {
                    $result[ $input_param_name ] = $input[ $input_param_name ];
                } else {
                    $result[ $result_param_name ] = $input[ $input_param_name ];
                }
            }
        };
        $fn( 'city', $address_field_value );
        $fn( 'state', $address_field_value );
        $fn( 'country', $address_field_value );
        $fn( 'postal', $address_field_value, 'zip' );
        return $result;
    }
}
