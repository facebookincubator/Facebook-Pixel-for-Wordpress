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

use FacebookPixelPlugin\Core\FacebookPixel;
use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\FacebookWordPressOptions;
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\PixelRenderer;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\UserData;

/**
 * FacebookWordpressNinjaForms class.
 */
class FacebookWordpressNinjaForms extends FacebookWordpressIntegrationBase {
    const PLUGIN_FILE   = 'ninja-forms/ninja-forms.php';
    const TRACKING_NAME = 'ninja-forms';

    /**
     * Injects Facebook Pixel code for Ninja Forms.
     *
     * This method hooks into the 'ninja_forms_submission_actions' action,
     * which is triggered during the form submission process in Ninja Forms.
     * It adds the 'injectLeadEvent' method to handle form submission events,
     * allowing the tracking of lead events with Facebook Pixel.
     *
     * @return void
     */
    public static function inject_pixel_code() {
        add_action(
            'ninja_forms_submission_actions',
            array( __CLASS__, 'injectLeadEvent' ),
            10,
            3
        );
        add_filter(
            'ninja_forms_post_run_action_type_successmessage',
            array( __CLASS__, 'injectLeadEventResponse' )
        );
        add_action(
            'wp_footer',
            array( __CLASS__, 'injectAjaxListener' ),
            9
        );
    }

    /**
     * Injects lead event code into the form submission process of Ninja Forms.
     *
     * This method hooks into the 'ninja_forms_submission_actions' action,
     * which is triggered during the form submission process in Ninja Forms.
     * It generates a lead event for successful submissions so the event can be
     * added to the AJAX response later in the request lifecycle.
     *
     * @param array $actions An array of form submission actions.
     * @param array $form_cache An array of form cache data.
     * @param array $form_data An array of form data.
     *
     * @return array An array of form submission actions with the injected code.
     */
    public static function injectLeadEvent(
        $actions,
        $form_cache,
        $form_data
    ) {
        if ( FacebookPluginUtils::is_internal_user() ) {
            return $actions;
        }

        foreach ( $actions as $action ) {
            if ( ! isset( $action['settings'] ) ||
            ! isset( $action['settings']['type'] ) ) {
                continue;
            }

            $type = $action['settings']['type'];
            if ( ! is_string( $type ) ) {
                continue;
            }

            if ( 'successmessage' === $type ) {
                $event = ServerEventFactory::safe_create_event(
                    'Lead',
                    array( __CLASS__, 'readFormData' ),
                    array( $form_data ),
                    self::TRACKING_NAME,
                    true
                );
                FacebookServerSideEvent::get_instance()->track( $event );
                break;
            }
        }

        return $actions;
    }

    /**
     * Adds raw pixel calls to the Ninja Forms AJAX response.
     *
     * @param array $data Submission response data.
     * @return array
     */
    public static function injectLeadEventResponse( $data ) {
        if ( FacebookPluginUtils::is_internal_user()
            || isset( $data['fb_pxl_code'] ) ) {
            return $data;
        }

        $events = FacebookServerSideEvent::get_instance()->get_tracked_events();
        if ( empty( $events ) ) {
            return $data;
        }

        $data['fb_pxl_code'] = PixelRenderer::render(
            $events,
            self::TRACKING_NAME,
            false
        );

        return $data;
    }

    /**
     * Outputs a listener that executes pixel code returned by Ninja Forms AJAX.
     *
     * @return void
     */
    public static function injectAjaxListener() {
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
     * Reads form data from the $_POST global array.
     *
     * This function extracts user-related data such as email,
     * first name, last name,
     * phone number, and address details from the $_POST array,
     * commonly used in form
     * submissions. The extracted data includes:
     * - 'email': The user's email address.
     * - 'first_name': The user's first name.
     * - 'last_name': The user's last name.
     * - 'phone': The user's phone number.
     * - 'city', 'state', 'zip', 'country': Address
     * details, where the country must
     *   be specified using a 2-letter code.
     *
     * The function returns an associative array containing the extracted data.
     *
     * @param array $form_data The form data as an associative array.
     * @return array An associative array of form data.
     */
    public static function readFormData( $form_data ) {
        if ( empty( $form_data ) ) {
            return array();
        }

            $event_data = array();
            $name       = self::getName( $form_data );
        if ( $name ) {
            $event_data['first_name'] = $name[0];
            $event_data['last_name']  = $name[1];
        } else {
            $event_data['first_name'] = self::getFirstName( $form_data );
            $event_data['last_name']  = self::getLastName( $form_data );
        }
        $event_data['email']   = self::getEmail( $form_data );
        $event_data['phone']   = self::getPhone( $form_data );
        $event_data['city']    = self::getCity( $form_data );
        $event_data['zip']     = self::getZipCode( $form_data );
        $event_data['state']   = self::getState( $form_data );
        $event_data['country'] = self::getCountry( $form_data );
        $event_data['gender']  = self::getGender( $form_data );

        return $event_data;
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
