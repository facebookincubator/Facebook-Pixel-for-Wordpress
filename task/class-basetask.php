<?php
/**
 * Facebook Pixel Plugin BaseTask class.
 *
 * This file contains the main logic for BaseTask.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define BaseTask class.
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

require_once __DIR__ . '/../vendor/phing/phing/src/Phing/Task.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Phing\Task;

/**
 * BaseTask class.
 */
abstract class BaseTask extends Task {

    /**
     * Sets the ABSPATH constant if it is not already defined.
     *
     * WordPress needs this constant to be set in order to load. We can't
     * assume that the constant is set in the context of our tasks, since
     * they are being run from the command line instead of through a
     * web request.
     *
     * @return void
     */
    public function setABSPATH() {
    if ( ! defined( 'ABSPATH' ) ) {
        define( 'ABSPATH', __DIR__ . '/../' );
    }
    }
}
