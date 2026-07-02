<?php
/**
 * Facebook Pixel Plugin FacebookWordpressNinjaForms class.
 *
 * This file contains the main logic for FacebookWordpressNinjaForms.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressNinjaForms class.
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

namespace FacebookPixelPlugin\Integration;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\AjaxFilterDelivery;

/**
 * FacebookWordpressNinjaForms class.
 */
class FacebookWordpressNinjaForms extends FacebookWordpressIntegrationBase {
    const PLUGIN_FILE   = 'ninja-forms/ninja-forms.php';
    const TRACKING_NAME = 'ninja-forms';

    /**
     * Registers the Ninja Forms hooks.
     *
     * A Lead event is dispatched through Signals when a submission runs its
     * success-message action; the browser pixel is delivered into the
     * success-message AJAX response and a front-end listener evaluates it.
     *
     * @return void
     */
    public function inject_pixel_code() {
        $this->signals->on(
            'ninja_forms_submission_actions',
            'Lead',
            array( $this, 'read_form_data' ),
            new AjaxFilterDelivery(
                'ninja_forms_post_run_action_type_successmessage',
                'fb_pxl_code'
            ),
            self::TRACKING_NAME,
            10,
            3
        );
        add_action(
            'wp_footer',
            array( $this, 'inject_ajax_listener' ),
            9
        );
    }

    /**
     * Extracts the Lead event data from a Ninja Forms submission, or null when
     * the submission has no success-message action.
     *
     * @param array $actions    The form submission actions.
     * @param array $form_cache The form cache data (unused).
     * @param array $form_data  The submitted form data.
     * @return \FacebookPixelPlugin\Core\EventData|null
     */
    public function read_form_data(
        $actions,
        $form_cache = array(),
        $form_data = array()
    ) {
        if ( ! self::has_success_message( $actions ) || empty( $form_data ) ) {
            return null;
        }

        $name = self::getName( $form_data );

        return $this->event_builder->build(
            array(
                'first_name' => $name ? $name[0] : self::getFirstName( $form_data ),
                'last_name'  => $name ? $name[1] : self::getLastName( $form_data ),
                'email'      => self::getEmail( $form_data ),
                'phone'      => self::getPhone( $form_data ),
                'city'       => self::getCity( $form_data ),
                'zip'        => self::getZipCode( $form_data ),
                'state'      => self::getState( $form_data ),
                'country'    => self::getCountry( $form_data ),
                'gender'     => self::getGender( $form_data ),
            )
        );
    }

