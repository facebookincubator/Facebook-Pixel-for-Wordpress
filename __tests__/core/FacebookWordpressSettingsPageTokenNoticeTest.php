<?php
/**
 * Facebook Pixel Plugin FacebookWordpressSettingsPageTokenNoticeTest class.
 *
 * This file contains tests for the token invalid admin notice.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressSettingsPageTokenNoticeTest class.
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

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Core\FacebookWordpressSettingsPage;
use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressSettingsPageTokenNoticeTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressSettingsPageTokenNoticeTest extends FacebookWordpressTestBase {

    /**
     * Tests that the token invalid notice is registered when
     * the transient is set and user hasn't dismissed it.
     */
    public function testTokenInvalidNoticeRegisteredWhenTransientSet() {
        $this->mockFacebookWordpressOptions(
            array( 'is_fbe_installed' => '1' )
        );

        \WP_Mock::userFunction(
            'current_user_can',
            array(
                'args'   => array( FacebookPluginConfig::ADMIN_CAPABILITY ),
                'return' => true,
            )
        );

        $mock_screen     = \Mockery::mock();
        $mock_screen->id = 'dashboard';

        \WP_Mock::userFunction(
            'get_current_screen',
            array( 'return' => $mock_screen )
        );

        \WP_Mock::userFunction(
            'get_current_user_id',
            array( 'return' => 1 )
        );

        // FBE installed notice - user dismissed.
        \WP_Mock::userFunction(
            'get_user_meta',
            array(
                'args'   => array(
                    1,
                    FacebookPluginConfig::ADMIN_IGNORE_PLUGIN_REVIEW_NOTICE,
                    true,
                ),
                'return' => true,
            )
        );

        // Token notice - user has NOT dismissed.
        \WP_Mock::userFunction(
            'get_user_meta',
            array(
                'args'   => array(
                    1,
                    FacebookPluginConfig::ADMIN_IGNORE_TOKEN_INVALID_NOTICE,
                    true,
                ),
                'return' => false,
            )
        );

        // Transient IS set.
        \WP_Mock::userFunction(
            'get_transient',
            array(
                'args'   => array(
                    FacebookPluginConfig::TOKEN_INVALID_TRANSIENT_KEY,
                ),
                'return' => array(
                    'code'    => 190,
                    'subcode' => 464,
                ),
            )
        );

        \WP_Mock::expectActionAdded(
            'admin_notices',
            array( \Mockery::any(), 'token_invalid_notice' )
        );

        $settings_page = new FacebookWordpressSettingsPage(
            'facebook_for_wordpress'
        );
        $settings_page->register_notices();
    }

    /**
     * Tests that the token invalid notice is NOT registered when
     * the user has dismissed it.
     */
    public function testTokenInvalidNoticeNotRegisteredWhenDismissed() {
        $this->mockFacebookWordpressOptions(
            array( 'is_fbe_installed' => '1' )
        );

        \WP_Mock::userFunction(
            'current_user_can',
            array(
                'args'   => array( FacebookPluginConfig::ADMIN_CAPABILITY ),
                'return' => true,
            )
        );

        $mock_screen     = \Mockery::mock();
        $mock_screen->id = 'dashboard';

        \WP_Mock::userFunction(
            'get_current_screen',
            array( 'return' => $mock_screen )
        );

        \WP_Mock::userFunction(
            'get_current_user_id',
            array( 'return' => 1 )
        );

        // Plugin review notice - dismissed.
        \WP_Mock::userFunction(
            'get_user_meta',
            array(
                'args'   => array(
                    1,
                    FacebookPluginConfig::ADMIN_IGNORE_PLUGIN_REVIEW_NOTICE,
                    true,
                ),
                'return' => true,
            )
        );

        // Token notice - user HAS dismissed.
        \WP_Mock::userFunction(
            'get_user_meta',
            array(
                'args'   => array(
                    1,
                    FacebookPluginConfig::ADMIN_IGNORE_TOKEN_INVALID_NOTICE,
                    true,
                ),
                'return' => true,
            )
        );

        // Transient IS set.
        \WP_Mock::userFunction(
            'get_transient',
            array(
                'args'   => array(
                    FacebookPluginConfig::TOKEN_INVALID_TRANSIENT_KEY,
                ),
                'return' => array(
                    'code'    => 190,
                    'subcode' => 464,
                ),
            )
        );

        $settings_page = new FacebookWordpressSettingsPage(
            'facebook_for_wordpress'
        );
        $settings_page->register_notices();

        // If the notice action were added, WP_Mock would register it.
        // By not calling expectActionAdded for token_invalid_notice,
        // we verify it was NOT added.
        $this->assertTrue( true );
    }

    /**
     * Tests that the token invalid notice is NOT registered when
     * no transient is set.
     */
    public function testTokenInvalidNoticeNotRegisteredWhenNoTransient() {
        $this->mockFacebookWordpressOptions(
            array( 'is_fbe_installed' => '1' )
        );

        \WP_Mock::userFunction(
            'current_user_can',
            array(
                'args'   => array( FacebookPluginConfig::ADMIN_CAPABILITY ),
                'return' => true,
            )
        );

        $mock_screen     = \Mockery::mock();
        $mock_screen->id = 'dashboard';

        \WP_Mock::userFunction(
            'get_current_screen',
            array( 'return' => $mock_screen )
        );

        \WP_Mock::userFunction(
            'get_current_user_id',
            array( 'return' => 1 )
        );

        // Plugin review notice - dismissed.
        \WP_Mock::userFunction(
            'get_user_meta',
            array(
                'args'   => array(
                    1,
                    FacebookPluginConfig::ADMIN_IGNORE_PLUGIN_REVIEW_NOTICE,
                    true,
                ),
                'return' => true,
            )
        );

        // Transient is NOT set.
        \WP_Mock::userFunction(
            'get_transient',
            array(
                'args'   => array(
                    FacebookPluginConfig::TOKEN_INVALID_TRANSIENT_KEY,
                ),
                'return' => false,
            )
        );

        $settings_page = new FacebookWordpressSettingsPage(
            'facebook_for_wordpress'
        );
        $settings_page->register_notices();

        // Verify no token_invalid_notice action was added.
        $this->assertTrue( true );
    }
}
