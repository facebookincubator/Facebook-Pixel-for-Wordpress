<?php
/**
 * Facebook Pixel Plugin FormFieldDiscoveryTest class.
 *
 * Exercises the form/field discovery API exposed by the form-builder
 * integrations for the admin Field Mapping screen.
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

namespace FacebookPixelPlugin\Tests\Integration;

use FacebookPixelPlugin\Integration\FacebookWordpressContactForm7;
use FacebookPixelPlugin\Integration\FacebookWordpressWPForms;
use FacebookPixelPlugin\Integration\FacebookWordpressNinjaForms;
use FacebookPixelPlugin\Integration\FacebookWordpressFormidableForm;
use FacebookPixelPlugin\Integration\FacebookWordpressCalderaForm;
use FacebookPixelPlugin\Integration\FacebookWordpressWooCommerce;
use FacebookPixelPlugin\Tests\Mocks\MockContactForm7Tag;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FormFieldDiscoveryTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FormFieldDiscoveryTest extends FacebookWordpressTestBase {

    /**
     * All form-builder integrations advertise mapping support.
     *
     * @return void
     */
    public function testFormBuildersSupportFieldMapping() {
        $this->assertTrue(
            FacebookWordpressContactForm7::supports_field_mapping()
        );
        $this->assertTrue(
            FacebookWordpressWPForms::supports_field_mapping()
        );
        $this->assertTrue(
            FacebookWordpressNinjaForms::supports_field_mapping()
        );
        $this->assertTrue(
            FacebookWordpressFormidableForm::supports_field_mapping()
        );
        $this->assertTrue(
            FacebookWordpressCalderaForm::supports_field_mapping()
        );
    }

    /**
     * E-commerce integrations do not opt into mapping.
     *
     * @return void
     */
    public function testEcommerceDoesNotSupportFieldMapping() {
        $this->assertFalse(
            FacebookWordpressWooCommerce::supports_field_mapping()
        );
    }

    /**
     * Each integration exposes a friendly label.
     *
     * @return void
     */
    public function testIntegrationLabels() {
        $this->assertEquals(
            'Contact Form 7',
            FacebookWordpressContactForm7::get_integration_label()
        );
        $this->assertEquals(
            'WPForms',
            FacebookWordpressWPForms::get_integration_label()
        );
        $this->assertEquals(
            'Ninja Forms',
            FacebookWordpressNinjaForms::get_integration_label()
        );
        $this->assertEquals(
            'Formidable Forms',
            FacebookWordpressFormidableForm::get_integration_label()
        );
        $this->assertEquals(
            'Caldera Forms',
            FacebookWordpressCalderaForm::get_integration_label()
        );
    }

    /**
     * When the underlying plugin is not active, discovery returns empties and
     * is_available() is false.
     *
     * @return void
     */
    public function testDiscoveryEmptyWhenPluginUnavailable() {
        $this->assertFalse( FacebookWordpressContactForm7::is_available() );
        $this->assertSame( array(), FacebookWordpressContactForm7::get_forms() );
        $this->assertSame(
            array(),
            FacebookWordpressContactForm7::get_form_fields( '1' )
        );

        $this->assertFalse( FacebookWordpressWPForms::is_available() );
        $this->assertSame( array(), FacebookWordpressWPForms::get_forms() );

        $this->assertFalse( FacebookWordpressNinjaForms::is_available() );
        $this->assertSame( array(), FacebookWordpressNinjaForms::get_forms() );

        $this->assertFalse( FacebookWordpressFormidableForm::is_available() );
        $this->assertSame(
            array(),
            FacebookWordpressFormidableForm::get_forms()
        );

        $this->assertFalse( FacebookWordpressCalderaForm::is_available() );
        $this->assertSame( array(), FacebookWordpressCalderaForm::get_forms() );
    }

    /**
     * Contact Form 7 forms are enumerated via WPCF7_ContactForm::find().
     *
     * @return void
     */
    public function testContactForm7GetForms() {
        $form = \Mockery::mock();
        $form->shouldReceive( 'id' )->andReturn( 123 );
        $form->shouldReceive( 'title' )->andReturn( 'wform1' );

        \Mockery::mock( 'alias:WPCF7_ContactForm' )
            ->shouldReceive( 'find' )
            ->andReturn( array( $form ) );

        $forms = FacebookWordpressContactForm7::get_forms();

        $this->assertEquals(
            array(
                array(
                    'id'    => '123',
                    'title' => 'wform1',
                ),
            ),
            $forms
        );
    }

    /**
     * Contact Form 7 fields are read from scan_form_tags(), skipping submit
     * and unnamed tags and de-duplicating by name.
     *
     * @return void
     */
    public function testContactForm7GetFormFields() {
        $tags = array(
            new MockContactForm7Tag( 'text', 'your-name' ),
            new MockContactForm7Tag( 'email', 'your-email' ),
            new MockContactForm7Tag( 'email', 'your-email' ), // duplicate.
            new MockContactForm7Tag( 'submit', 'send' ),      // skipped.
            new MockContactForm7Tag( 'text', '' ),            // skipped.
        );

        $form = \Mockery::mock();
        $form->shouldReceive( 'scan_form_tags' )->andReturn( $tags );

        \Mockery::mock( 'alias:WPCF7_ContactForm' )
            ->shouldReceive( 'get_instance' )
            ->with( '123' )
            ->andReturn( $form );

        $fields = FacebookWordpressContactForm7::get_form_fields( '123' );

        $this->assertEquals(
            array(
                array(
                    'id'    => 'your-name',
                    'label' => 'your-name',
                    'type'  => 'text',
                ),
                array(
                    'id'    => 'your-email',
                    'label' => 'your-email',
                    'type'  => 'email',
                ),
            ),
            $fields
        );
    }
}