    /**
     * Whether the submission actions include a success-message action.
     *
     * @param array $actions The form submission actions.
     * @return bool
     */
    private static function has_success_message( $actions ) {
        if ( ! is_array( $actions ) ) {
            return false;
        }
        foreach ( $actions as $action ) {
            if ( isset( $action['settings']['type'] )
                && is_string( $action['settings']['type'] )
                && 'successmessage' === $action['settings']['type'] ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Outputs a listener that executes pixel code returned by Ninja Forms AJAX.
     *
     * @return void
     */
    public function inject_ajax_listener() {
        ?>
        <!-- Meta Pixel Event Code -->
        <script type='text/javascript'>
        (function () {
            function runPixelCode( response ) {
                if ( ! response || ! response.data || ! response.data.fb_pxl_code ) {
                    return;
                }

                try {
                    new Function( response.data.fb_pxl_code )();
                } catch ( e ) {
                    console && console.warn
                        && console.warn( 'Meta Pixel response parsing failed. Please check if your pixel is connected.', e );
                }
            }

            var radio = window.nfRadio
                || ( window.Backbone && window.Backbone.Radio );
            if ( radio && radio.channel ) {
                radio.channel( 'forms' ).on( 'submit:response', runPixelCode );
            }

            if ( window.jQuery ) {
                window.jQuery( document ).on(
                    'nfFormSubmitResponse',
                    function ( event, data ) {
                        if ( data && data.response ) {
                            runPixelCode( data.response );
                        }
                    }
                );
            }
        })();
        </script>
        <!-- End Meta Pixel Event Code -->
        <?php
    }

    /**
     * Retrieves the user's email address from the form data.
     *
     * @param array $form_data The form data as an associative array.
     * @return string The user's email address.
     */
    private static function getEmail( $form_data ) {
        return self::getField( $form_data, 'email' );
    }

    /**
     * Retrieves the user's full name from the form data and splits it into
     * first name and last name.
     *
     * @param array $form_data The form data as an associative array.
     * @return array|null An array containing first name and last name, or null
     *                    if no name field is found.
     */
    private static function getName( $form_data ) {
        $name = self::getField( $form_data, 'name' );
        if ( $name ) {
            return ServerEventFactory::split_name( $name );
        }
            return null;
    }

    /**
     * Retrieves the user's first name from the form data.
     *
     * @param array $form_data The form data as an associative array.
     * @return string The user's first name.
     */
    private static function getFirstName( $form_data ) {
        return self::getField( $form_data, 'firstname' );
    }

    /**
     * Retrieves the user's last name from the form data.
     *
     * @param array $form_data The form data as an associative array.
     * @return string The user's last name.
     */
    private static function getLastName( $form_data ) {
        return self::getField( $form_data, 'lastname' );
    }

    /**
     * Retrieves the user's phone number from the form data.
     *
     * @param array $form_data The form data as an associative array.
     * @return string The user's phone number.
     */
    private static function getPhone( $form_data ) {
        return self::getField( $form_data, 'phone' );
    }

    /**
     * Retrieves the user's city from the form data.
     *
     * @param array $form_data The form data as an associative array.
     * @return string The user's city.
     */
    private static function getCity( $form_data ) {
        return self::getField( $form_data, 'city' );
    }

    /**
     * Retrieves the user's zip code from the form data.
     *
     * @param array $form_data The form data as an associative array.
     * @return string The user's zip code.
     */
    private static function getZipCode( $form_data ) {
        return self::getField( $form_data, 'zip' );
    }

    /**
     * Retrieves the user's state from the form data.
     *
     * @param array $form_data The form data as an associative array.
     * @return string The user's state.
     */
    private static function getState( $form_data ) {
        return self::getField( $form_data, 'liststate' );
    }

    /**
     * Retrieves the user's country from the form data.
     *
     * @param array $form_data The form data as an associative array.
     * @return string The user's country.
     */
    private static function getCountry( $form_data ) {
        return self::getField( $form_data, 'listcountry' );
    }

    /**
     * Retrieves the user's gender from the form data.
     *
     * @param array $form_data The form data as an associative array.
     * @return string The user's gender.
     */
    private static function getGender( $form_data ) {
        return self::getField( $form_data, 'gender' );
    }

    /**
     * Checks if a given string starts with a given prefix.
     *
     * @param string $text The string to check.
     * @param string $prefix The prefix to check for.
     * @return boolean True if the string starts with the
     * prefix, false otherwise.
     */
    private static function hasPrefix( $text, $prefix ) {
        $len = strlen( $prefix );
        return substr( $text, 0, $len ) === $prefix;
    }

    /**
     * Retrieves the value of a field from the form data by its key.
     *
     * The key is searched for in the form data as a prefix of the field's key.
     * If a matching field is found, its value is returned.
     * If not, null is returned.
     *
     * @param array  $form_data The form data as an associative array.
     * @param string $key The key of the field to retrieve the value for.
     * @return string|null The value of the field, or null
     * if no matching field is found.
     */
    private static function getField( $form_data, $key ) {
        if ( empty( $form_data['fields'] ) ) {
            return null;
        }

        foreach ( $form_data['fields'] as $field ) {
            if ( self::hasPrefix( $field['key'], $key ) ) {
                return $field['value'];
            }
        }

        return null;
    }
}
