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
use FacebookPixelPlugin\Core\FormFieldMappingStore;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FormFieldMapperTest class.
 *
 * The storage seam is a PHPUnit mock of FormFieldMappingStore (our own
 * interface), so these are plain unit tests with no WordPress option mocking.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FormFieldMapperTest extends FacebookWordpressTestBase {

    /**
     * Builds a mapper backed by a mock store whose read() returns $stored.
     *
     * @param array $stored The contents read() should return.
     * @return array An array of [ FormFieldMapper, store mock ].
     */
    private function makeMapper( $stored = array() ) {
        $store = $this->createMock( FormFieldMappingStore::class );
        $store->method( 'read' )->willReturn( $stored );
        $mapper = new FormFieldMapper( $store );
        return array( $mapper, $store );
    }

    /**
     * get_all returns whatever the store reads.
     *
     * @return void
     */
    public function testGetAllReturnsStoreContents() {
        list( $mapper ) = $this->makeMapper();
        $this->assertSame( array(), $mapper->get_all() );
    }

    /**
     * get_mappings returns the stored mappings for a form.
     *
     * @return void
     */
    public function testGetMappingsReturnsStoredMappings() {
        list( $mapper ) = $this->makeMapper(
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

        $this->assertEquals(
            array(
                'FN_1' => 'first_name',
                'EM_2' => 'email',
            ),
            $mapper->get_mappings( 'contact-form-7', '123' )
        );
        $this->assertSame(
            array(),
            $mapper->get_mappings( 'contact-form-7', '999' )
        );
    }

    /**
     * save_form_mapping writes an upserted entry and drops invalid targets.
     *
     * @return void
     */
    public function testSaveFormMappingUpsertsAndDropsInvalid() {
        list( $mapper, $store ) = $this->makeMapper();

        $store->expects( $this->once() )
            ->method( 'write' )
            ->with(
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
                )
            );

        $mapper->save_form_mapping(
            'wpforms-lite',
            7,
            'Contact',
            array(
                '1' => 'email',
                '2' => 'first_name',
                '3' => 'totally_invalid_target',
            )
        );
    }

    /**
     * Re-saving the same form writes a replaced entry (one mapping per form).
     *
     * @return void
     */
    public function testSaveFormMappingReplacesExistingForm() {
        list( $mapper, $store ) = $this->makeMapper(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'old title',
                        'mappings'   => array( 'OLD' => 'email' ),
                    ),
                ),
            )
        );

        $store->expects( $this->once() )
            ->method( 'write' )
            ->with(
                array(
                    'contact-form-7' => array(
                        '123' => array(
                            'form_title' => 'new title',
                            'mappings'   => array( 'NEW' => 'phone' ),
                        ),
                    ),
                )
            );

        $mapper->save_form_mapping(
            'contact-form-7',
            '123',
            'new title',
            array( 'NEW' => 'phone' )
        );
    }

    /**
     * Saving an empty/all-invalid mapping writes the entry removed.
     *
     * @return void
     */
    public function testSaveEmptyMappingRemovesEntry() {
        list( $mapper, $store ) = $this->makeMapper(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'wform1',
                        'mappings'   => array( 'FN_1' => 'first_name' ),
                    ),
                ),
            )
        );

        $store->expects( $this->once() )
            ->method( 'write' )
            ->with( array() );

        $mapper->save_form_mapping(
            'contact-form-7',
            '123',
            'wform1',
            array( 'X' => 'invalid' )
        );
    }

    /**
     * delete_form_mapping writes the store with the form slot removed.
     *
     * @return void
     */
    public function testDeleteFormMapping() {
        list( $mapper, $store ) = $this->makeMapper(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'wform1',
                        'mappings'   => array( 'FN_1' => 'first_name' ),
                    ),
                ),
            )
        );

        $store->expects( $this->once() )
            ->method( 'write' )
            ->with( array() );

        $mapper->delete_form_mapping( 'contact-form-7', '123' );
    }

    /**
     * get_flat_list flattens the store into UI rows.
     *
     * @return void
     */
    public function testGetFlatList() {
        list( $mapper ) = $this->makeMapper(
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

        $rows = $mapper->get_flat_list();

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
        list( $mapper ) = $this->makeMapper(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'wform1',
                        'mappings'   => array(
                            'FN_1'  => 'first_name',
                            'EM_2'  => 'email',
                            'EMPTY' => 'phone',
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

        $resolved = $mapper->resolve(
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
        list( $mapper ) = $this->makeMapper(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'wform1',
                        'mappings'   => array( 'NAME' => 'full_name' ),
                    ),
                ),
            )
        );

        $resolved = $mapper->resolve(
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
        list( $mapper ) = $this->makeMapper();

        $resolved = $mapper->resolve(
            'contact-form-7',
            '123',
            function ( $field_id ) {
                return 'value';
            }
        );

        $this->assertSame( array(), $resolved );
    }
}
