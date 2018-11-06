<?php
/**
 * Plugin Name: Facebook Pixel for WordPress
 * Plugin URI: https://www.facebook.com/business/help/881403525362441
 * Description: The Facebook pixel is an analytics tool that helps you measure the effectiveness of your advertising. You can use the Facebook pixel to understand the actions people are taking on your website and reach audiences you care about.
 * Author: Facebook
 * Author URI: https://www.facebook.com/
 * Version: {*VERSION_NUMBER*}
 * Text Domain: official-facebook-pixel
 */

/*
* Copyright (C) 2017-present, Facebook, Inc.
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

namespace FacebookPixelPlugin;

defined('ABSPATH') or die('Direct access not allowed');

require_once plugin_dir_path(__FILE__).'vendor/autoload.php';

use FacebookPixelPlugin\Core\FacebookPixel;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Core\FacebookWordpressPixelInjection;
use FacebookPixelPlugin\Core\FacebookWordpressSettingsPage;

class FacebookForWordpress {
  public function __construct() {
    // initialize options
    FacebookWordpressOptions::initialize();

    // initialize pixel
    $options = FacebookWordpressOptions::getOptions();
    FacebookPixel::initialize(FacebookWordpressOptions::getPixelId());

    // Register WordPress pixel injection controlling where to fire pixel
    add_action('init', array($this, 'registerPixelInjection'), 0);

    // initialize admin page config
    $this->registerSettingsPage();
  }

  /**
   * Helper function for registering pixel injection.
   */
  public function registerPixelInjection() {
    return new FacebookWordpressPixelInjection();
  }

  /**
   * Helper function for registering the settings page.
   */
  public function registerSettingsPage() {
    if (is_admin()) {
      $plugin_name = plugin_basename(__FILE__);
      new FacebookWordpressSettingsPage($plugin_name);
    }
  }
}

$WP_FacebookForWordpress = new FacebookForWordpress();
