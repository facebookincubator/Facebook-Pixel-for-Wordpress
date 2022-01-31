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

require_once \dirname(__FILE__) . "/BaseTask.php";

use FacebookPixelPlugin\Core\FacebookPluginConfig;

class FacebookWordpressAddVersionNumberTask extends BaseTask {

  /**
   * The path of file we want to add version number to
   */
  private $path = null;

  /**
   * The setter for the attribute "path"
   */
  public function setPath($path) {
    $this->path = $path;
  }

  public function main() {
    $this->setABSPATH();
    $version = FacebookPluginConfig::PLUGIN_VERSION;
    echo "The current version is {$version} \n";
    $file_contents = file_get_contents($this->path);
    $file_contents = str_replace("{*VERSION_NUMBER*}", $version, $file_contents);
    file_put_contents($this->path, $file_contents);
  }
}
