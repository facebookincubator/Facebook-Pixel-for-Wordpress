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

use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\AjaxFilterDelivery;

/**
 * FacebookWordpressContactForm7 class.
 */
class FacebookWordpressContactForm7 extends FacebookWordpressIntegrationBase {
    const PLUGIN_FILE   = 'contact-form-7/wp-contact-form-7.php';
    const TRACKING_NAME = 'contact-form-7';

    /**
     * Registers the Contact Form 7 hooks.
     *
     * The Lead event (server + browser) is dispatched through Signals on
     * submit, with the browser code delivered into the CF7 AJAX response; a
     * front-end listener evaluates it.
     *
     * @return void
     */
    public function inject_pixel_code() {
        $this->signals->on(
            'wpcf7_submit',
            'Lead',
            array( $this, 'read_form_data' ),
            new AjaxFilterDelivery( 'wpcf7_feedback_response', 'fb_pxl_code' ),
            self::TRACKING_NAME,
            10,
            2
        );
        add_action(
            'wp_footer',
            array( $this, 'inject_mail_sent_listener' ),
            10,
            2
        );
    }

    /**
     * Injects a JavaScript listener for the 'wpcf7mailsent' event that
     * evaluates the pixel code returned in the CF7 AJAX response.
     *
     * @return void
     */
    public function inject_mail_sent_listener() {
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
        echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Extracts the Lead event data from a Contact Form 7 submission.
     *
     * Returns null when the submission did not succeed, so Signals skips
     * dispatching. The internal-user check is handled by Signals.
     *
     * @param object $form   The Contact Form 7 form object.
     * @param array  $result The Contact Form 7 submission result.
     * @return \FacebookPixelPlugin\Core\EventData|null
     */
    public function read_form_data( $form, $result = array() ) {
        if ( isset( $result['status'] ) && 'mail_sent' !== $result['status'] ) {
            return null;
        }
        if ( empty( $form ) ) {
            return null;
        }

        $form_tags = $form->scan_form_tags();
        $name      = self::getName( $form_tags );

        return $this->event_builder->build(
            array(
                'email'      => self::getEmail( $form_tags ),
                'first_name' => is_array( $name ) ? $name[0] : null,
                'last_name'  => is_array( $name ) ? $name[1] : null,
                'phone'      => self::getPhone( $form_tags ),
            )
        );
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
