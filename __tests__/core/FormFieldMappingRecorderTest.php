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
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FormFieldMappingRecorderTest class.
 *
 * The recorder is given a PHPUnit mock of FormFieldMapper, so the handlers can
 * be tested in isolation. WP_Mock is used only at the WordPress boundary
 * (auth, nonce, sanitize, wp_send_json).
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FormFieldMappingRecorderTest extends FacebookWordpressTestBase {

    /**
     * Mock mapper injected into the recorder.
     *
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $mapper;

    /**
     * Builds a recorder with a mock FormFieldMapper.
     *
     * @return FacebookWordpressSettingsRecorder
     */
    private function makeRecorder() {
        $this->mapper = $this->createMock( FormFieldMapper::class );
        return new FacebookWordpressSettingsRecorder( $this->mapper );
    }

    /**
     * Mocks the WordPress boundary functions used by the mapping handlers.
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
     * get_mapping_fields returns fields plus the saved mapping from the mapper.
     *
     * @return void
     */
    public function testGetMappingFieldsSuccess() {
        $this->mockMappingFunctions();
        $_POST['integration'] = 'contact-form-7';
        $_POST['form_id']     = '123';
        $recorder             = $this->makeRecorder();

        $this->mapper->method( 'get_mappings' )
            ->with( 'contact-form-7', '123' )
            ->willReturn( array( 'your-email' => 'email' ) );

        $res = $recorder->get_mapping_fields();

        $this->assertTrue( $res['success'] );
        $this->assertArrayHasKey( 'fields', $res['msg'] );
        $this->assertEquals(
            array( 'your-email' => 'email' ),
            $res['msg']['mapping']
        );
    }

    /**
     * save_field_mapping forwards a sanitized mapping to the mapper.
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

        $this->mapper->expects( $this->once() )
            ->method( 'save_form_mapping' )
            ->with(
                'contact-form-7',
                '123',
                'wform1',
                array(
                    'FN_1' => 'first_name',
                    'EM_2' => 'email',
                )
            );
        $this->mapper->method( 'get_flat_list' )->willReturn( array() );

        $res = $recorder->save_field_mapping();

        $this->assertTrue( $res['success'] );
        $this->assertArrayHasKey( 'list', $res['msg'] );
    }

    /**
     * save_field_mapping rejects an unknown integration and never saves.
     *
     * @return void
     */
    public function testSaveFieldMappingInvalidIntegration() {
        $this->mockMappingFunctions();
        $_POST['integration'] = 'bogus';
        $_POST['form_id']     = '123';
        $_POST['mapping']     = json_encode( array( 'FN_1' => 'first_name' ) );
        $recorder             = $this->makeRecorder();

        $this->mapper->expects( $this->never() )->method( 'save_form_mapping' );

        $res = $recorder->save_field_mapping();

        $this->assertFalse( $res['success'] );
    }

    /**
     * delete_field_mapping forwards to the mapper.
     *
     * @return void
     */
    public function testDeleteFieldMapping() {
        $this->mockMappingFunctions();
        $_POST['integration'] = 'contact-form-7';
        $_POST['form_id']     = '123';
        $recorder             = $this->makeRecorder();

        $this->mapper->expects( $this->once() )
            ->method( 'delete_form_mapping' )
            ->with( 'contact-form-7', '123' );
        $this->mapper->method( 'get_flat_list' )->willReturn( array() );

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
        $recorder             = $this->makeRecorder();

        $this->mapper->expects( $this->never() )->method( 'delete_form_mapping' );

        $res = $recorder->delete_field_mapping();

        $this->assertFalse( $res['success'] );
    }
}
