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

require_once dirname( __FILE__ ) . '/../vendor/phing/phing/src/Phing/Task.php';
require_once dirname( __FILE__ ) . '/../vendor/autoload.php';

use Phing\Task;

abstract class BaseTask extends Task {

	public function setABSPATH() {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __FILE__ ) . '/../' );
		}
	}
}
