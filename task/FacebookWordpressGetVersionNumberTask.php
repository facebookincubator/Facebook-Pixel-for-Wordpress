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

use FacebookPixelPlugin\Core\FacebookPluginConfig;

class FacebookWordpressGetVersionNumberTask extends BaseTask {

  /**
   * The version number property
   */
  private $versionprop;

  /**
   * The setter for the attribute "versionprop"
   */
  public function setVersionProp($versionprop) {
    $this->versionprop = $versionprop;
  }

  public function main() {
    $version = FacebookPluginConfig::PLUGIN_VERSION;
    $this->project->setProperty($this->versionprop, $version);
  }
}
