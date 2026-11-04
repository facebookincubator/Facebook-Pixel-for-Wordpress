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
     * Mocks the WordPress functions the endpoints call.
     *
     * @param bool $can_manage Whether the user may manage options.
     * @return void
     */
    private function mock_wp( $can_manage = true ) {
        \WP_Mock::userFunction( 'current_user_can', array( 'return' => $can_manage ) );
        \WP_Mock::userFunction( 'check_admin_referer', array( 'return' => true ) );
        \WP_Mock::userFunction( 'wp_send_json', array( 'return' => null ) );
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
     * @param array $saved Initial mapping store contents.
     * @return FacebookWordpressSettingsRecorder
     */
    private function make_recorder( array $saved = array() ) {
        $integration = \Mockery::mock( TrackableLeadFormIntegrationBase::class );
        $integration->shouldReceive( 'get_forms' )->andReturn(
            array(
                array(
                    'id'    => '5',
                    'title' => 'Contact',
                ),
            )
        );
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
        $this->store = new MockFormFieldMappingStore( $saved );

        return new FacebookWordpressSettingsRecorder(
            function () use ( $integration ) {
                return array( 'test-forms' => $integration );
            },
            new FormFieldMapper( $this->store )
        );
    }

    /**
     * The four endpoints are registered as admin AJAX actions.
     *
     * @return void
     */
    public function testActionsAdded() {
        $recorder = $this->make_recorder();
        $actions  = array(
            FacebookPluginConfig::GET_MAPPING_FORMS_ACTION_NAME    => 'get_mapping_forms',
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

        $this->assertFalse( $recorder->get_mapping_forms()['success'] );
        $this->assertFalse( $recorder->save_field_mapping()['success'] );
        $this->assertSame( 0, $this->store->writes );
    }

    /**
     * Forms are listed for an active integration; any other name is refused.
     *
     * @return void
     */
    public function testGetMappingForms() {
        $this->mock_wp();
        $recorder = $this->make_recorder();

        $_POST    = array( 'integration' => 'test-forms' );
        $response = $recorder->get_mapping_forms();
        $this->assertTrue( $response['success'] );
        $this->assertSame( '5', $response['msg']['forms'][0]['id'] );

        $_POST = array( 'integration' => 'FacebookPixelPlugin\\Core\\FacebookWordpressOptions' );
        $this->assertFalse( $recorder->get_mapping_forms()['success'] );
    }

    /**
     * A form's fields come back with its saved mapping.
     *
     * @return void
     */
    public function testGetMappingFields() {
        $this->mock_wp();
        $recorder = $this->make_recorder(
            array( 'test-forms' => array( '5' => array( 'mappings' => array( 'name' => 'full_name' ) ) ) )
        );
        $_POST    = array(
            'integration' => 'test-forms',
            'form_id'     => '5',
        );

        $response = $recorder->get_mapping_fields();

        $this->assertTrue( $response['success'] );
        $this->assertSame( array( 'email', 'name' ), array_column( $response['msg']['fields'], 'id' ) );
        $this->assertSame( array( 'name' => 'full_name' ), $response['msg']['mapping'] );
    }

    /**
     * Saving keeps only the form's own fields and valid targets, takes the
     * title from the form plugin, and returns the refreshed list.
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
                    'email'   => 'email',
                    'name'    => 'nickname',
                    'unknown' => 'phone',
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
                        'mappings'   => array( 'email' => 'email' ),
                    ),
                ),
            ),
            $this->store->data
        );
        $this->assertSame( 'Contact', $response['msg']['list'][0]['form_title'] );
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
        $this->assertSame( array( 'test-forms' ), array_keys( $this->store->data ) );
        $this->assertSame( array( 'test-forms' ), array_column( $response['msg']['list'], 'integration_name' ) );
    }
}

