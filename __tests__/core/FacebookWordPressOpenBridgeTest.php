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

use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\FacebookWordpressOpenBridge;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordPressOpenBridgeTest extends FacebookWordpressTestBase {

    public function testWhenNoLoggedInUserNewGUIDShouldBeGenerated() {
        self::mockFacebookWordpressOptions();
        \WP_Mock::userFunction('wp_get_current_user', array('return' => []));
        \WP_Mock::userFunction('get_current_user_id', array('return' => 0));
        \WP_Mock::userFunction('com_create_guid', array('return' => 'GUID'));
        $_SESSION['obeid'] = 'GUID';

        $event = ServerEventFactory::newEvent('Lead');
        $openBridgeInstance = FacebookWordpressOpenBridge::getInstance();

        $ev = $openBridgeInstance->extractFromDatabag($event);

        $this->assertEquals('GUID', $ev['external_id']);
    }

    public function testExternalIdFetchedFromCookieWhenObeidExists() {
        self::mockFacebookWordpressOptions();
        \WP_Mock::userFunction('wp_get_current_user', array('return' => []));
        \WP_Mock::userFunction('get_current_user_id', array('return' => 0));
        $_SESSION['obeid'] = 'testObeid';

        $event = ServerEventFactory::newEvent('Lead');
        $openBridgeInstance = FacebookWordpressOpenBridge::getInstance();

        $ev = $openBridgeInstance->extractFromDatabag($event);

        $this->assertEquals('testObeid', $ev['external_id']);
    }

    public function testExternalIdFetchedFromUserIdIfFirstTime() {
        self::mockFacebookWordpressOptions();
        \WP_Mock::userFunction(
            'wp_get_current_user',
            array('return' => (object) [
                'ID' => 'testUser',
                'user_email' => 'foo@foo.com',
                'user_firstname' => 'John',
                'user_lastname' => 'Doe',
          ])
        );
        \WP_Mock::userFunction(
            'get_current_user_id',
            array('return' => 'testUser')
        );

        $_SESSION['obeid'] = 'testObeid';

        $event = ServerEventFactory::newEvent('Lead');
        $openBridgeInstance = FacebookWordpressOpenBridge::getInstance();

        $ev = $openBridgeInstance->extractFromDatabag($event);

        $this->assertEquals('testUser,testObeid', $ev['external_id']);
    }

    public function testExternalIdFetchedFromAAMIfFirstTimeAndUserIdNotFound() {
        self::mockFacebookWordpressOptions();
        \WP_Mock::userFunction('wp_get_current_user', array('return' => []));
        \WP_Mock::userFunction('get_current_user_id', array('return' => 0));
        $_SESSION['obeid'] = 'testObeid';

        $openBridgeInstance = FacebookWordpressOpenBridge::getInstance();
        $event = array(
            'fb.advanced_matching' => array (
                'external_id' => 'testAM'
                )
            );

        $ev = $openBridgeInstance->extractFromDatabag($event);

        $this->assertEquals('testAM,testObeid', $ev['external_id']);
    }
}
