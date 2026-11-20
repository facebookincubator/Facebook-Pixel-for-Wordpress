<?php
/**
 * Facebook Pixel Plugin FormFieldMappingRecorderTest class.
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

use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Core\FacebookWordpressSettingsRecorder;
use FacebookPixelPlugin\Core\FormFieldMapper;
use FacebookPixelPlugin\Integration\TrackableLeadFormIntegrationBase;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Tests\Mocks\MockFormFieldMappingStore;

/**
 * The form field mapping AJAX endpoints of FacebookWordpressSettingsRecorder.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FormFieldMappingRecorderTest extends FacebookWordpressTestBase {

    /**
     * Mapping storage shared with the recorder under test.
     *
     * @var MockFormFieldMappingStore
     */
    private $store;

    /**
     * How many times the cached field mapping review was cleared.
     *
     * @var int
     */
    private $invalidations = 0;

    /**
     * Mocks the WordPress functions the endpoints call.
     *
     * @param bool $can_manage Whether the user may manage options.
     * @return void
     */
    private function mock_wp( $can_manage = true ) {
        \WP_Mock::userFunction( 'current_user_can', array( 'return' => $can_manage ) );
        \WP_Mock::userFunction( 'check_admin_referer', array( 'return' => true ) );
        \WP_Mock::userFunction( 'wp_send_json', array( 'return' => null ) );
        $this->invalidations = 0;
        \WP_Mock::userFunction(
            'delete_transient',
            array(
                'return' => function ( $key ) {
                    if ( FacebookPluginConfig::FIELD_MAPPING_REVIEW_TRANSIENT === $key ) {
                        ++$this->invalidations;
                    }
                    return true;
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
    }

    /**
     * Builds the recorder with one active integration, 'test-forms', whose
     * form 5 ("Contact") has the fields 'email' and 'name'.
     *
     * @param array $saved  Initial mapping store contents.
     * @param bool  $active Whether the form plugin is active.
     * @return FacebookWordpressSettingsRecorder
     */
    private function make_recorder( array $saved = array(), $active = true ) {
        // Partial, so get_missing_mapped_fields() runs on the fields below.
        $integration = \Mockery::mock( TrackableLeadFormIntegrationBase::class )->makePartial();
        $integration->shouldReceive( 'is_field_mapping_available' )->andReturn( $active );
        $integration->shouldReceive( 'get_integration_label' )->andReturn( 'Test Forms' );
        $integration->shouldReceive( 'get_form_edit_url' )->andReturn( 'https://example.com/wp-admin/admin.php?page=test-forms&form=5' );
        $integration->shouldReceive( 'get_forms' )->andReturn(
            array(
                array(
                    'id'    => '5',
                    'title' => 'Contact',
                ),
            )
        );
        $integration->shouldReceive( 'suggest_field_mapping' )->with( '5' )->andReturn( array( 'email' => 'email' ) );
        $integration->shouldReceive( 'get_form_fields' )->with( '5' )->andReturn(
            array(
                array(
                    'id'    => 'email',
                    'label' => 'Email',
                    'type'  => 'email',
                ),
                array(
                    'id'    => 'name',
                    'label' => 'Name',
                    'type'  => 'text',
                ),
            )
        );
        $this->store = new MockFormFieldMappingStore( self::field_decisions( $saved ) );

        return new FacebookWordpressSettingsRecorder(
            function () use ( $integration ) {
                return array( 'test-forms' => $integration );
            },
            new FormFieldMapper( $this->store )
        );
    }

    /**
     * The three endpoints are registered as admin AJAX actions.
     *
     * @return void
     */
    public function testActionsAdded() {
        $recorder = $this->make_recorder();
        $actions  = array(
            FacebookPluginConfig::GET_MAPPING_FIELDS_ACTION_NAME   => 'get_mapping_fields',
            FacebookPluginConfig::SAVE_FIELD_MAPPING_ACTION_NAME   => 'save_field_mapping',
            FacebookPluginConfig::DELETE_FIELD_MAPPING_ACTION_NAME => 'delete_field_mapping',
        );
        foreach ( $actions as $action => $method ) {
            \WP_Mock::expectActionAdded( 'wp_ajax_' . $action, array( $recorder, $method ) );
        }
        $recorder->init();
        $this->assertHooksAdded();
    }

    /**
     * Users who cannot manage options are refused and nothing is saved.
     *
     * @return void
     */
    public function testUnauthorizedUserIsRefused() {
        $this->mock_wp( false );
        $recorder = $this->make_recorder();
        $_POST    = array(
            'integration' => 'test-forms',
            'form_id'     => '5',
            'mapping'     => '{"email":"email"}',
        );

        $this->assertFalse( $recorder->get_mapping_fields()['success'] );
        $this->assertFalse( $recorder->save_field_mapping()['success'] );
        $this->assertSame( 0, $this->store->writes );
    }

    /**
     * Only an active supported integration can be edited: an unknown name or
     * an inactive plugin is refused.
     *
     * @return void
     */
    public function testOnlyActiveIntegrationsAreAccepted() {
        $this->mock_wp();

        $_POST = array(
            'integration' => 'FacebookPixelPlugin\\Core\\FacebookWordpressOptions',
            'form_id'     => '5',
        );
        $this->assertFalse( $this->make_recorder()->get_mapping_fields()['success'] );

        $_POST = array(
            'integration' => 'test-forms',
            'form_id'     => '5',
        );
        $this->assertFalse( $this->make_recorder( array(), false )->get_mapping_fields()['success'] );
        $this->assertTrue( $this->make_recorder()->get_mapping_fields()['success'] );
    }

    /**
     * A form's fields come back with its saved mapping and no suggestion.
     *
     * @return void
     */
    public function testGetMappingFields() {
        $this->mock_wp();
        $recorder = $this->make_recorder(
            array(
                'test-forms' => array(
                    '5' => array(
                        'mappings' => array( 'name' => 'full_name' ),
                        'excluded' => array( 'email' ),
                    ),
                ),
            )
        );
        $_POST    = array(
            'integration' => 'test-forms',
            'form_id'     => '5',
        );

        $response = $recorder->get_mapping_fields();

        $this->assertTrue( $response['success'] );
        $this->assertSame( array( 'email', 'name' ), array_column( $response['msg']['fields'], 'id' ) );
        $this->assertTrue( $response['msg']['saved'] );
        $this->assertSame( array( 'name' => 'full_name' ), $response['msg']['mapping'] );
        $this->assertSame( array(), $response['msg']['suggested'] );
        $this->assertSame( array(), $response['msg']['missing_fields'] );
        $this->assertSame( array(), $response['msg']['new_fields'] );
    }

    /**
     * A form without a saved mapping comes back with the mapping automatic
     * detection suggests, for the admin to review.
     *
     * @return void
     */
    public function testGetMappingFieldsSuggestsWhenNothingIsSaved() {
        $this->mock_wp();
        $recorder = $this->make_recorder();
        $_POST    = array(
            'integration' => 'test-forms',
            'form_id'     => '5',
        );

        $response = $recorder->get_mapping_fields();

        $this->assertFalse( $response['msg']['saved'] );
        $this->assertSame( array(), $response['msg']['mapping'] );
        $this->assertSame( array( 'email' => 'email' ), $response['msg']['suggested'] );
    }

    /**
     * Rejecting saves every field as not sent; the form then counts as saved
     * and rejected, and gets no suggestion.
     *
     * @return void
     */
    public function testRejectSavesAnEmptyMapping() {
        $this->mock_wp();
        $recorder = $this->make_recorder();
        $_POST    = array(
            'integration' => 'test-forms',
            'form_id'     => '5',
            'mapping'     => '{"email":"","name":""}',
        );

        $response = $recorder->save_field_mapping();
        $this->assertTrue( $response['success'] );
        $this->assertTrue( $this->store->data['test-forms']['5']['rejected'] );
        $this->assertSame( 'rejected', $response['msg']['list'][0]['status'] );

        $_POST    = array(
            'integration' => 'test-forms',
            'form_id'     => '5',
        );
        $response = $recorder->get_mapping_fields();
        $this->assertTrue( $response['msg']['saved'] );
        $this->assertSame( array(), $response['msg']['suggested'] );
    }

    /**
     * Saving stores a decision for every field, with its label and the title
     * from the form plugin, and returns the refreshed list.
     *
     * @return void
     */
    public function testSaveFieldMapping() {
        $this->mock_wp();
        $recorder = $this->make_recorder();
        $_POST    = array(
            'integration' => 'test-forms',
            'form_id'     => '5',
            'form_title'  => 'Ignored',
            'mapping'     => json_encode(
                array(
                    'email' => 'email',
                    'name'  => '',
                )
            ),
        );

        $response = $recorder->save_field_mapping();

        $this->assertTrue( $response['success'] );
        $this->assertSame(
            array(
                'test-forms' => array(
                    '5' => array(
                        'form_title' => 'Contact',
                        'rejected'   => false,
                        'fields'     => array(
                            'email' => array(
                                'included' => true,
                                'target'   => 'email',
                                'label'    => 'Email',
                            ),
                            'name'  => array(
                                'included' => false,
                                'label'    => 'Name',
                            ),
                        ),
                    ),
                ),
            ),
            $this->store->data
        );
        // The cached review is cleared, so the admin notice is recomputed.
        $this->assertSame( 1, $this->invalidations );
        // The refreshed table lists the form as saved.
        $this->assertSame( 'Contact', $response['msg']['list'][0]['form_title'] );
        $this->assertSame( 'saved', $response['msg']['list'][0]['status'] );
        $this->assertSame( array( 'email' ), $response['msg']['list'][0]['targets'] );
    }

    /**
     * A mapping with an invalid target is refused without saving.
     *
     * @return void
     */
    public function testSaveRefusesAnInvalidTarget() {
        $this->mock_wp();
        $recorder = $this->make_recorder();
        $_POST    = array(
            'integration' => 'test-forms',
            'form_id'     => '5',
            'mapping'     => json_encode(
                array(
                    'email' => 'email',
                    'name'  => 'nickname',
                )
            ),
        );

        $response = $recorder->save_field_mapping();

        $this->assertFalse( $response['success'] );
        $this->assertSame( 'Invalid values', $response['msg'] );
        $this->assertSame( 0, $this->store->writes );
    }

    /**
     * A save must decide every field the form has, and only those. When the
     * form changed while the editor was open (a field was removed, renamed
     * or added), nothing is saved and the editor is told so. In particular
     * a mapping is never stored without its removed fields, which could
     * leave no field sent and silently reject the form, or without a
     * decision for a new field.
     *
     * @return void
     */
    public function testSaveRefusesDecisionsThatDoNotMatchTheForm() {
        $this->mock_wp();
        $recorder = $this->make_recorder(
            array( 'test-forms' => array( '5' => array( 'mappings' => array( 'email' => 'email' ) ) ) )
        );
        $before   = $this->store->data;

        foreach ( array(
            array( 'your-email' => 'email' ),
            array(
                'your-email' => 'email',
                'name'       => '',
            ),
            array( 'email' => 'email' ),
            array(
                'email'   => 'email',
                'name'    => '',
                'unknown' => 'phone',
            ),
            array(),
        ) as $mapping ) {
            $_POST = array(
                'integration' => 'test-forms',
                'form_id'     => '5',
                'mapping'     => json_encode( (object) $mapping ),
            );

            $response = $recorder->save_field_mapping();

            $this->assertFalse( $response['success'] );
            $this->assertSame( array( 'code' => 'form_changed' ), $response['msg'] );
        }
        $this->assertSame( 0, $this->store->writes );
        $this->assertSame( $before, $this->store->data );
        $this->assertSame( 0, $this->invalidations );
    }

    /**
     * The editor gets the saved mapping's fields the form no longer has, with
     * the label they had when the mapping was saved ('' when unknown), so it
     * can ask for them to be mapped again.
     *
     * @return void
     */
    public function testGetMappingFieldsListsMissingMappedFields() {
        $this->mock_wp();
        $recorder = $this->make_recorder(
            array(
                'test-forms' => array(
                    '5' => array(
                        'mappings'     => array(
                            'email'      => 'email',
                            'your-phone' => 'phone',
                            'fax'        => 'phone',
                        ),
                        'field_labels' => array(
                            'email'      => 'Email',
                            'your-phone' => 'Your phone',
                        ),
                    ),
                ),
            )
        );
        $_POST    = array(
            'integration' => 'test-forms',
            'form_id'     => '5',
        );

        $response = $recorder->get_mapping_fields();

        $this->assertSame(
            array(
                array(
                    'id'     => 'your-phone',
                    'label'  => 'Your phone',
                    'target' => 'phone',
                ),
                array(
                    'id'     => 'fax',
                    'label'  => '',
                    'target' => 'phone',
                ),
            ),
            $response['msg']['missing_fields']
        );
        // The form's 'name' field has no saved decision.
        $this->assertSame( array( 'name' ), $response['msg']['new_fields'] );
    }

    /**
     * An unknown form or a malformed mapping is refused without saving.
     *
     * @return void
     */
    public function testSaveRejectsUnknownFormAndMalformedMapping() {
        $this->mock_wp();
        $recorder = $this->make_recorder();

        $_POST = array(
            'integration' => 'test-forms',
            'form_id'     => '6',
            'mapping'     => '{"email":"email"}',
        );
        $this->assertFalse( $recorder->save_field_mapping()['success'] );

        $_POST = array(
            'integration' => 'test-forms',
            'form_id'     => '5',
            'mapping'     => 'not json',
        );
        $this->assertFalse( $recorder->save_field_mapping()['success'] );

        $this->assertSame( 0, $this->store->writes );
    }

    /**
     * A mapping can be deleted even when its form plugin is no longer active.
     *
     * @return void
     */
    public function testDeleteWorksForInactiveIntegration() {
        $this->mock_wp();
        $recorder = $this->make_recorder(
            array(
                'ninja-forms' => array( '2' => array( 'mappings' => array( 'email_1' => 'email' ) ) ),
                'test-forms'  => array( '5' => array( 'mappings' => array( 'email' => 'email' ) ) ),
            )
        );
        $_POST    = array(
            'integration' => 'ninja-forms',
            'form_id'     => '2',
        );

        $response = $recorder->delete_field_mapping();

        $this->assertTrue( $response['success'] );
        $this->assertSame( 1, $this->invalidations );
        $this->assertSame( array( 'test-forms' ), array_keys( $this->store->data ) );
        $this->assertSame( array( 'test-forms' ), array_column( $response['msg']['list'], 'integration_name' ) );
        $this->assertSame( array( 'saved' ), array_column( $response['msg']['list'], 'status' ) );
    }
}

