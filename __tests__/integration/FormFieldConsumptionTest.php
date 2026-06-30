<?php
/**
 * Facebook Pixel Plugin FormFieldConsumptionTest class.
 *
 * Verifies that saved field mappings override the heuristic extraction in
 * each integration's readFormData(), falling back to heuristics when a
 * standard field is not mapped.
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
use FacebookPixelPlugin\Integration\FacebookWordpressCalderaForm;
use FacebookPixelPlugin\Tests\Mocks\MockContactForm7Tag;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * Minimal Contact Form 7 form double exposing id() and scan_form_tags() as
 * real methods (so method_exists() works in the integration).
 */
class MappingTestCF7Form {
    /**
     * Form id.
     *
     * @var string
     */
    public $form_id;

    /**
     * Form tags.
     *
     * @var array
     */
    public $tags = array();

    /**
     * @return string The form id.
     */
    public function id() {
        return $this->form_id;
    }

    /**
     * @return array The form tags.
     */
    public function scan_form_tags() {
        return $this->tags;
    }
}

/**
 * FormFieldConsumptionTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FormFieldConsumptionTest extends FacebookWordpressTestBase {

    /**
     * Mocks sanitize_text_field and the mapping store.
     *
     * @param array $store The mapping store returned by get_option.
     * @return void
     */
    private function mockEnv( $store ) {
        \WP_Mock::userFunction(
            'sanitize_text_field',
            array(
                'return' => function ( $v ) {
                    return $v;
                },
            )
        );
        \WP_Mock::userFunction(
            'get_option',
            array( 'return' => $store )
        );
    }

    /**
     * A mapped field overrides the heuristic; unmapped fields fall back.
     *
     * @return void
     */
    public function testContactForm7MappingOverridesHeuristic() {
        $this->mockEnv(
            array(
                'contact-form-7' => array(
                    '99' => array(
                        'form_title' => 'wform1',
                        'mappings'   => array( 'custom_fn' => 'first_name' ),
                    ),
                ),
            )
        );

        $_POST['your-email'] = 'heur@example.com';
        $_POST['your-name']  = 'Heur Last';
        $_POST['custom_fn']  = 'Mapped';

        $form          = new MappingTestCF7Form();
        $form->form_id = '99';
        $form->tags    = array(
            new MockContactForm7Tag( 'email', 'your-email' ),
            new MockContactForm7Tag( 'text', 'your-name' ),
            new MockContactForm7Tag( 'text', 'custom_fn' ),
        );

        $data = FacebookWordpressContactForm7::readFormData( $form );

        // Mapping wins for first_name.
        $this->assertEquals( 'Mapped', $data['first_name'] );
        // Heuristics still supply email and last_name.
        $this->assertEquals( 'heur@example.com', $data['email'] );
        $this->assertEquals( 'Last', $data['last_name'] );
    }

    /**
     * With no mapping stored, behavior is unchanged (pure heuristics).
     *
     * @return void
     */
    public function testContactForm7NoMappingFallsBack() {
        $this->mockEnv( array() );

        $_POST['your-email'] = 'heur@example.com';
        $_POST['your-name']  = 'Heur Last';

        $form          = new MappingTestCF7Form();
        $form->form_id = '99';
        $form->tags    = array(
            new MockContactForm7Tag( 'email', 'your-email' ),
            new MockContactForm7Tag( 'text', 'your-name' ),
        );

        $data = FacebookWordpressContactForm7::readFormData( $form );

        $this->assertEquals( 'heur@example.com', $data['email'] );
        $this->assertEquals( 'Heur', $data['first_name'] );
        $this->assertEquals( 'Last', $data['last_name'] );
    }

    /**
     * A full_name mapping splits into first and last name.
     *
     * @return void
     */
    public function testContactForm7FullNameMappingSplits() {
        $this->mockEnv(
            array(
                'contact-form-7' => array(
                    '99' => array(
                        'form_title' => 'wform1',
                        'mappings'   => array( 'fullname' => 'full_name' ),
                    ),
                ),
            )
        );

        $_POST['fullname'] = 'Ada Lovelace';

        $form          = new MappingTestCF7Form();
        $form->form_id = '99';
        $form->tags    = array(
            new MockContactForm7Tag( 'text', 'fullname' ),
        );

        $data = FacebookWordpressContactForm7::readFormData( $form );

        $this->assertEquals( 'Ada', $data['first_name'] );
        $this->assertEquals( 'Lovelace', $data['last_name'] );
    }

    /**
     * Caldera mapping overrides heuristics (value read from $_POST by ID).
     *
     * @return void
     */
    public function testCalderaMappingOverridesHeuristic() {
        $this->mockEnv(
            array(
                'caldera-forms' => array(
                    'cf1' => array(
                        'form_title' => 'Contact',
                        'mappings'   => array( 'fld_custom' => 'last_name' ),
                    ),
                ),
            )
        );

        $_POST['fld_email']  = 'c@d.com';
        $_POST['fld_custom'] = 'Zed';

        $form = array(
            'ID'     => 'cf1',
            'fields' => array(
                array( 'ID' => 'fld_email', 'type' => 'email' ),
                array( 'ID' => 'fld_custom', 'type' => 'text' ),
            ),
        );

        $data = FacebookWordpressCalderaForm::readFormData( $form );

        $this->assertEquals( 'c@d.com', $data['email'] );
        $this->assertEquals( 'Zed', $data['last_name'] );
    }
}
