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
     * @return TrackableLeadFormIntegrationBase
     */
    private function integration( $label, $active, array $forms = array(), array $suggested = array() ) {
        $integration = \Mockery::mock( TrackableLeadFormIntegrationBase::class );
        $integration->shouldReceive( 'get_integration_label' )->andReturn( $label );
        $integration->shouldReceive( 'is_field_mapping_available' )->andReturn( $active );
        $integration->shouldReceive( 'get_forms' )->andReturn( $forms );
        foreach ( $suggested as $form_id => $mapping ) {
            $integration->shouldReceive( 'suggest_field_mapping' )->with( (string) $form_id )->andReturn( $mapping );
        }
        return $integration;
    }

    /**
     * Every form of an active plugin is listed: unsaved forms with what
     * automatic detection would send, saved ones with their mapping, rejected
     * ones with nothing. Saved mappings of a gone form or an inactive plugin
     * follow; an inactive plugin's forms are not listed otherwise.
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
                );
            },
            $rows
        );
        $this->assertSame(
            array(
                array( 'Contact Form 7', '1', 'automatic', array( 'full_name', 'email' ), true, false ),
                array( 'Contact Form 7', '2', 'saved', array( 'email' ), true, false ),
                array( 'Contact Form 7', '3', 'rejected', array(), true, false ),
                array( 'Contact Form 7', '99', 'saved', array( 'phone' ), true, true ),
                array( 'Ninja Forms', '4', 'saved', array( 'email' ), false, false ),
            ),
            $summary
        );
        $this->assertSame( 'Deleted form', $rows[3]['form_title'] );
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
