<?php
/**
 * Facebook Pixel Plugin FormFieldMappingConfigTest class.
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

use FacebookPixelPlugin\Core\FormFieldMappingConfig;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FormFieldMappingConfigTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FormFieldMappingConfigTest extends FacebookWordpressTestBase {

    /**
     * All grouped fields appear in get_all_fields().
     *
     * @return void
     */
    public function testGetAllFieldsContainsUserAndCustomData() {
        $all = ( new FormFieldMappingConfig() )->get_all_fields();

        $this->assertArrayHasKey( 'first_name', $all );
        $this->assertArrayHasKey( 'email', $all );
        $this->assertArrayHasKey( 'external_id', $all );
        $this->assertArrayHasKey( 'value', $all );
        $this->assertArrayHasKey( 'content_ids', $all );
        $this->assertArrayHasKey( 'full_name', $all );
    }

    /**
     * Grouped fields are split into User Data and Custom Data.
     *
     * @return void
     */
    public function testGroupedFields() {
        $grouped = ( new FormFieldMappingConfig() )->get_grouped_fields();

        $this->assertArrayHasKey( 'User Data', $grouped );
        $this->assertArrayHasKey( 'Custom Data', $grouped );
        $this->assertArrayHasKey( 'first_name', $grouped['User Data'] );
        $this->assertArrayHasKey( 'currency', $grouped['Custom Data'] );
    }

    /**
     * is_valid_target accepts known fields and rejects unknown ones.
     *
     * @return void
     */
    public function testIsValidTarget() {
        $config = new FormFieldMappingConfig();

        $this->assertTrue( $config->is_valid_target( 'first_name' ) );
        $this->assertTrue( $config->is_valid_target( 'content_ids' ) );
        $this->assertFalse( $config->is_valid_target( 'not_a_real_field' ) );
        $this->assertFalse( $config->is_valid_target( '' ) );
    }
}
