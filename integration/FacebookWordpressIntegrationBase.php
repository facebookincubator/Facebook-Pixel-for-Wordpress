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

/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Integration;

defined('ABSPATH') or die('Direct access not allowed');

use ReflectionMethod;

abstract class FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = '';
  const TRACKING_NAME = '';

  /**
   * inject the pixel code for the plugin
   */
  public static function injectPixelCode() {
  }

  // TODO(T39560845): Add unit test for addPixelFireForHook
  public static function addPixelFireForHook($pixel_fire_for_hook_params) {
    $hook_name = $pixel_fire_for_hook_params['hook_name'];
    $classname = $pixel_fire_for_hook_params['classname'];
    $inject_function = $pixel_fire_for_hook_params['inject_function'];
    $priority = isset($pixel_fire_for_hook_params['priority'])
    ? $pixel_fire_for_hook_params['priority']
    : 11;

    $user_function = array(
      // get derived class in base class
      $classname,
      $inject_function);
    $reflection = new ReflectionMethod($classname, $inject_function);
    $argc = $reflection->getNumberOfParameters();
    $argv = $reflection->getParameters();

    $callback = function () use ($user_function, $argv) {
      $hook_wp_footer = function () use ($user_function, $argv) {
        \call_user_func_array($user_function, $argv);
      };
      add_action(
        'wp_footer',
        $hook_wp_footer,
        11);
    };

    add_action($hook_name, $callback, $priority, $argc);
  }
}
