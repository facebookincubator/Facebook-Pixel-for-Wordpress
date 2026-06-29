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
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FormFieldMapperTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FormFieldMapperTest extends FacebookWordpressTestBase {

    /**
     * Captured value passed to the last update_option call.
     *
     * @var mixed
     */
    private $saved_option;

    /**
     * Mocks get_option to return $store and update_option to capture writes.
     *
     * @param array $store The mapping store to return from get_option.
     * @return void
     */
    private function mockOptionStore( $store ) {
        $this->saved_option = null;
        \WP_Mock::userFunction(
            'get_option',
            array(
                'return' => function ( $key, $default = false ) use ( $store ) {
                    return $store;
                },
            )
        );
        \WP_Mock::userFunction(
            'update_option',
            array(
                'return' => function ( $key, $value ) {
                    $this->saved_option = $value;
                    return true;
                },
            )
        );
    }

    /**
     * get_all returns an empty array when the option is not an array.
     *
     * @return void
     */
    public function testGetAllReturnsArrayWhenOptionMissing() {
        \WP_Mock::userFunction(
            'get_option',
            array( 'return' => false )
        );

        $this->assertSame( array(), FormFieldMapper::get_all() );
    }

    /**
     * get_mappings returns the stored mappings for a form.
     *
     * @return void
     */
    public function testGetMappingsReturnsStoredMappings() {
        $this->mockOptionStore(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'wform1',
                        'mappings'   => array(
                            'FN_1' => 'first_name',
                            'EM_2' => 'email',
                        ),
                    ),
                ),
            )
        );

        $mappings = FormFieldMapper::get_mappings( 'contact-form-7', '123' );

        $this->assertEquals(
            array(
                'FN_1' => 'first_name',
                'EM_2' => 'email',
            ),
            $mappings
        );
        $this->assertSame(
            array(),
            FormFieldMapper::get_mappings( 'contact-form-7', '999' )
        );
    }

    /**
     * save_form_mapping upserts the entry and drops invalid targets.
     *
     * @return void
     */
    public function testSaveFormMappingUpsertsAndDropsInvalid() {
        $this->mockOptionStore( array() );

        FormFieldMapper::save_form_mapping(
            'wpforms-lite',
            7,
            'Contact',
            array(
                '1' => 'email',
                '2' => 'first_name',
                '3' => 'totally_invalid_target',
            )
        );

        $this->assertEquals(
            array(
                'wpforms-lite' => array(
                    '7' => array(
                        'form_title' => 'Contact',
                        'mappings'   => array(
                            '1' => 'email',
                            '2' => 'first_name',
                        ),
                    ),
                ),
            ),
            $this->saved_option
        );
    }

    /**
     * Re-saving the same form replaces the entry (one mapping per form).
     *
     * @return void
     */
    public function testSaveFormMappingReplacesExistingForm() {
        $this->mockOptionStore(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'old title',
                        'mappings'   => array( 'OLD' => 'email' ),
                    ),
                ),
            )
        );

        FormFieldMapper::save_form_mapping(
            'contact-form-7',
            '123',
            'new title',
            array( 'NEW' => 'phone' )
        );

        $this->assertEquals(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'new title',
                        'mappings'   => array( 'NEW' => 'phone' ),
                    ),
                ),
            ),
            $this->saved_option
        );
    }

    /**
     * Saving an empty/all-invalid mapping removes any existing entry.
     *
     * @return void
     */
    public function testSaveEmptyMappingRemovesEntry() {
        $this->mockOptionStore(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'wform1',
                        'mappings'   => array( 'FN_1' => 'first_name' ),
                    ),
                ),
            )
        );

        FormFieldMapper::save_form_mapping(
            'contact-form-7',
            '123',
            'wform1',
            array( 'X' => 'invalid' )
        );

        $this->assertSame( array(), $this->saved_option );
    }

    /**
     * delete_form_mapping removes the form slot and prunes empty integration.
     *
     * @return void
     */
    public function testDeleteFormMapping() {
        $this->mockOptionStore(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'wform1',
                        'mappings'   => array( 'FN_1' => 'first_name' ),
                    ),
                ),
            )
        );

        FormFieldMapper::delete_form_mapping( 'contact-form-7', '123' );

        $this->assertSame( array(), $this->saved_option );
    }

    /**
     * get_flat_list flattens the store into UI rows.
     *
     * @return void
     */
    public function testGetFlatList() {
        $this->mockOptionStore(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'wform1',
                        'mappings'   => array(
                            'FN_1' => 'first_name',
                            'EM_2' => 'email',
                        ),
                    ),
                ),
                'ninja-forms'    => array(
                    '5' => array(
                        'form_title' => 'Lead',
                        'mappings'   => array( 'email_1' => 'email' ),
                    ),
                ),
            )
        );

        $rows = FormFieldMapper::get_flat_list();

        $this->assertCount( 2, $rows );
        $this->assertEquals(
            array(
                'tracking_name' => 'contact-form-7',
                'form_id'       => '123',
                'form_title'    => 'wform1',
                'mapped_count'  => 2,
            ),
            $rows[0]
        );
        $this->assertEquals( 'ninja-forms', $rows[1]['tracking_name'] );
        $this->assertEquals( 1, $rows[1]['mapped_count'] );
    }

    /**
     * resolve maps submitted values to standard keys, skipping empties.
     *
     * @return void
     */
    public function testResolveMapsValues() {
        $this->mockOptionStore(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'wform1',
                        'mappings'   => array(
                            'FN_1'   => 'first_name',
                            'EM_2'   => 'email',
                            'EMPTY'  => 'phone',
                        ),
                    ),
                ),
            )
        );

        $submitted = array(
            'FN_1'  => 'Ada',
            'EM_2'  => 'ada@example.com',
            'EMPTY' => '',
        );

        $resolved = FormFieldMapper::resolve(
            'contact-form-7',
            '123',
            function ( $field_id ) use ( $submitted ) {
                return isset( $submitted[ $field_id ] )
                    ? $submitted[ $field_id ] : null;
            }
        );

        $this->assertEquals(
            array(
                'first_name' => 'Ada',
                'email'      => 'ada@example.com',
            ),
            $resolved
        );
    }

    /**
     * resolve splits a full_name target into first and last name.
     *
     * @return void
     */
    public function testResolveSplitsFullName() {
        $this->mockOptionStore(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'wform1',
                        'mappings'   => array( 'NAME' => 'full_name' ),
                    ),
                ),
            )
        );

        $resolved = FormFieldMapper::resolve(
            'contact-form-7',
            '123',
            function ( $field_id ) {
                return 'Ada Lovelace';
            }
        );

        $this->assertEquals(
            array(
                'first_name' => 'Ada',
                'last_name'  => 'Lovelace',
            ),
            $resolved
        );
    }

    /**
     * resolve returns an empty array when no mapping exists.
     *
     * @return void
     */
    public function testResolveNoMapping() {
        $this->mockOptionStore( array() );

        $resolved = FormFieldMapper::resolve(
            'contact-form-7',
            '123',
            function ( $field_id ) {
                return 'value';
            }
        );

        $this->assertSame( array(), $resolved );
    }
}
