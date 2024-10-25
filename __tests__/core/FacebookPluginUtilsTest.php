<?php
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

use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in seperate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookPluginUtilsTest extends FacebookWordpressTestBase {
	public function testWhenIsPositiveInteger() {
		$this->assertTrue( FacebookPluginUtils::is_positive_integer( '1' ) );
		$this->assertFalse( FacebookPluginUtils::is_positive_integer( '0' ) );
		$this->assertFalse( FacebookPluginUtils::is_positive_integer( '-1' ) );
	}

	public function testIsInternalUser_WhenUserIsExternal() {
		\WP_Mock::userFunction(
			'current_user_can',
			array(
				'times'  => 1,
				'args'   => 'edit_posts',
				'return' => false,
			)
		);
		\WP_Mock::userFunction(
			'current_user_can',
			array(
				'times'  => 1,
				'args'   => 'upload_files',
				'return' => false,
			)
		);
		$isInternalUser = FacebookPluginUtils::is_internal_user();

		$this->assertFalse( $isInternalUser );
	}

	public function testIsInternalUser_WhenUserCanEditPosts() {
		\WP_Mock::userFunction(
			'current_user_can',
			array(
				'times'  => 1,
				'args'   => 'edit_posts',
				'return' => true,
			)
		);
		$isInternalUser = FacebookPluginUtils::is_internal_user();

		$this->assertTrue( $isInternalUser );
	}

	public function testIsInternalUser_WhenUserCanUploadFiles() {
		\WP_Mock::userFunction(
			'current_user_can',
			array(
				'times'  => 1,
				'args'   => 'edit_posts',
				'return' => false,
			)
		);
		\WP_Mock::userFunction(
			'current_user_can',
			array(
				'times'  => 1,
				'args'   => 'upload_files',
				'return' => true,
			)
		);
		$isInternalUser = FacebookPluginUtils::is_internal_user();

		$this->assertTrue( $isInternalUser );
	}
}
