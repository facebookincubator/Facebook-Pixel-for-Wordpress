<?php
/**
 * Facebook Pixel Plugin FacebookWordpressIntegrationBase class.
 *
 * This file contains the main logic for FacebookWordpressIntegrationBase.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressIntegrationBase class.
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

namespace FacebookPixelPlugin\Integration;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

use ReflectionMethod;

/**
 * FacebookWordpressIntegrationBase class.
 */
abstract class FacebookWordpressIntegrationBase {
    const PLUGIN_FILE   = '';
    const TRACKING_NAME = '';


    /**
     * This function should be overridden in derived classes.
     * It is responsible for adding action hooks to
     * WordPress to inject the pixel code.
     *
     * @return void
     */
    public static function inject_pixel_code() {
    }


    /**
     * Adds a hook to WordPress to inject the pixel code for a specific plugin.
     *
     * The hook is added to the WordPress action system.
     * The hook is the $hook_name, which is the name of the hook that
     * triggers the injection of the pixel code.
     * The callback is a closure that adds a hook to the
     * 'wp_footer' action to inject the pixel code. The hook is
     * added with a priority of 11.
     *
     * The function that is called is the $inject_function,
     * which is a static method of the class $classname.
     * The function is called with the parameters $argv.
     *
     * The hook is added with a priority of $priority,
     * which is optional and defaults to 11.
     *
     * @param array $pixel_fire_for_hook_params {
     *     Parameters for adding the hook.
     *
     *     @type string $hook_name       The name of the
     * hook that triggers the injection of the pixel code.
     *     @type string $classname       The name of the
     * class that contains the function that injects the pixel code.
     *     @type string $inject_function The name of
     * the function that injects the pixel code.
     *     @type int    $priority        The priority of
     * the hook. Optional and defaults to 11.
     * }
     */
    public static function add_pixel_fire_for_hook(
        $pixel_fire_for_hook_params
    ) {
        $hook_name       = $pixel_fire_for_hook_params['hook_name'];
        $classname       = $pixel_fire_for_hook_params['classname'];
        $inject_function = $pixel_fire_for_hook_params['inject_function'];
        $priority        = isset( $pixel_fire_for_hook_params['priority'] ) ?
        $pixel_fire_for_hook_params['priority'] : 11;

        $user_function = array(
            $classname,
            $inject_function,
        );
        $reflection    = new ReflectionMethod( $classname, $inject_function );
        $argc          = $reflection->getNumberOfParameters();
        $argv          = $reflection->getParameters();

        $callback = function () use ( $user_function, $argv ) {
            $hook_wp_footer = function () use ( $user_function, $argv ) {
                \call_user_func_array( $user_function, $argv );
            };
        add_action(
            'wp_footer',
            $hook_wp_footer,
            11
        );
        };

        add_action( $hook_name, $callback, $priority, $argc );
    }
}
