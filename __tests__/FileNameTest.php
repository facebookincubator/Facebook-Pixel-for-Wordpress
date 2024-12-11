<?php
/**
 * Facebook Pixel Plugin FileNameTest class.
 *
 * This file contains the main logic for FileNameTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FileNameTest class.
 *
 * @return void
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


namespace FacebookPixelPlugin\Tests;

use FacebookPixelPlugin\Core\FacebookPixel;

/**
 * This is a testcase to make sure that the name of entry point file
 * 'facebook-for-wordpress.php' cannot be changed. Changing the file
 * name will break how WordPress find the plugin file and will end
 * up deactivate the plugin.
**/
final class FileNameTest extends FacebookWordpressTestBase {
    /**
     * Check that the name of entry point file
     * 'facebook-for-wordpress.php' still
     * exist. This is to make sure that the name of
     * entry point file is not changed,
     * since changing the file name will break how
     * WordPress find the plugin file
     * and will end up deactivate the plugin.
     */
    public function testEntryPointFileNamePersists() {
        $exist = \file_exists( __DIR__ . '/../class-facebookforwordpress.php' );
        $this->assertTrue( $exist );
    }
}
