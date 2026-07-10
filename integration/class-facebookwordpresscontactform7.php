<?php
/**
 * Facebook Pixel Plugin FacebookWordpressContactForm7 class.
 *
 * This file contains the lead-tracking integration for Contact Form 7.
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
 * Lead-tracking integration for the Contact Form 7 plugin.
 */
class FacebookWordpressContactForm7 extends TrackableLeadFormIntegrationBase {

    const PLUGIN_FILE      = 'contact-form-7/wp-contact-form-7.php';
    const INTEGRATION_NAME = 'contact-form-7';

    const AJAX_PIXEL_CONTAINER = 'contact_form_7_pxl_container';

    /**
     * Initialize the integration.
     *
     * @return void
     */
    protected function set_up_tracking() {
        // TODO: Make it configurable so internal users can choose to track/not track their actions.
        if ( FacebookPluginUtils::is_internal_user() ) {
            return;
        }
        add_action( 'wpcf7_submit', array( $this, 'capture_submitted_form' ), 10, 2 );

        $this->register_ajax_container( $this->get_listener_js() );
    }

    /**
     * Captures a successful Contact Form 7 submission and delivers a Lead event.
     *
     * @param \WPCF7_ContactForm $form   The submitted contact form.
     * @param array              $result The submission result data.
     * @return void
     */
    public function capture_submitted_form( $form, $result ) {
        $submit_failed = 'mail_sent' !== $result['status'];
        if ( $submit_failed ) {
            return;
        }

        // A submission that mail_sent should still be tracked even if reading the
        // form fields fails; fall back to an empty payload so the Lead fires.
        try {
            $lead_data = $this->extract_lead_data( $form );
        } catch ( \Exception $e ) {
            $lead_data = array();
        }

        $event = $this->generate_event( static::EVENT_NAME, $lead_data );

        // Contact Form 7 submits over AJAX, so the browser event rides the response.
        $is_ajax = wp_doing_ajax();
        if ( $is_ajax ) {
            $this->deliver( $event, self::BROWSER_AJAX, self::SERVER_SYNC, array( $event ) );
        } else {
            $this->deliver( $event );
        }
    }

    /**
     * Injects the Lead pixel code into the Contact Form 7 AJAX feedback response
     * so the footer listener can eval it.
     *
     * @param array $args Arguments from deliver(); $args[0] is the Lead event.
     * @throws \Exception When the request is not AJAX or no event is provided.
     * @return void
     */
    #[Override]
    protected function deliver_ajax_browser_event( $args ) {
        if ( ! wp_doing_ajax() ) {
            throw new \Exception( 'This request is not AJAX.' );
        }
        if ( empty( $args ) ) {
            throw new \Exception( '$args cannot be empty.' );
        }
        $event        = $args[0];
        $pixel_code   = $this->signals->pixel->generate_script_for_event( $event, false );
        $response_key = self::AJAX_PIXEL_CONTAINER;
        WordPressUtils::register_filter_hooks(
            array( 'wpcf7_feedback_response' ),
            function ( $response ) use ( $pixel_code, $response_key ) {
                return $this->add_tracking_code_to_ajax_response( $response, $pixel_code, $response_key );
            }
        );
    }

    /**
     * Returns the Contact Form 7 listener JavaScript: on wpcf7mailsent it hands
     * the API response object to the shared fbHandleResponse() helper.
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
            document.addEventListener('wpcf7mailsent', function (event) {
                if (event.detail) {
                    fbHandleResponse(event.detail.apiResponse);
                }
            }, false);
        })();
        JS;
    }

    /**
     * Reads the form id from the plugin submit-hook arguments.
     *
     * @param mixed ...$args The plugin submit-hook arguments.
     * @return mixed The Contact Form 7 form identifier.
     */
    protected function get_form_id( ...$args ) {
        $form = $args[0];
        return $form->id();
    }

    /**
     * Yields the submitted form fields as normalized parameter rows.
     *
     * @param mixed ...$args The plugin submit-hook arguments.
     * @return \Generator Normalized form-parameter rows.
     */
    protected function get_form_param_iterator( ...$args ) {
        $form = $args[0];
        foreach ( $form->scan_form_tags() as $tag ) {
            $tag_name = $tag->name;
            $value    = WordPressUtils::get_from_post( $tag->name );

            if ( empty( $value ) ) {
                continue;
            }
            yield self::get_iterator_yield_output(
                $tag_name,
                $tag->basetype,
                $value
            );
        }
    }

    /**
     * Hard-coded extraction of Lead data used when no mapping is configured.
     *
     * @param iterable $form_param_iterator Normalized form-parameter rows.
     * @return array Normalized lead data keyed by Lead parameter name.
     */
    protected function extract_lead_data_fallback( $form_param_iterator ) {
        $result = array();
        foreach ( $form_param_iterator as $form_param ) {
            if ( empty( $form_param ) ) {
                continue;
            }
            $type  = $form_param['type'];
            $value = $form_param['value'];

            if ( 'text' === $type ) {
                if ( strpos( strtolower( $form_param['name'] ), 'name' ) !== false ) {
                    $name                 = StringUtils::split_name( $value );
                    $result['first_name'] = $name[0];
                    $result['last_name']  = $name[1];
                }
            } elseif ( 'tel' === $type ) {
                $result['phone'] = $value;
            } elseif ( 'email' === $type ) {
                $result['email'] = $value;
            }
        }
        return $result;
    }
}
