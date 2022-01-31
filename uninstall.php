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

namespace FacebookPixelPlugin;

require_once plugin_dir_path(__FILE__).'vendor/autoload.php';

use FacebookPixelPlugin\Core\FacebookPluginConfig;

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
  die;
}

delete_option(FacebookPluginConfig::SETTINGS_KEY);
delete_user_meta(get_current_user_id(), FacebookPluginConfig::ADMIN_IGNORE_PIXEL_ID_NOTICE);
