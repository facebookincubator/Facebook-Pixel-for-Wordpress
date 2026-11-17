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
use FacebookPixelPlugin\Core\LeadEventQuality;
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
     * A store holding one Contact Form 7 mapping: two included fields and an
     * excluded one.
     *
     * @return array
     */
    private function cf7_store() {
        return array(
            'contact-form-7' => array(
                '123' => array(
                    'form_title' => 'Contact form 1',
                    'rejected'   => false,
                    'fields'     => array(
                        'your-email'   => array(
                            'included' => true,
                            'target'   => 'email',
                            'label'    => 'your-email',
                        ),
                        'your-name'    => array(
                            'included' => true,
                            'target'   => 'full_name',
                        ),
                        'your-message' => array( 'included' => false ),
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
        $this->assertTrue( FormFieldMapper::is_valid_target( 'address' ) );
        $this->assertFalse( FormFieldMapper::is_valid_target( 'company' ) );
        $this->assertFalse( FormFieldMapper::is_valid_target( 'value' ) );
        $this->assertFalse( FormFieldMapper::is_valid_target( '' ) );
    }

    /**
     * get_mappings returns a form's included fields, get_fields every saved
     * decision; both are empty for a form without a saved mapping.
     *
     * @return void
     */
    public function testGetMappingsAndFields() {
        $mapper = new FormFieldMapper( new MockFormFieldMappingStore( $this->cf7_store() ) );

        $this->assertSame(
            array(
                'your-email' => 'email',
                'your-name'  => 'full_name',
            ),
            $mapper->get_mappings( 'contact-form-7', 123 )
        );
        $this->assertSame(
            array(
                'your-email'   => array(
                    'included' => true,
                    'target'   => 'email',
                    'label'    => 'your-email',
                ),
                'your-name'    => array(
                    'included' => true,
                    'target'   => 'full_name',
                    'label'    => '',
                ),
                'your-message' => array(
                    'included' => false,
                    'target'   => '',
                    'label'    => '',
                ),
            ),
            $mapper->get_fields( 'contact-form-7', '123' )
        );
        $this->assertFalse( $mapper->is_form_rejected( 'contact-form-7', '123' ) );
        $this->assertSame( array(), $mapper->get_mappings( 'contact-form-7', '999' ) );
        $this->assertSame( array(), $mapper->get_fields( 'ninja-forms', '123' ) );
        $this->assertFalse( $mapper->is_form_rejected( 'ninja-forms', '123' ) );
    }

    /**
     * Saving replaces the form's previous mapping with a decision per field:
     * a valid target includes the field, '' excludes it, and an invalid
     * target or an empty field id gets no decision. Labels are kept for the
     * fields with a decision.
     *
     * @return void
     */
    public function testSaveStoresADecisionPerField() {
        $this->expect_quality_cache_cleared( 1 );
        $store  = new MockFormFieldMappingStore( $this->cf7_store() );
        $mapper = new FormFieldMapper( $store );

        $this->assertTrue(
            $mapper->save_form_mapping(
                'contact-form-7',
                123,
                'Renamed form',
                array(
                    'tel'     => 'phone',
                    'note'    => '',
                    'company' => 'company_name',
                    ''        => 'email',
                ),
                array(
                    'tel'     => ' Phone ',
                    'note'    => '',
                    'company' => 'Company',
                )
            )
        );

        $this->assertSame(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'Renamed form',
                        'rejected'   => false,
                        'fields'     => array(
                            'tel'  => array(
                                'included' => true,
                                'target'   => 'phone',
                                'label'    => 'Phone',
                            ),
                            'note' => array( 'included' => false ),
                        ),
                    ),
                ),
            ),
            $store->data
        );
    }

    /**
     * A mapping that includes no field is saved as a rejection, flagged on
     * the form: it then sends no customer information instead of using
     * automatic detection.
     *
     * @return void
     */
    public function testSaveWithNoIncludedFieldIsARejection() {
        $this->expect_quality_cache_cleared( 1 );
        $store  = new MockFormFieldMappingStore( $this->cf7_store() );
        $mapper = new FormFieldMapper( $store );

        $mapper->save_form_mapping(
            'contact-form-7',
            '123',
            'Contact form 1',
            array(
                'your-email' => '',
                'your-name'  => '',
            )
        );

        $this->assertTrue( $mapper->has_form_mapping( 'contact-form-7', '123' ) );
        $this->assertTrue( $mapper->is_form_rejected( 'contact-form-7', '123' ) );
        $this->assertSame( array(), $mapper->get_mappings( 'contact-form-7', '123' ) );
        $this->assertCount( 2, $mapper->get_fields( 'contact-form-7', '123' ) );
        $this->assertFalse( $mapper->has_form_mapping( 'contact-form-7', '999' ) );
    }

    /**
     * Deleting removes only the given form; deleting something that is not
     * saved succeeds without writing (nor clearing the cached Lead event
     * quality).
     *
     * @return void
     */
    public function testDelete() {
        $this->expect_quality_cache_cleared( 1 );
        $data                                = $this->cf7_store();
        $data['contact-form-7']['456']       = array(
            'form_title' => 'Other',
            'fields'     => array(),
        );
        $data['ninja-forms']['1']['fields']   = array();
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
     * A failed write reports failure and keeps the cached Lead event quality,
     * as the mappings did not change.
     *
     * @return void
     */
    public function testFailedWriteKeepsTheQualityCache() {
        $this->expect_quality_cache_cleared( 0 );
        $store              = new MockFormFieldMappingStore( $this->cf7_store() );
        $store->fail_writes = true;
        $mapper             = new FormFieldMapper( $store );

        $this->assertFalse( $mapper->save_form_mapping( 'contact-form-7', '123', 'Contact form 1', array( 'email' => 'email' ) ) );
        $this->assertFalse( $mapper->delete_form_mapping( 'contact-form-7', '123' ) );
    }

    /**
     * Expects the cached Lead event quality to be dropped a number of times:
     * each change to the mappings drops it.
     *
     * @param int $times Expected calls.
     * @return void
     */
    private function expect_quality_cache_cleared( $times ) {
        \WP_Mock::userFunction(
            'delete_transient',
            array(
                'args'  => array( LeadEventQuality::CACHE_KEY ),
                'times' => $times,
            )
        );
    }
}
