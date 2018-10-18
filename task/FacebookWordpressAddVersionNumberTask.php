<?php
/**
 * @package FacebookPixelPlugin
 */

require_once "phing/Task.php";
require_once \dirname(__FILE__)."/../tests/bootstrap.php";

use FacebookPixelPlugin\Core\FacebookPluginConfig;

class FacebookWordpressAddVersionNumberTask extends Task {

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
    $version = FacebookPluginConfig::PLUGIN_VERSION;
    echo "The current version is {$version} \n";
    $file_contents = file_get_contents($this->path);
    $file_contents = str_replace("{*VERSION_NUMBER*}", $version, $file_contents);
    file_put_contents($this->path, $file_contents);
  }
}
