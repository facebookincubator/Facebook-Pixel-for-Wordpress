<?php
/**
 * Facebook Pixel Plugin FormFieldMappingRecorderTest class.
 *
 * Covers the AJAX handlers that back the admin Field Mapping screen.
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
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FormFieldMappingRecorderTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FormFieldMappingRecorderTest extends FacebookWordpressTestBase {

    /**
     * Mocks the WordPress functions used by the mapping handlers.
     *
     * @param bool $can_manage Whether current_user_can returns true.
     * @return void
     */
    private function mockMappingFunctions( $can_manage = true ) {
        \WP_Mock::userFunction(
            'current_user_can',
            array( 'return' => $can_manage )
        );
        \WP_Mock::userFunction(
            'check_admin_referer',
            array( 'return' => true )
        );
        \WP_Mock::userFunction(
            'wp_send_json',
            array( 'return' => true )
        );
        \WP_Mock::userFunction(
            'sanitize_text_field',
            array(
                'return' => function ( $v ) {
                    return $v;
                },
            )
        );
        \WP_Mock::userFunction(
            'sanitize_textarea_field',
            array(
                'return' => function ( $v ) {
                    return $v;
                },
            )
        );
        \WP_Mock::userFunction(
            'get_option',
            array( 'return' => array() )
        );
        \WP_Mock::userFunction(
            'update_option',
            array( 'return' => true )
        );
    }

    /**
     * The four mapping AJAX actions are registered.
     *
     * @return void
     */
    public function testMappingAjaxActionsAdded() {
        $recorder = new FacebookWordpressSettingsRecorder();
        \WP_Mock::expectActionAdded(
            'wp_ajax_' . FacebookPluginConfig::GET_MAPPING_FORMS_ACTION_NAME,
            array( $recorder, 'get_mapping_forms' )
        );
        \WP_Mock::expectActionAdded(
            'wp_ajax_' . FacebookPluginConfig::GET_MAPPING_FIELDS_ACTION_NAME,
            array( $recorder, 'get_mapping_fields' )
        );
        \WP_Mock::expectActionAdded(
            'wp_ajax_' . FacebookPluginConfig::SAVE_FIELD_MAPPING_ACTION_NAME,
            array( $recorder, 'save_field_mapping' )
        );
        \WP_Mock::expectActionAdded(
            'wp_ajax_' . FacebookPluginConfig::DELETE_FIELD_MAPPING_ACTION_NAME,
            array( $recorder, 'delete_field_mapping' )
        );

        $recorder->init();

        $this->assertConditionsMet();
    }

    /**
     * Unauthorized users are rejected by get_mapping_forms.
     *
     * @return void
     */
    public function testGetMappingFormsUnauthorized() {
        $this->mockMappingFunctions( false );
        $recorder = new FacebookWordpressSettingsRecorder();

        $res = $recorder->get_mapping_forms();

        $this->assertFalse( $res['success'] );
    }

    /**
     * An unknown integration is rejected.
     *
     * @return void
     */
    public function testGetMappingFormsInvalidIntegration() {
        $this->mockMappingFunctions();
        $_POST['integration'] = 'not-a-real-integration';
        $recorder             = new FacebookWordpressSettingsRecorder();

        $res = $recorder->get_mapping_forms();

        $this->assertFalse( $res['success'] );
    }

    /**
     * A valid integration returns a forms list (empty when plugin inactive).
     *
     * @return void
     */
    public function testGetMappingFormsSuccess() {
        $this->mockMappingFunctions();
        $_POST['integration'] = 'contact-form-7';
        $recorder             = new FacebookWordpressSettingsRecorder();

        $res = $recorder->get_mapping_forms();

        $this->assertTrue( $res['success'] );
        $this->assertArrayHasKey( 'forms', $res['msg'] );
        $this->assertSame( array(), $res['msg']['forms'] );
    }

    /**
     * get_mapping_fields requires a form id.
     *
     * @return void
     */
    public function testGetMappingFieldsRequiresFormId() {
        $this->mockMappingFunctions();
        $_POST['integration'] = 'contact-form-7';
        $_POST['form_id']     = '';
        $recorder             = new FacebookWordpressSettingsRecorder();

        $res = $recorder->get_mapping_fields();

        $this->assertFalse( $res['success'] );
    }

    /**
     * get_mapping_fields returns fields plus the saved mapping.
     *
     * @return void
     */
    public function testGetMappingFieldsSuccess() {
        $this->mockMappingFunctions();
        $_POST['integration'] = 'contact-form-7';
        $_POST['form_id']     = '123';
        $recorder             = new FacebookWordpressSettingsRecorder();

        $res = $recorder->get_mapping_fields();

        $this->assertTrue( $res['success'] );
        $this->assertArrayHasKey( 'fields', $res['msg'] );
        $this->assertArrayHasKey( 'mapping', $res['msg'] );
    }

    /**
     * save_field_mapping persists a valid mapping and returns the list.
     *
     * @return void
     */
    public function testSaveFieldMappingPersists() {
        $this->mockMappingFunctions();
        $_POST['integration'] = 'contact-form-7';
        $_POST['form_id']     = '123';
        $_POST['form_title']  = 'wform1';
        $_POST['mapping']     = json_encode(
            array(
                'FN_1' => 'first_name',
                'EM_2' => 'email',
            )
        );
        $recorder = new FacebookWordpressSettingsRecorder();

        $res = $recorder->save_field_mapping();

        $this->assertTrue( $res['success'] );
        $this->assertArrayHasKey( 'list', $res['msg'] );
    }

    /**
     * save_field_mapping rejects an unknown integration.
     *
     * @return void
     */
    public function testSaveFieldMappingInvalidIntegration() {
        $this->mockMappingFunctions();
        $_POST['integration'] = 'bogus';
        $_POST['form_id']     = '123';
        $_POST['mapping']     = json_encode( array( 'FN_1' => 'first_name' ) );
        $recorder             = new FacebookWordpressSettingsRecorder();

        $res = $recorder->save_field_mapping();

        $this->assertFalse( $res['success'] );
    }

    /**
     * delete_field_mapping succeeds for a valid request.
     *
     * @return void
     */
    public function testDeleteFieldMapping() {
        $this->mockMappingFunctions();
        $_POST['integration'] = 'contact-form-7';
        $_POST['form_id']     = '123';
        $recorder             = new FacebookWordpressSettingsRecorder();

        $res = $recorder->delete_field_mapping();

        $this->assertTrue( $res['success'] );
        $this->assertArrayHasKey( 'list', $res['msg'] );
    }

    /**
     * delete_field_mapping requires integration and form id.
     *
     * @return void
     */
    public function testDeleteFieldMappingRequiresParams() {
        $this->mockMappingFunctions();
        $_POST['integration'] = '';
        $_POST['form_id']     = '';
        $recorder             = new FacebookWordpressSettingsRecorder();

        $res = $recorder->delete_field_mapping();

        $this->assertFalse( $res['success'] );
    }
}
