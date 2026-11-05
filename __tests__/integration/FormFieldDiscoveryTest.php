<?php
/**
 * Facebook Pixel Plugin FormFieldDiscoveryTest class.
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
use FacebookPixelPlugin\Integration\FacebookWordpressFormidableForm;
use FacebookPixelPlugin\Integration\FacebookWordpressMailchimpForWp;
use FacebookPixelPlugin\Integration\FacebookWordpressNinjaForms;
use FacebookPixelPlugin\Integration\FacebookWordpressWPForms;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Tests\Mocks\MockContactForm7Tag;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormField;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormFieldValue;

/**
 * Form and field discovery for the field mapping screen, and that the field
 * ids it lists are the ones a saved mapping is matched on at submission.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FormFieldDiscoveryTest extends FacebookWordpressTestBase {

    /**
     * Builds an integration with the given saved mappings.
     *
     * @param string $class    The integration class.
     * @param array  $mappings Initial mapping store contents.
     * @return \FacebookPixelPlugin\Integration\TrackableLeadFormIntegrationBase
     */
    private function make( $class, array $mappings = array() ) {
        return new $class( $this->make_signals(), $this->make_field_mapper( $mappings ) );
    }

    /**
     * Saved mappings for one form.
     *
     * @param string $integration_name The integration name.
     * @param string $form_id          The form id.
     * @param array  $mappings         field id => target.
     * @return array
     */
    private function saved( $integration_name, $form_id, array $mappings ) {
        return array(
            $integration_name => array(
                $form_id => array(
                    'form_title' => 'Form',
                    'mappings'   => $mappings,
                ),
            ),
        );
    }

    /**
     * Every lead-form integration has a label, and lists nothing while its
     * plugin is not loaded.
     *
     * @return void
     */
    public function testUnavailablePluginsListNothing() {
        $labels = array(
            FacebookWordpressContactForm7::class   => 'Contact Form 7',
            FacebookWordpressWPForms::class        => 'WPForms',
            FacebookWordpressNinjaForms::class     => 'Ninja Forms',
            FacebookWordpressFormidableForm::class => 'Formidable Forms',
            FacebookWordpressMailchimpForWp::class => 'Mailchimp for WP',
        );
        foreach ( $labels as $class => $label ) {
            $integration = $this->make( $class );
            $this->assertSame( $label, $integration->get_integration_label() );
            $this->assertFalse( $integration->is_field_mapping_available(), $class );
            $this->assertSame( array(), $integration->get_forms(), $class );
            $this->assertSame( array(), $integration->get_form_fields( '1' ), $class );
            $this->assertSame( array(), $integration->suggest_field_mapping( '1' ), $class );
        }
    }

    /**
     * Contact Form 7 lists its forms, and each form's named input tags once.
     *
     * @return void
     */
    public function testContactForm7Discovery() {
        $form = \Mockery::mock();
        $form->shouldReceive( 'id' )->andReturn( 123 );
        $form->shouldReceive( 'title' )->andReturn( 'Contact form 1' );
        $form->shouldReceive( 'scan_form_tags' )->andReturn(
            array(
                new MockContactForm7Tag( 'text', 'your-name' ),
                new MockContactForm7Tag( 'email', 'your-email' ),
                new MockContactForm7Tag( 'email', 'your-email' ),
                new MockContactForm7Tag( 'submit', 'send' ),
                new MockContactForm7Tag( 'text', '' ),
            )
        );
        $cf7 = \Mockery::mock( 'alias:WPCF7_ContactForm' );
        $cf7->shouldReceive( 'find' )->andReturn( array( $form ) );
        $cf7->shouldReceive( 'get_instance' )->with( '123' )->andReturn( $form );

        $integration = $this->make( FacebookWordpressContactForm7::class );

        $this->assertSame(
            array(
                array(
                    'id'    => '123',
                    'title' => 'Contact form 1',
                ),
            ),
            $integration->get_forms()
        );
        $this->assertSame(
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
            $integration->get_form_fields( '123' )
        );
        $this->assertSame(
            array(
                'your-name'  => 'full_name',
                'your-email' => 'email',
            ),
            $integration->suggest_field_mapping( '123' )
        );
    }

    /**
     * WPForms lists fields by numeric id, skipping layout fields, and a mapping
     * saved for a field id is applied on submission even though the row's name
     * is the field label.
     *
     * @return void
     */
    public function testWPFormsDiscoveryIdsMatchSubmission() {
        $post               = new \stdClass();
        $post->ID           = 5;
        $post->post_title   = 'Contact';
        $post->post_content = json_encode(
            array(
                'fields' => array(
                    '3' => array(
                        'id'    => '3',
                        'type'  => 'text',
                        'label' => 'Work email',
                    ),
                    '4' => array(
                        'id'    => '4',
                        'type'  => 'divider',
                        'label' => 'Section',
                    ),
                    '6' => array(
                        'id'   => '6',
                        'type' => 'phone',
                    ),
                    '7' => array(
                        'id'     => '7',
                        'type'   => 'name',
                        'format' => 'first-last',
                        'label'  => 'Name',
                    ),
                    '8' => array(
                        'id'    => '8',
                        'type'  => 'text',
                        'label' => 'Town',
                    ),
                ),
            )
        );
        $forms = \Mockery::mock();
        $forms->shouldReceive( 'get' )->with( '' )->andReturn( array( $post ) );
        $forms->shouldReceive( 'get' )->with( '5' )->andReturn( $post );
        $wpforms       = new \stdClass();
        $wpforms->form = $forms;
        \WP_Mock::userFunction( 'wpforms', array( 'return' => $wpforms ) );

        $integration = $this->make(
            FacebookWordpressWPForms::class,
            $this->saved( FacebookWordpressWPForms::INTEGRATION_NAME, '5', array( '3' => 'email' ) )
        );

        $this->assertSame(
            array(
                array(
                    'id'    => '5',
                    'title' => 'Contact',
                ),
            ),
            $integration->get_forms()
        );
        $this->assertSame(
            array(
                array(
                    'id'    => '3',
                    'label' => 'Work email',
                    'type'  => 'text',
                ),
                array(
                    'id'    => '6',
                    'label' => '6',
                    'type'  => 'phone',
                ),
                array(
                    'id'    => '7',
                    'label' => 'Name',
                    'type'  => 'name',
                ),
                array(
                    'id'    => '8',
                    'label' => 'Town',
                    'type'  => 'text',
                ),
            ),
            $integration->get_form_fields( '5' )
        );
        $this->assertSame(
            array(
                '6' => 'phone',
                '7' => 'full_name',
                '8' => 'city',
            ),
            $integration->suggest_field_mapping( '5' )
        );

        $lead = $integration->extract_lead_data(
            array( 'fields' => array( '3' => 'work@s2s.com' ) ),
            array(
                'id'     => 5,
                'fields' => array(
                    '3' => array(
                        'label' => 'Work email',
                        'type'  => 'text',
                    ),
                ),
            )
        );
        $this->assertSame( array( 'email' => 'work@s2s.com' ), $lead );
    }

    /**
     * Ninja Forms lists fields by key, skipping submit buttons.
     *
     * @return void
     */
    public function testNinjaFormsDiscovery() {
        $field = function ( $key, $type, $label ) {
            $mock = \Mockery::mock();
            $mock->shouldReceive( 'get_setting' )->with( 'key' )->andReturn( $key );
            $mock->shouldReceive( 'get_setting' )->with( 'type' )->andReturn( $type );
            $mock->shouldReceive( 'get_setting' )->with( 'label' )->andReturn( $label );
            return $mock;
        };
        $form = \Mockery::mock();
        $form->shouldReceive( 'get_id' )->andReturn( 2 );
        $form->shouldReceive( 'get_setting' )->with( 'title' )->andReturn( 'Contact Me' );
        $all_forms = \Mockery::mock();
        $all_forms->shouldReceive( 'get_forms' )->andReturn( array( $form ) );
        $one_form = \Mockery::mock();
        $one_form->shouldReceive( 'get_fields' )->andReturn(
            array(
                $field( 'email_1', 'email', 'Email' ),
                $field( 'submit_2', 'submit', 'Send' ),
            )
        );
        $ninja = \Mockery::mock();
        $ninja->shouldReceive( 'form' )->withNoArgs()->andReturn( $all_forms );
        $ninja->shouldReceive( 'form' )->with( '2' )->andReturn( $one_form );
        \WP_Mock::userFunction( 'Ninja_Forms', array( 'return' => $ninja ) );

        $integration = $this->make( FacebookWordpressNinjaForms::class );

        $this->assertSame(
            array(
                array(
                    'id'    => '2',
                    'title' => 'Contact Me',
                ),
            ),
            $integration->get_forms()
        );
        $this->assertSame(
            array(
                array(
                    'id'    => 'email_1',
                    'label' => 'Email',
                    'type'  => 'email',
                ),
            ),
            $integration->get_form_fields( '2' )
        );
        $this->assertSame( array( 'email_1' => 'email' ), $integration->suggest_field_mapping( '2' ) );
    }

    /**
     * Formidable lists fields by id, skipping layout fields, and a mapping
     * saved for a field id is applied on submission.
     *
     * @return void
     */
    public function testFormidableDiscoveryIdsMatchSubmission() {
        $form       = new \stdClass();
        $form->id   = 9;
        $form->name = 'Lead';
        $email      = new \stdClass();
        $email->id   = 11;
        $email->name = 'Work email';
        $email->type = 'text';
        $html       = new \stdClass();
        $html->id   = 12;
        $html->name = 'Intro';
        $html->type = 'html';
        \Mockery::mock( 'alias:FrmForm' )->shouldReceive( 'get_published_forms' )->andReturn( array( $form ) );
        $first              = new \stdClass();
        $first->id          = 13;
        $first->name        = 'Name';
        $first->description = 'First';
        $first->type        = 'text';
        $where              = new \stdClass();
        $where->id          = 14;
        $where->name        = 'Address';
        $where->type        = 'address';
        \Mockery::mock( 'alias:FrmField' )->shouldReceive( 'get_all_for_form' )->with( '9' )->andReturn( array( $email, $html, $first, $where ) );
        \Mockery::mock( 'overload:FrmEntryValues' )->shouldReceive( 'get_field_values' )->andReturn(
            array(
                new MockFormidableFormFieldValue(
                    new MockFormidableFormField( 'text', 'Work email', '', '11' ),
                    'work@s2s.com'
                ),
            )
        );

        $integration = $this->make(
            FacebookWordpressFormidableForm::class,
            $this->saved( FacebookWordpressFormidableForm::INTEGRATION_NAME, '9', array( '11' => 'email' ) )
        );

        $this->assertSame(
            array(
                array(
                    'id'    => '9',
                    'title' => 'Lead',
                ),
            ),
            $integration->get_forms()
        );
        $this->assertSame(
            array(
                array(
                    'id'    => '11',
                    'label' => 'Work email',
                    'type'  => 'text',
                ),
                array(
                    'id'    => '13',
                    'label' => 'Name',
                    'type'  => 'text',
                ),
                array(
                    'id'    => '14',
                    'label' => 'Address',
                    'type'  => 'address',
                ),
            ),
            $integration->get_form_fields( '9' )
        );
        $this->assertSame(
            array(
                '13' => 'first_name',
                '14' => 'address',
            ),
            $integration->suggest_field_mapping( '9' )
        );
        $this->assertSame( array( 'email' => 'work@s2s.com' ), $integration->extract_lead_data( 9, 100 ) );
    }

    /**
     * Mailchimp for WP lists the upper-cased input names in a form's markup,
     * grouping ADDRESS[...] inputs under ADDRESS and skipping internal ones,
     * suggests the merge fields automatic detection knows, and applies only
     * the saved mapping on submission.
     *
     * @return void
     */
    public function testMailchimpDiscoveryAndSubmission() {
        $form          = new \stdClass();
        $form->ID      = 42;
        $form->name    = 'Newsletter';
        $form->content = '<input type="email" name="EMAIL" />'
            . '<input type="text" name="fname" />'
            . "<input type='text' name='WORK_PHONE' />"
            . '<input type="text" name="ADDRESS[city]" />'
            . '<input type="text" name="ADDRESS[country]" />'
            . '<input type="text" name="_mc4wp_honeypot" />'
            . '<input type="email" name="EMAIL" />';
        \WP_Mock::userFunction( 'mc4wp_get_forms', array( 'return' => array( $form ) ) );
        \WP_Mock::userFunction(
            'mc4wp_get_form',
            array(
                'return' => function ( $id ) use ( $form ) {
                    if ( '42' !== (string) $id ) {
                        throw new \Exception( 'Form not found' );
                    }
                    return $form;
                },
            )
        );
        \WP_Mock::userFunction(
            'sanitize_text_field',
            array(
                'return' => function ( $input ) {
                    return $input;
                },
            )
        );

        $integration = $this->make(
            FacebookWordpressMailchimpForWp::class,
            $this->saved(
                FacebookWordpressMailchimpForWp::INTEGRATION_NAME,
                '42',
                array(
                    'WORK_PHONE' => 'phone',
                    'ADDRESS'    => 'address',
                )
            )
        );

        $this->assertSame(
            array(
                array(
                    'id'    => '42',
                    'title' => 'Newsletter',
                ),
            ),
            $integration->get_forms()
        );
        $this->assertSame(
            array( 'EMAIL', 'FNAME', 'WORK_PHONE', 'ADDRESS' ),
            array_column( $integration->get_form_fields( '42' ), 'id' )
        );
        $this->assertSame( array(), $integration->get_form_fields( '7' ) );
        $this->assertSame(
            array(
                'EMAIL'   => 'email',
                'FNAME'   => 'first_name',
                'ADDRESS' => 'address',
            ),
            $integration->suggest_field_mapping( '42' )
        );

        // EMAIL is not in the saved mapping, so it is not sent.
        $form->data = array(
            'EMAIL'      => 'pika.chu@s2s.com',
            'WORK_PHONE' => '555',
            'ADDRESS'    => array(
                'city'    => 'Springfield',
                'country' => 'US',
            ),
        );
        $this->assertSame(
            array(
                'phone'   => '555',
                'city'    => 'Springfield',
                'country' => 'US',
            ),
            $integration->extract_lead_data( $form )
        );
    }

    /**
     * A suggestion uses each target once: a second email field is left
     * unmapped, and so is a second name field once full_name is taken.
     *
     * @return void
     */
    public function testSuggestionUsesEachTargetOnce() {
        $form = \Mockery::mock();
        $form->shouldReceive( 'scan_form_tags' )->andReturn(
            array(
                new MockContactForm7Tag( 'text', 'your-name' ),
                new MockContactForm7Tag( 'email', 'your-email' ),
                new MockContactForm7Tag( 'email', 'other-email' ),
                new MockContactForm7Tag( 'text', 'company-name' ),
            )
        );
        \Mockery::mock( 'alias:WPCF7_ContactForm' )->shouldReceive( 'get_instance' )->andReturn( $form );

        $this->assertSame(
            array(
                'your-name'  => 'full_name',
                'your-email' => 'email',
            ),
            $this->make( FacebookWordpressContactForm7::class )->suggest_field_mapping( '1' )
        );
    }
}
