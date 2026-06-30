<?php
/**
 * Facebook Pixel Plugin FacebookWordpressContactForm7 class.
 *
 * This file contains the main logic for FacebookWordpressContactForm7.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressContactForm7 class.
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

use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\FacebookWordPressOptions;
use FacebookPixelPlugin\Core\FormFieldMapper;
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\PixelRenderer;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\UserData;

/**
 * FacebookWordpressContactForm7 class.
 */
class FacebookWordpressContactForm7 extends FacebookWordpressFormIntegrationBase {
    const PLUGIN_FILE   = 'contact-form-7/wp-contact-form-7.php';
    const TRACKING_NAME = 'contact-form-7';

    /**
     * Whether Contact Form 7 is active.
     *
     * @return bool
     */
    public static function is_available() {
        return class_exists( '\WPCF7_ContactForm' );
    }

    /**
     * Human-readable label for the mapping UI.
     *
     * @return string
     */
    public static function get_integration_label() {
        return 'Contact Form 7';
    }

    /**
     * Returns all Contact Form 7 form objects.
     *
     * @return iterable
     */
    protected static function fetch_forms() {
        return \WPCF7_ContactForm::find();
    }

    /**
     * Maps a Contact Form 7 form object to id + title.
     *
     * @param mixed $raw_form A WPCF7_ContactForm instance.
     * @return array<string,mixed>
     */
    protected static function map_form( $raw_form ) {
        return array(
            'id'    => $raw_form->id(),
            'title' => $raw_form->title(),
        );
    }

    /**
     * Returns the form tags for a single Contact Form 7 form.
     *
     * @param string $form_id The Contact Form 7 form id.
     * @return iterable
     */
    protected static function fetch_form_fields( $form_id ) {
        $form = \WPCF7_ContactForm::get_instance( $form_id );
        return empty( $form ) ? array() : $form->scan_form_tags();
    }

    /**
     * Maps a Contact Form 7 tag to a field, skipping submit/unnamed tags.
     *
     * The field id is the tag name, the same key used to read the submitted
     * value from $_POST at event time.
     *
     * @param mixed $raw_field A Contact Form 7 form tag.
     * @return array<string,mixed>|null
     */
    protected static function map_field( $raw_field ) {
        $name     = isset( $raw_field->name ) ? (string) $raw_field->name : '';
        $basetype = isset( $raw_field->basetype )
            ? (string) $raw_field->basetype : '';
        if ( '' === $name
            || in_array( $basetype, array( 'submit', '' ), true ) ) {
            return null;
        }
        return array(
            'id'    => $name,
            'label' => $name,
            'type'  => $basetype,
        );
    }

    /**
     * Add hooks to inject the Contact Form 7 tracking code.
     *
     * Adds the following hooks:
     *  - wpcf7_submit: Triggers a server-side event when the form is submitted.
     *  - wp_footer: Injects the mail sent listener.
     */
    public static function inject_pixel_code() {
        add_action(
            'wpcf7_submit',
            array( __CLASS__, 'trackServerEvent' ),
            10,
            2
        );
        add_action(
            'wp_footer',
            array( __CLASS__, 'injectMailSentListener' ),
            10,
            2
        );
    }

