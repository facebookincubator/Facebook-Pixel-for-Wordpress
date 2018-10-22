<?php
/**
 * @package FacebookPixelPlugin
 */

require_once "phing/Task.php";
require_once dirname(__FILE__).'/../vendor/autoload.php';

use FacebookPixelPlugin\Core\FacebookPluginConfig;

abstract class BaseTask extends Task {

    public function setABSPATH() {
        if (!defined('ABSPATH')) {
            define('ABSPATH', dirname(__FILE__) . '/../');
        }
    }
}
