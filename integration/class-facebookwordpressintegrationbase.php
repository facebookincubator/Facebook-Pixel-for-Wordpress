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
     * The shared Signals facade, injected by the pixel injector.
     *
     * @var \FacebookPixelPlugin\Core\Signals
     */
    protected $signals;

    /**
     * Constructor. Integrations are instantiated by the pixel injector with the
     * single shared Signals instance created in the bootstrap, so every
     * integration tracks/render through the same facade rather than
     * constructing its own.
     *
     * @param \FacebookPixelPlugin\Core\Signals $signals The shared Signals facade.
     */
    public function __construct( \FacebookPixelPlugin\Core\Signals $signals ) {
        $this->signals = $signals;
    }

    /**
     * This function should be overridden in derived classes.
     * It is responsible for adding action hooks to
     * WordPress to inject the pixel code.
     *
     * @return void
     */
    public function inject_pixel_code() {
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
     * which is an instance method of this integration. It is invoked on the
     * same instance, so it has access to the injected Signals facade.
     *
     * The hook is added with a priority of $priority,
     * which is optional and defaults to 11.
     *
     * @param array $pixel_fire_for_hook_params {
     *     Parameters for adding the hook.
     *
     *     @type string $hook_name       The name of the
     * hook that triggers the injection of the pixel code.
     *     @type string $inject_function The name of
     * the instance method that injects the pixel code.
     *     @type int    $priority        The priority of
     * the hook. Optional and defaults to 11.
     * }
     */
    public function add_pixel_fire_for_hook(
        $pixel_fire_for_hook_params
    ) {
        $hook_name       = $pixel_fire_for_hook_params['hook_name'];
        $inject_function = $pixel_fire_for_hook_params['inject_function'];
        $priority        = isset( $pixel_fire_for_hook_params['priority'] ) ?
        $pixel_fire_for_hook_params['priority'] : 11;

        $user_function = array(
            $this,
            $inject_function,
        );
        $reflection    = new ReflectionMethod( $this, $inject_function );
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
