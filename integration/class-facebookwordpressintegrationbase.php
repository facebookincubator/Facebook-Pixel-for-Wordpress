<?php
/**
 * Facebook Pixel Plugin FacebookWordpressIntegrationBase class.
 *
 * Backward-compatible shim for the legacy all-static integration base. New
 * integrations should extend TrackableIntegrationBase (or
 * TrackableLeadFormIntegrationBase) instead; this class is deprecated and will
 * be removed in a future release.
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

namespace FacebookPixelPlugin\Integration;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

use ReflectionMethod;

/**
 * FacebookWordpressIntegrationBase class.
 *
 * @deprecated Extend {@see TrackableIntegrationBase} (or
 *             {@see TrackableLeadFormIntegrationBase} for lead / form-submission
 *             integrations) instead. This shim re-parents the legacy base onto
 *             TrackableIntegrationBase so integrations that implement the static
 *             inject_pixel_code() keep working: the modern instance lifecycle
 *             (initialize()) bridges to inject_pixel_code(). Scheduled for
 *             removal in a future major release.
 */
abstract class FacebookWordpressIntegrationBase extends TrackableIntegrationBase {
    /**
     * Legacy alias of TrackableIntegrationBase::INTEGRATION_NAME, retained for
     * source compatibility with pre-refactor subclasses.
     *
     * @var string
     */
    const TRACKING_NAME = '';

    /**
     * Concrete subclasses for which the deprecation notice has already been
     * emitted this request, so it fires at most once per class.
     *
     * @var array<string, bool>
     */
    private static $deprecation_notified = array();

    /**
     * Bridges the modern instance lifecycle to the legacy static hook.
     *
     * The pre-refactor loader called inject_pixel_code() unconditionally for
     * every configured integration; each integration self-gated by only wiring
     * host-plugin hooks that fire when that plugin is active. This shim
     * preserves that behavior: it emits a one-time deprecation notice and then
     * invokes the subclass's static inject_pixel_code(), deliberately skipping
     * the is_integration_available() gate the modern base applies in
     * initialize().
     *
     * @return void
     */
    public function initialize() {
        self::emit_deprecation_notice();
        static::inject_pixel_code();
    }

    /**
     * Satisfies the abstract TrackableIntegrationBase contract by delegating to
     * the legacy static hook, so a subclass driven through the modern lifecycle
     * (e.g. via set_up_tracking()) still functions.
     *
     * @return void
     */
    protected function set_up_tracking() {
        static::inject_pixel_code();
    }

    /**
     * Legacy override point for injecting the pixel code.
     *
     * Derived classes should override this to add the action hooks that inject
     * the pixel code.
     *
     * @deprecated Move this logic into
     *             {@see TrackableIntegrationBase::set_up_tracking()}.
     *
     * @return void
     */
    public static function inject_pixel_code() {
    }

    /**
     * Emits the deprecation notice once per concrete subclass per request.
     *
     * Uses _deprecated_class() when available (WordPress 6.4+) and falls back to
     * _deprecated_function() on older cores.
     *
     * @return void
     */
    private static function emit_deprecation_notice() {
        $class = static::class;
        if ( isset( self::$deprecation_notified[ $class ] ) ) {
            return;
        }
        self::$deprecation_notified[ $class ] = true;

        if ( function_exists( '_deprecated_class' ) ) {
            _deprecated_class(
                esc_html( $class ),
                '6.0.0',
                'FacebookPixelPlugin\\Integration\\TrackableIntegrationBase'
            );
        } elseif ( function_exists( '_deprecated_function' ) ) {
            _deprecated_function(
                esc_html( $class . '::inject_pixel_code' ),
                '6.0.0',
                'FacebookPixelPlugin\\Integration\\'
                . 'TrackableIntegrationBase::set_up_tracking'
            );
        }
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
     * @deprecated Wire host-plugin hooks from
     *             {@see TrackableIntegrationBase::set_up_tracking()} instead.
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
