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
use FacebookPixelPlugin\Core\FormFieldMapper;
use FacebookPixelPlugin\Tests\Mocks\InMemoryFormFieldMappingStore;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FormFieldMappingRecorderTest class.
 *
 * The recorder is given a mapper backed by an in-memory store, so the
 * handlers can be tested without WordPress option mocking.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FormFieldMappingRecorderTest extends FacebookWordpressTestBase {

    /**
     * In-memory store backing the injected mapper.
     *
     * @var InMemoryFormFieldMappingStore
     */
    private $store;

    /**
     * Builds a recorder whose mapper is backed by an in-memory store.
     *
     * @param array $seed Initial mapping store contents.
     * @return FacebookWordpressSettingsRecorder
     */
    private function makeRecorder( $seed = array() ) {
        $this->store = new InMemoryFormFieldMappingStore( $seed );
        return new FacebookWordpressSettingsRecorder(
            new FormFieldMapper( $this->store )
        );
    }

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
    }

    /**
     * The four mapping AJAX actions are registered.
     *
     * @return void
     */
    public function testMappingAjaxActionsAdded() {
        $recorder = $this->makeRecorder();
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
        $recorder = $this->makeRecorder();

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
        $recorder             = $this->makeRecorder();

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
        $recorder             = $this->makeRecorder();

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
        $recorder             = $this->makeRecorder();

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
        $recorder             = $this->makeRecorder(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'wform1',
                        'mappings'   => array( 'your-email' => 'email' ),
                    ),
                ),
            )
        );

        $res = $recorder->get_mapping_fields();

        $this->assertTrue( $res['success'] );
        $this->assertArrayHasKey( 'fields', $res['msg'] );
        $this->assertEquals(
            array( 'your-email' => 'email' ),
            $res['msg']['mapping']
        );
    }

    /**
     * save_field_mapping persists a valid mapping to the store.
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
        $recorder = $this->makeRecorder();

        $res = $recorder->save_field_mapping();

        $this->assertTrue( $res['success'] );
        $this->assertArrayHasKey( 'list', $res['msg'] );
        // The mapping was actually written to the store.
        $this->assertEquals(
            array(
                'FN_1' => 'first_name',
                'EM_2' => 'email',
            ),
            $this->store->read()['contact-form-7']['123']['mappings']
        );
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
        $recorder             = $this->makeRecorder();

        $res = $recorder->save_field_mapping();

        $this->assertFalse( $res['success'] );
        $this->assertSame( array(), $this->store->read() );
    }

    /**
     * delete_field_mapping removes a stored mapping.
     *
     * @return void
     */
    public function testDeleteFieldMapping() {
        $this->mockMappingFunctions();
        $_POST['integration'] = 'contact-form-7';
        $_POST['form_id']     = '123';
        $recorder             = $this->makeRecorder(
            array(
                'contact-form-7' => array(
                    '123' => array(
                        'form_title' => 'wform1',
                        'mappings'   => array( 'FN_1' => 'first_name' ),
                    ),
                ),
            )
        );

        $res = $recorder->delete_field_mapping();

        $this->assertTrue( $res['success'] );
        $this->assertArrayHasKey( 'list', $res['msg'] );
        $this->assertSame( array(), $this->store->read() );
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
        $recorder             = $this->makeRecorder();

        $res = $recorder->delete_field_mapping();

        $this->assertFalse( $res['success'] );
    }
}
