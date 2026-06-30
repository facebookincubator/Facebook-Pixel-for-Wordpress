<?php
/**
 * WordPress integration tests for form-plugin discovery.
 *
 * These run against real, installed form plugins (Contact Form 7, WPForms
 * Lite, Ninja Forms, Formidable Lite) inside a booted WordPress test
 * environment. They validate that:
 *  - each integration's is_available() is correct against the real plugin,
 *  - get_forms()/get_form_fields() call the right plugin APIs (no fatals) and
 *    return well-formed data,
 *  - a programmatically created form is actually discovered (best-effort:
 *    skipped if a plugin's creation API differs).
 *
 * Run via: vendor/bin/phpunit -c phpunit-integration.xml (see README).
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

namespace FacebookPixelPlugin\Tests\WpIntegration;

use FacebookPixelPlugin\Integration\FacebookWordpressContactForm7;
use FacebookPixelPlugin\Integration\FacebookWordpressWPForms;
use FacebookPixelPlugin\Integration\FacebookWordpressNinjaForms;
use FacebookPixelPlugin\Integration\FacebookWordpressFormidableForm;

/**
 * FormPluginDiscoveryIntegrationTest class.
 */
final class FormPluginDiscoveryIntegrationTest extends \WP_UnitTestCase {

    /**
     * Asserts plugin availability.
     *
     * In CI (FB_INTEGRATION_REQUIRE_PLUGINS=1) availability is required, so a
     * wrong detection symbol fails the build. Locally, an absent plugin skips
     * the test instead.
     *
     * @param bool   $available Whether the integration reports availability.
     * @param string $label     Human label for messages.
     * @return void
     */
    private function ensure_available( $available, $label ) {
        if ( '1' === getenv( 'FB_INTEGRATION_REQUIRE_PLUGINS' ) ) {
            $this->assertTrue(
                $available,
                $label . ' should be available when the plugin is loaded'
            );
        } elseif ( ! $available ) {
            $this->markTestSkipped( $label . ' is not installed.' );
        }
    }

    /**
     * Asserts discovery returns well-formed shapes for an integration class.
     *
     * @param string $class   Integration class name.
     * @param string $form_id A form id to probe (may not exist).
     * @return void
     */
    private function assert_discovery_shape( $class, $form_id ) {
        $forms = $class::get_forms();
        $this->assertIsArray( $forms );
        foreach ( $forms as $form ) {
            $this->assertArrayHasKey( 'id', $form );
            $this->assertArrayHasKey( 'title', $form );
        }

        $fields = $class::get_form_fields( $form_id );
        $this->assertIsArray( $fields );
        foreach ( $fields as $field ) {
            $this->assertArrayHasKey( 'id', $field );
            $this->assertArrayHasKey( 'label', $field );
            $this->assertArrayHasKey( 'type', $field );
        }
    }

    /**
     * Returns the list of field ids discovered for a form.
     *
     * @param array $fields Field rows from get_form_fields().
     * @return string[]
     */
    private function field_ids( $fields ) {
        return array_map(
            function ( $field ) {
                return $field['id'];
            },
            $fields
        );
    }

