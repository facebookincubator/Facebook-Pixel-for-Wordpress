<?php
/**
 * Facebook Pixel Plugin OptionFormFieldMappingStoreTest class.
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
use FacebookPixelPlugin\Core\OptionFormFieldMappingStore;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * OptionFormFieldMappingStoreTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class OptionFormFieldMappingStoreTest extends FacebookWordpressTestBase {

    /**
     * A non-array option value reads as an empty store.
     *
     * @return void
     */
    public function testReadIgnoresCorruptOption() {
        \WP_Mock::userFunction(
            'get_option',
            array(
                'args'   => array( FacebookPluginConfig::FORM_FIELD_MAPPINGS_KEY, array() ),
                'return' => 'not-an-array',
            )
        );

        $this->assertSame( array(), ( new OptionFormFieldMappingStore() )->read() );
    }

    /**
     * Writing a changed store updates the option without autoloading it.
     *
     * @return void
     */
    public function testWriteUpdatesOptionWithoutAutoload() {
        $store = array( 'ninja-forms' => array( '1' => array( 'mappings' => array( 'email_1' => 'email' ) ) ) );
        \WP_Mock::userFunction( 'get_option', array( 'return' => array() ) );
        \WP_Mock::userFunction(
            'update_option',
            array(
                'times'  => 1,
                'args'   => array( FacebookPluginConfig::FORM_FIELD_MAPPINGS_KEY, $store, false ),
                'return' => true,
            )
        );

        $this->assertTrue( ( new OptionFormFieldMappingStore() )->write( $store ) );
    }

    /**
     * Re-writing an unchanged store succeeds without touching the option
     * (update_option() would report false).
     *
     * @return void
     */
    public function testWriteUnchangedStoreSucceeds() {
        $store = array( 'ninja-forms' => array( '1' => array( 'mappings' => array( 'email_1' => 'email' ) ) ) );
        \WP_Mock::userFunction( 'get_option', array( 'return' => $store ) );
        \WP_Mock::userFunction( 'update_option', array( 'times' => 0 ) );

        $this->assertTrue( ( new OptionFormFieldMappingStore() )->write( $store ) );
    }
}
