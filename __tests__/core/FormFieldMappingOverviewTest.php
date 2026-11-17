<?php
/**
 * Facebook Pixel Plugin FormFieldMappingOverviewTest class.
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

use FacebookPixelPlugin\Core\FormFieldMappingOverview;
use FacebookPixelPlugin\Integration\TrackableLeadFormIntegrationBase;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FormFieldMappingOverviewTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FormFieldMappingOverviewTest extends FacebookWordpressTestBase {

    /**
     * A supported integration double.
     *
     * @param string $label     Label.
     * @param bool   $active    Whether its plugin is active.
     * @param array  $forms     Forms it lists.
     * @param array  $suggested form_id => suggested mapping.
     * @param array  $gone      form_id => mapped field ids the form no longer has.
     * @return TrackableLeadFormIntegrationBase
     */
    private function integration( $label, $active, array $forms = array(), array $suggested = array(), array $gone = array() ) {
        $integration = \Mockery::mock( TrackableLeadFormIntegrationBase::class );
        $integration->shouldReceive( 'get_integration_label' )->andReturn( $label );
        $integration->shouldReceive( 'is_field_mapping_available' )->andReturn( $active );
        $integration->shouldReceive( 'get_forms' )->andReturn( $forms );
        $integration->shouldReceive( 'get_form_edit_url' )->andReturnUsing(
            function ( $form_id ) use ( $label ) {
                return 'https://example.com/edit/' . rawurlencode( $label ) . '/' . $form_id;
            }
        );
        $integration->shouldReceive( 'get_missing_mapped_fields' )->andReturnUsing(
            function ( $form_id, $mapping ) use ( $gone ) {
                $ids = isset( $gone[ $form_id ] ) ? $gone[ $form_id ] : array();
                return array_intersect_key( $mapping, array_flip( $ids ) );
            }
        );
        foreach ( $suggested as $form_id => $mapping ) {
            $integration->shouldReceive( 'suggest_field_mapping' )->with( (string) $form_id )->andReturn( $mapping );
        }
        return $integration;
    }

    /**
     * Every form of an active plugin is listed: unsaved forms with what
     * automatic detection would send, saved ones with their mapping, rejected
     * ones with nothing, each linked to the plugin's editor. Saved mappings of
     * a gone form or an inactive plugin follow, with no editor link; an
     * inactive plugin's forms are not listed otherwise.
     *
     * @return void
     */
    public function testBuild() {
        $integrations = array(
            'contact-form-7' => $this->integration(
                'Contact Form 7',
                true,
                array(
                    array(
                        'id'    => '1',
                        'title' => 'Contact',
                    ),
                    array(
                        'id'    => '2',
                        'title' => 'Quote',
                    ),
                    array(
                        'id'    => '3',
                        'title' => 'Newsletter',
                    ),
                ),
                array(
                    '1' => array(
                        'your-name'  => 'full_name',
                        'your-email' => 'email',
                    ),
                )
            ),
            'ninja-forms'    => $this->integration( 'Ninja Forms', false ),
        );
        $mapper       = $this->make_field_mapper(
            array(
                'contact-form-7' => array(
                    '2'  => array(
                        'form_title' => 'Quote',
                        'mappings'   => array(
                            'work'  => 'email',
                            'other' => 'email',
                        ),
                    ),
                    '3'  => array(
                        'form_title' => 'Newsletter',
                        'mappings'   => array(),
                    ),
                    '99' => array(
                        'form_title' => 'Deleted form',
                        'mappings'   => array( 'x' => 'phone' ),
                    ),
                ),
                'ninja-forms'    => array(
                    '4' => array(
                        'form_title' => 'Old',
                        'mappings'   => array( 'email_1' => 'email' ),
                    ),
                ),
            )
        );

        $rows = FormFieldMappingOverview::build( $integrations, $mapper );

        $summary = array_map(
            function ( $row ) {
                return array(
                    $row['integration_label'],
                    $row['form_id'],
                    $row['status'],
                    $row['targets'],
                    $row['active'],
                    $row['missing'],
                    $row['edit_url'],
                );
            },
            $rows
        );
        $this->assertSame(
            array(
                array( 'Contact Form 7', '1', 'automatic', array( 'full_name', 'email' ), true, false, 'https://example.com/edit/Contact%20Form%207/1' ),
                array( 'Contact Form 7', '2', 'saved', array( 'email' ), true, false, 'https://example.com/edit/Contact%20Form%207/2' ),
                array( 'Contact Form 7', '3', 'rejected', array(), true, false, 'https://example.com/edit/Contact%20Form%207/3' ),
                array( 'Contact Form 7', '99', 'saved', array( 'phone' ), true, true, '' ),
                array( 'Ninja Forms', '4', 'saved', array( 'email' ), false, false, '' ),
            ),
            $summary
        );
        $this->assertSame( 'Deleted form', $rows[3]['form_title'] );
    }

    /**
     * Mapped fields a form no longer has (deleted, or renamed in a plugin
     * that identifies fields by name) send nothing: the row lists only the
     * targets still sent and names the missing fields by the label they had
     * when the mapping was saved ('' when that is unknown). A saved mapping
     * whose fields are all gone stays saved, not rejected.
     *
     * @return void
     */
    public function testMissingMappedFieldsAreFlagged() {
        $integrations = array(
            'contact-form-7' => $this->integration(
                'Contact Form 7',
                true,
                array(
                    array(
                        'id'    => '1',
                        'title' => 'Contact',
                    ),
                    array(
                        'id'    => '2',
                        'title' => 'Quote',
                    ),
                ),
                array(),
                array(
                    '1' => array( 'your-email' ),
                    '2' => array( 'work' ),
                )
            ),
        );
        $mapper       = $this->make_field_mapper(
            array(
                'contact-form-7' => array(
                    '1' => array(
                        'form_title'   => 'Contact',
                        'mappings'     => array(
                            'your-name'  => 'full_name',
                            'your-email' => 'email',
                        ),
                        'field_labels' => array(
                            'your-name'  => 'Your name',
                            'your-email' => 'Your email',
                        ),
                    ),
                    // Saved before field labels were kept.
                    '2' => array(
                        'form_title' => 'Quote',
                        'mappings'   => array( 'work' => 'email' ),
                    ),
                ),
            )
        );

        $rows = FormFieldMappingOverview::build( $integrations, $mapper );

        $this->assertSame( 'saved', $rows[0]['status'] );
        $this->assertSame( array( 'full_name' ), $rows[0]['targets'] );
        $this->assertSame(
            array(
                array(
                    'id'    => 'your-email',
                    'label' => 'Your email',
                ),
            ),
            $rows[0]['missing_fields']
        );
        $this->assertSame( 'saved', $rows[1]['status'] );
        $this->assertSame( array(), $rows[1]['targets'] );
        $this->assertSame(
            array(
                array(
                    'id'    => 'work',
                    'label' => '',
                ),
            ),
            $rows[1]['missing_fields']
        );
    }

    /**
     * A saved mapping of an integration that is no longer registered is
     * listed under its stored name so it can be reset.
     *
     * @return void
     */
    public function testUnregisteredIntegration() {
        $mapper = $this->make_field_mapper(
            array( 'gone-forms' => array( '7' => array( 'mappings' => array( 'a' => 'email' ) ) ) )
        );

        $rows = FormFieldMappingOverview::build( array(), $mapper );

        $this->assertSame( 'gone-forms', $rows[0]['integration_label'] );
        $this->assertFalse( $rows[0]['active'] );
    }
}
