<?php
/**
 * Define bootstrap.
 *
 * @package FacebookPixelPlugin
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

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/WPHelpers.php';

WP_Mock::bootstrap();
