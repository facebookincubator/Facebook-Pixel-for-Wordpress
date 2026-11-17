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
     * Each published form's cached settings and fields, keyed by form id, or
     * null until read (see get_form_caches()).
     *
     * @var array<string,array>|null
     */
    private $form_caches = null;

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
     * A single Ninja Forms form can be configured with more than one
     * successmessage action; they all belong to the same submission, so only
     * the first one delivers an event.
     *
     * @param mixed ...$args The Ninja Forms submit-hook arguments
     *                       (actions, form cache, form data).
     * @return array The form actions, passed through unchanged.
     */
    public function capture_submitted_form( ...$args ) {
        $actions = $args[0];
        if ( $this->is_form_rejected( ...$args ) ) {
            return $actions;
        }

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

            // One Lead per submission, however many successmessage actions the
            // form is configured with.
            break;
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
     * @return \Generator Rows of ['name'=>, 'value'=>, 'id'=>].
     */
    protected function get_form_param_iterator( ...$args ) {
        $form_data = $args[2];
        if ( ! isset( $form_data['fields'] ) ) {
            return;
        } else {
            foreach ( $form_data['fields'] as $field ) {
                if ( isset( $field['key'] ) && isset( $field['value'] ) ) {
                    // The key is regenerated from the label when a field is
                    // renamed, so the row also carries the numeric field id the
                    // field mapping screen lists (see get_mapping_key()).
                    $id = isset( $field['id'] ) ? $field['id'] : '';
                    yield self::get_iterator_yield_output( $field['key'], '', $field['value'], 'id', $id );
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

    /**
     * This integration supports form field mapping.
     *
     * @return bool
     */
    public function supports_field_mapping() {
        return true;
    }

    /**
     * Whether the Ninja Forms API is loaded.
     *
     * @return bool
     */
    protected function is_form_plugin_loaded() {
        return function_exists( 'Ninja_Forms' );
    }

    /**
     * Ninja Forms fires these when the form builder publishes a form and when a form is deleted.
     *
     * @return string[]
     */
    public function get_form_change_hooks() {
        return array( 'ninja_forms_save_form', 'ninja_forms_after_form_delete' );
    }

    /**
     * Human-readable integration name for the field mapping screen.
     *
     * @return string
     */
    public function get_integration_label() {
        return 'Ninja Forms';
    }

    /**
     * The URL of the Ninja Forms form builder for a form.
     *
     * @param string $form_id The native form id.
     * @return string
     */
    public function get_form_edit_url( $form_id ) {
        return admin_url( 'admin.php?page=ninja-forms&form_id=' . absint( $form_id ) );
    }

    /**
     * Each published form's settings and fields as Ninja Forms caches them
     * (the copy it renders forms from), keyed by form id, read with a single
     * query per request. Listing forms through the Ninja Forms API instead
     * costs several queries per form (its title, and its fields twice), which
     * made the field mapping table slow on sites with many forms. Forms
     * missing from the cache fall back to the API.
     *
     * @return array<string,array> form_id => ['settings' => [...], 'fields' => [...]].
     */
    private function get_form_caches() {
        if ( null !== $this->form_caches ) {
            return $this->form_caches;
        }
        $this->form_caches = array();
        global $wpdb;
        if ( ! isset( $wpdb ) || ! class_exists( 'WPN_Helper' ) ) {
            return $this->form_caches;
        }
        // The table is missing on installs that predate it; that is not an error.
        $suppressed = $wpdb->suppress_errors( true );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One read of Ninja Forms' own cache table, kept for the request; no user input.
        $rows = $wpdb->get_results( "SELECT id, cache FROM `{$wpdb->prefix}nf3_upgrades`", ARRAY_A );
        $wpdb->suppress_errors( $suppressed );
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $cache = \WPN_Helper::maybe_unserialize( $row['cache'] );
            if ( is_array( $cache ) && isset( $cache['fields'] ) && is_array( $cache['fields'] ) ) {
                $this->form_caches[ (string) $row['id'] ] = $cache;
            }
        }
        return $this->form_caches;
    }

    /**
     * Returns all Ninja Forms forms as id + title, the title from the form
     * cache when it has the form.
     *
     * @return iterable
     */
    protected function fetch_forms() {
        $forms  = Ninja_Forms()->form()->get_forms();
        $caches = $this->get_form_caches();
        $result = array();
        foreach ( is_array( $forms ) ? $forms : array() as $form ) {
            $id       = (string) $form->get_id();
            $result[] = array(
                'id'    => $id,
                'title' => isset( $caches[ $id ]['settings']['title'] )
                    ? $caches[ $id ]['settings']['title']
                    : $form->get_setting( 'title' ),
            );
        }
        return $result;
    }

    /**
     * Forms are already id + title (see fetch_forms()).
     *
     * @param mixed $raw_form A form from fetch_forms().
     * @return array
     */
    protected function map_form( $raw_form ) {
        return $raw_form;
    }

    /**
     * Returns a Ninja Forms form's fields as ['id' =>, 'settings' => [...]],
     * from the form cache when it has the form.
     *
     * @param string $form_id The form id.
     * @return iterable
     */
    protected function fetch_form_fields( $form_id ) {
        $caches = $this->get_form_caches();
        if ( isset( $caches[ (string) $form_id ] ) ) {
            return $caches[ (string) $form_id ]['fields'];
        }
        $fields = Ninja_Forms()->form( $form_id )->get_fields();
        $result = array();
        foreach ( is_array( $fields ) ? $fields : array() as $field ) {
            $result[] = array(
                'id'       => $field->get_id(),
                'settings' => $field->get_settings(),
            );
        }
        return $result;
    }

    /**
     * Reads a setting of a field from fetch_form_fields().
     *
     * @param array  $raw_field The field.
     * @param string $name      The setting name.
     * @return string
     */
    private static function get_field_setting( $raw_field, $name ) {
        return isset( $raw_field['settings'][ $name ] ) && is_scalar( $raw_field['settings'][ $name ] )
            ? (string) $raw_field['settings'][ $name ]
            : '';
    }

    /**
     * Maps a Ninja Forms field to a field. The id is the numeric field id,
     * which get_form_param_iterator() adds to each row: unlike the field key,
     * it does not change when the field is renamed.
     *
     * @param mixed $raw_field A field from fetch_form_fields().
     * @return array|null
     */
    protected function map_field( $raw_field ) {
        $id   = isset( $raw_field['id'] ) ? (string) $raw_field['id'] : '';
        $type = self::get_field_setting( $raw_field, 'type' );
        if ( '' === $id || in_array( $type, array( 'submit', 'hr', 'html', 'recaptcha', 'spam' ), true ) ) {
            return null;
        }
        return array(
            'id'    => $id,
            'label' => self::get_field_setting( $raw_field, 'label' ),
            'type'  => $type,
        );
    }

    /**
     * Suggests a target for a Ninja Forms field from its key prefix, following
     * extract_lead_data_fallback().
     *
     * @param mixed $raw_field A field from fetch_form_fields().
     * @return string|null
     */
    protected function suggest_field_target( $raw_field ) {
        $prefixes = array(
            'email'       => 'email',
            'name'        => 'full_name',
            'firstname'   => 'first_name',
            'lastname'    => 'last_name',
            'phone'       => 'phone',
            'city'        => 'city',
            'zip'         => 'zip',
            'liststate'   => 'state',
            'listcountry' => 'country',
            'gender'      => 'gender',
        );
        $key      = self::get_field_setting( $raw_field, 'key' );
        foreach ( $prefixes as $prefix => $target ) {
            if ( StringUtils::starts_with( $key, $prefix ) ) {
                return $target;
            }
        }
        return null;
    }
}