    /**
     * Injects a JavaScript listener for the 'wpcf7mailsent' event,
     * which is triggered when a form is submitted.
     *
     * The listener executes the Pixel code sent in the response
     * via the 'fb_pxl_code' key.
     *
     * @return void
     */
    public static function injectMailSentListener() {
        ob_start();
    ?>
    <!-- Meta Pixel Event Code -->
    <script type='text/javascript'>
        document.addEventListener( 'wpcf7mailsent', function( event ) {
        if( "fb_pxl_code" in event.detail.apiResponse){
            eval(event.detail.apiResponse.fb_pxl_code);
        }
        }, false );
    </script>
    <!-- End Meta Pixel Event Code -->
        <?php
        $listener_code = ob_get_clean();
        echo $listener_code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Triggers a server-side event when a form is submitted.
     *
     * If the user is an internal user or the form submission failed,
     * the event is not tracked.
     *
     * @param array $form The form object.
     * @param array $result The form submission result.
     *
     * @return array The submission result.
     */
    public static function trackServerEvent( $form, $result ) {
        $is_internal_user = FacebookPluginUtils::is_internal_user();
        $submit_failed    = 'mail_sent' !== $result['status'];
        if ( $is_internal_user || $submit_failed ) {
            return $result;
        }

        $server_event = ServerEventFactory::safe_create_event(
            'Lead',
            array( __CLASS__, 'readFormData' ),
            array( $form ),
            self::TRACKING_NAME,
            true
        );
        FacebookServerSideEvent::get_instance()->track( $server_event );

        add_action(
            'wpcf7_feedback_response',
            array( __CLASS__, 'injectLeadEvent' ),
            20,
            2
        );

        return $result;
    }

    /**
     * Injects the Pixel code into the Contact Form 7 response.
     *
     * Hooks into the `wpcf7_feedback_response` action and checks if the form
     * is submitted successfully and if the user is not an internal user.
     * If conditions are met, it renders the Pixel code using the `PixelRenderer` class
     * and appends the code to the form response.
     *
     * @param array $response The Contact Form 7 response.
     * @param array $result   The form data.
     *
     * @return array The modified Contact Form 7 response.
     */
    public static function injectLeadEvent( $response, $result ) {
        if ( FacebookPluginUtils::is_internal_user() ) {
            return $response;
        }

            $events = FacebookServerSideEvent::get_instance()->get_tracked_events();
        if ( count( $events ) === 0 ) {
            return $response;
        }
        $event_id  = $events[0]->getEventId();
        $fbq_calls = PixelRenderer::render(
            $events,
            self::TRACKING_NAME,
            false
        );
        $code      = sprintf(
            "
    if( typeof window.pixelLastGeneratedLeadEvent === 'undefined'
    || window.pixelLastGeneratedLeadEvent != '%s' ){
    window.pixelLastGeneratedLeadEvent = '%s';
    %s
    }
        ",
            $event_id,
            $event_id,
            $fbq_calls
        );

        $response['fb_pxl_code'] = $code;
        return $response;
    }

    /**
     * Reads the form data from the Contact Form 7 submission.
     *
     * @param object $form The Contact Form 7 form object.
     *
     * @return array The form data in the format expected
     * by the `FacebookServerSideEvent` class.
     */
    public static function readFormData( $form ) {
        if ( empty( $form ) ) {
            return array();
        }

        $form_tags = $form->scan_form_tags();
        $name      = self::getName( $form_tags );

        $data = array(
            'email'      => self::getEmail( $form_tags ),
            'first_name' => $name[0],
            'last_name'  => $name[1],
            'phone'      => self::getPhone( $form_tags ),
        );

        $form_id = method_exists( $form, 'id' ) ? (string) $form->id() : '';

        return self::applyFieldMapping( $form_id, $data );
    }

    /**
     * Merges any saved field mapping for the form over the heuristic data.
     *
     * Mapped values (read from $_POST by tag name) take priority; unmapped
     * standard fields fall back to the auto-detected values.
     *
     * @param string $form_id The Contact Form 7 form id.
     * @param array  $data     The heuristically extracted data.
     * @return array The merged data.
     */
    private static function applyFieldMapping( $form_id, $data ) {
        if ( '' === $form_id ) {
            return $data;
        }

        $mapped = ( new FormFieldMapper() )->resolve(
            self::TRACKING_NAME,
            $form_id,
            function ( $field_id ) {
                if ( ! isset( $_POST[ $field_id ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    return null;
                }
                return sanitize_text_field(
                    wp_unslash( $_POST[ $field_id ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
                );
            }
        );

        return array_merge( $data, $mapped );
    }

    /**
     * Retrieves the email address from the form submission.
     *
     * @param array $form_tags The form tags.
     *
     * @return string|null The email address, or null if no email tag found.
     */
    private static function getEmail( $form_tags ) {
        if ( empty( $form_tags ) ) {
            return null;
        }

        foreach ( $form_tags as $tag ) {
            if ( 'email' === $tag->basetype && isset( $_POST[ $tag->name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                return sanitize_text_field( wp_unslash( $_POST[ $tag->name ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            }
        }

        return null;
    }

    /**
     * Retrieves the first and last name from the form submission.
     *
     * @param array $form_tags The form tags.
     *
     * @return array|null An array containing the first and
     * last name, or null if no name tag found.
     */
    private static function getName( $form_tags ) {
        if ( empty( $form_tags ) ) {
            return null;
        }

        foreach ( $form_tags as $tag ) {
            if ( 'text' === $tag->basetype
            && strpos( strtolower( $tag->name ), 'name' ) !== false ) {
                return ServerEventFactory::split_name(
                    sanitize_text_field(
                        wp_unslash( $_POST[ $tag->name ] ?? null ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    )
                );
            }
        }

        return null;
    }

    /**
     * Retrieves the phone number from the form submission.
     *
     * @param array $form_tags The form tags.
     *
     * @return string|null The phone number, or null if no phone tag found.
     */
    private static function getPhone( $form_tags ) {
        if ( empty( $form_tags ) ) {
            return null;
        }

        foreach ( $form_tags as $tag ) {
            if ( 'tel' === $tag->basetype ) {
                return isset( $_POST[ $tag->name ] ) ? // phpcs:ignore WordPress.Security.NonceVerification.Missing
                sanitize_text_field(
                    wp_unslash( $_POST[ $tag->name ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
                ) : null;
            }
        }

        return null;
    }
}
