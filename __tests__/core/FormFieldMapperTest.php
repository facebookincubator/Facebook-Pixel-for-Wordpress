<?php
/**
 * Facebook Pixel Plugin FormFieldMapperTest class.
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

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Core\FormFieldMapper;
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Tests\Mocks\MockFormFieldMappingStore;

/**
 * FormFieldMapperTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FormFieldMapperTest extends FacebookWordpressTestBase {

    /**
     * A store holding one Contact Form 7 mapping.
     *
     * @return array
     */
    private function cf7_store() {
        return array(
            'contact-form-7' => array(
                '123' => array(
                    'form_title' => 'Contact form 1',
                    'mappings'   => array(
                        'your-email' => 'email',
                        'your-name'  => 'full_name',
                    ),
                ),
            ),
        );
    }

    /**
     * Every user-data field and the compound targets are valid targets;
     * anything else is not.
     *
     * @return void
     */
    public function testValidTargetsFollowUserDataFields() {
        foreach ( ServerEventFactory::get_user_data_field_names() as $field ) {
            $this->assertTrue( FormFieldMapper::is_valid_target( $field ), $field );
        }
        $this->assertTrue( FormFieldMapper::is_valid_target( 'full_name' ) );
        $this->assertFalse( FormFieldMapper::is_valid_target( 'address' ) );
        $this->assertFalse( FormFieldMapper::is_valid_target( 'value' ) );
        $this->assertFalse( FormFieldMapper::is_valid_target( '' ) );
    }

    /**
     * get_mappings returns a form's saved mappings, or nothing.
     *
     * @return void
     */
    public function testGetMappings() {
        $mapper = new FormFieldMapper( new MockFormFieldMappingStore( $this->cf7_store() ) );

        $this->assertSame(
            array(
                'your-email' => 'email',
                'your-name'  => 'full_name',
            ),
            $mapper->get_mappings( 'contact-form-7', 123 )
        );
        $this->assertSame( array(), $mapper->get_mappings( 'contact-form-7', '999' ) );
        $this->assertSame( array(), $mapper->get_mappings( 'ninja-forms', '123' ) );
    }

    /**
     * Saving replaces the form's previous mapping and drops invalid targets
     * and empty field ids.
     *
     * @return void
     */
    public function testSaveReplacesMappingAndDropsInvalidTargets() {
        $store  = new MockFormFieldMappingStore( $this->cf7_store() );
        $mapper = new FormFieldMapper( $store );

        $this->assertTrue(
            $mapper->save_form_mapping(
                'contact-form-7',
                123,
                'Renamed form',
                array(
                    'tel'     => 'phone',
                    'company' => 'company_name',
                    ''        => 'email',
                )
            )
        );

        $this->assertSame(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'Renamed form',
                        'mappings'   => array( 'tel' => 'phone' ),
                    ),
                ),
            ),
            $store->data
        );
    }

    /**
     * Saving a mapping with nothing valid in it removes the form's entry, and
     * the integration's entry once it is empty.
     *
     * @return void
     */
    public function testSaveWithNothingValidRemovesEntry() {
        $store  = new MockFormFieldMappingStore( $this->cf7_store() );
        $mapper = new FormFieldMapper( $store );

        $mapper->save_form_mapping( 'contact-form-7', '123', 'Contact form 1', array( 'x' => 'bogus' ) );

        $this->assertSame( array(), $store->data );
    }

    /**
     * Deleting removes only the given form; deleting something that is not
     * saved succeeds without writing.
     *
     * @return void
     */
    public function testDelete() {
        $data                                = $this->cf7_store();
        $data['contact-form-7']['456']       = array(
            'form_title' => 'Other',
            'mappings'   => array( 'email' => 'email' ),
        );
        $data['ninja-forms']['1']['mappings'] = array( 'email_1' => 'email' );
        $store                               = new MockFormFieldMappingStore( $data );
        $mapper                              = new FormFieldMapper( $store );

        $this->assertTrue( $mapper->delete_form_mapping( 'contact-form-7', '123' ) );
        $this->assertEquals( array( '456' ), array_keys( $store->data['contact-form-7'] ) );
        $this->assertArrayHasKey( 'ninja-forms', $store->data );

        $writes = $store->writes;
        $this->assertTrue( $mapper->delete_form_mapping( 'wpforms-lite', '9' ) );
        $this->assertSame( $writes, $store->writes );
    }

    /**
     * The flat list has one row per saved form.
     *
     * @return void
     */
    public function testGetFlatList() {
        $data                                 = $this->cf7_store();
        $data['ninja-forms']['1']['mappings'] = array( 'email_1' => 'email' );
        $mapper                               = new FormFieldMapper( new MockFormFieldMappingStore( $data ) );

        $this->assertSame(
            array(
                array(
                    'integration_name' => 'contact-form-7',
                    'form_id'          => '123',
                    'form_title'       => 'Contact form 1',
                    'mapped_count'     => 2,
                ),
                array(
                    'integration_name' => 'ninja-forms',
                    'form_id'          => '1',
                    'form_title'       => '',
                    'mapped_count'     => 1,
                ),
            ),
            $mapper->get_flat_list()
        );
    }
}