    /**
     * Returns true if the forms list contains the given id.
     *
     * @param array  $forms   Form rows from get_forms().
     * @param string $form_id The form id to look for.
     * @return bool
     */
    private function forms_contain( $forms, $form_id ) {
        foreach ( $forms as $form ) {
            if ( (string) $form['id'] === (string) $form_id ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Contact Form 7: availability, discovery shape, and a created form.
     *
     * @return void
     */
    public function test_contact_form_7() {
        $this->ensure_available(
            FacebookWordpressContactForm7::is_available(),
            'Contact Form 7'
        );

        $this->assert_discovery_shape(
            FacebookWordpressContactForm7::class,
            '0'
        );

        try {
            $form = \WPCF7_ContactForm::get_template(
                array( 'title' => 'FB IT CF7' )
            );
            $form->set_properties(
                array(
                    'form' => '[text* your-name]'
                        . '[email* your-email][tel your-phone][submit "Send"]',
                )
            );
            $form_id = (string) $form->save();
        } catch ( \Throwable $e ) {
            $this->markTestSkipped(
                'CF7 form creation API changed: ' . $e->getMessage()
            );
        }

        $this->assertTrue(
            $this->forms_contain(
                FacebookWordpressContactForm7::get_forms(),
                $form_id
            ),
            'Created CF7 form should be discovered'
        );

        $ids = $this->field_ids(
            FacebookWordpressContactForm7::get_form_fields( $form_id )
        );
        $this->assertContains( 'your-name', $ids );
        $this->assertContains( 'your-email', $ids );
        $this->assertContains( 'your-phone', $ids );
        $this->assertNotContains( 'submit', $ids );
    }

    /**
     * WPForms: availability, discovery shape, and a created form.
     *
     * @return void
     */
    public function test_wpforms() {
        $this->ensure_available(
            FacebookWordpressWPForms::is_available(),
            'WPForms'
        );

        $this->assert_discovery_shape( FacebookWordpressWPForms::class, '0' );

        try {
            $form_data = array(
                'settings' => array( 'form_title' => 'FB IT WPForms' ),
                'fields'   => array(
                    1 => array(
                        'id'    => 1,
                        'type'  => 'email',
                        'label' => 'Email',
                    ),
                    2 => array(
                        'id'    => 2,
                        'type'  => 'text',
                        'label' => 'First Name',
                    ),
                ),
            );
            $form_id = (string) wp_insert_post(
                array(
                    'post_type'    => 'wpforms',
                    'post_status'  => 'publish',
                    'post_title'   => 'FB IT WPForms',
                    'post_content' => wpforms_encode( $form_data ),
                )
            );
        } catch ( \Throwable $e ) {
            $this->markTestSkipped(
                'WPForms form creation API changed: ' . $e->getMessage()
            );
        }

        $this->assertTrue(
            $this->forms_contain(
                FacebookWordpressWPForms::get_forms(),
                $form_id
            ),
            'Created WPForms form should be discovered'
        );

        $ids = $this->field_ids(
            FacebookWordpressWPForms::get_form_fields( $form_id )
        );
        $this->assertContains( '1', $ids );
        $this->assertContains( '2', $ids );
    }

    /**
     * Ninja Forms: availability and discovery shape, plus a best-effort form.
     *
     * @return void
     */
    public function test_ninja_forms() {
        $this->ensure_available(
            FacebookWordpressNinjaForms::is_available(),
            'Ninja Forms'
        );

        $this->assert_discovery_shape( FacebookWordpressNinjaForms::class, '0' );

        try {
            $form = \Ninja_Forms()->form()->get_form();
            $form->update_settings( array( 'title' => 'FB IT Ninja' ) )->save();
            $form_id = (string) $form->get_id();

            $field = \Ninja_Forms()->form( $form_id )->get_field();
            $field->update_settings(
                array(
                    'key'   => 'email_1',
                    'label' => 'Email',
                    'type'  => 'email',
                )
            )->save();
        } catch ( \Throwable $e ) {
            $this->markTestSkipped(
                'Ninja Forms creation API differs: ' . $e->getMessage()
            );
        }

        $this->assertTrue(
            $this->forms_contain(
                FacebookWordpressNinjaForms::get_forms(),
                $form_id
            ),
            'Created Ninja form should be discovered'
        );
        $this->assertContains(
            'email_1',
            $this->field_ids(
                FacebookWordpressNinjaForms::get_form_fields( $form_id )
            )
        );
    }

    /**
     * Formidable: availability, discovery shape, and a created form.
     *
     * @return void
     */
    public function test_formidable() {
        $this->ensure_available(
            FacebookWordpressFormidableForm::is_available(),
            'Formidable Forms'
        );

        $this->assert_discovery_shape(
            FacebookWordpressFormidableForm::class,
            '0'
        );

        try {
            $form_id = (string) \FrmForm::create(
                array(
                    'name'     => 'FB IT Formidable',
                    'form_key' => 'fb_it_formidable',
                )
            );
            \FrmField::create(
                array(
                    'type'      => 'email',
                    'name'      => 'Email',
                    'field_key' => 'fb_it_email',
                    'form_id'   => $form_id,
                )
            );
        } catch ( \Throwable $e ) {
            $this->markTestSkipped(
                'Formidable creation API differs: ' . $e->getMessage()
            );
        }

        $this->assertTrue(
            $this->forms_contain(
                FacebookWordpressFormidableForm::get_forms(),
                $form_id
            ),
            'Created Formidable form should be discovered'
        );

        $fields = FacebookWordpressFormidableForm::get_form_fields( $form_id );
        $this->assertNotEmpty( $fields );
    }
}
