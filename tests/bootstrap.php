<?php

require_once dirname(__FILE__).'/../vendor/autoload.php';

use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\FacebookPluginConfig;

function setup() {
  if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../');
  }

  if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
      return \dirname($file).'/';
    }
  }

  if (!function_exists('is_admin')) {
    function is_admin() {
      return FacebookWordpressTestBase::$isAdmin;
    }
  }

  if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
      FacebookWordpressTestBase::$addActionCallCount++;
    }
  }

  if (!function_exists('esc_html__')) {
    function esc_html__($tesxt, $domain = 'default') {
      FacebookWordpressTestBase::$escHtmlCallCount++;
    }
  }

  if (!function_exists('checked')) {
    function checked($checked, $current, $echo = true) {
      FacebookWordpressTestBase::$checkedCallCount++;
    }
  }

  if (!function_exists('get_option')) {
    function get_option($key, $default) {
      return array(FacebookPluginConfig::PIXEL_ID_KEY => FacebookWordpressTestBase::$mockPixelId,
        FacebookPluginConfig::USE_PII_KEY => FacebookWordpressTestBase::$mockUsePII,);
    }
  }

  if (!array_key_exists('wp_version', $GLOBALS)) {
    $GLOBALS['wp_version'] = '1.0';
  }

  if (!function_exists('esc_js')) {
    function esc_js($string) {
      return $string;
    }
  }

  if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
      return (object)[
        'ID' => FacebookWordpressTestBase::$mockUserId,
        'user_email' => 'foo@foo.com',
        'user_firstname' => 'John',
        'user_lastname' => 'Doe',
      ];
    }
  }
}
