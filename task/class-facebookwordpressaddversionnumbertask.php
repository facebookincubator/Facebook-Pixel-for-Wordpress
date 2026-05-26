<?php
/**
 * Facebook Pixel Plugin FacebookWordpressAddVersionNumberTask class.
 *
 * This file contains the main logic for FacebookWordpressAddVersionNumberTask.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressAddVersionNumberTask class.
 *
 * @return void
 */

require_once __DIR__ . '/class-basetask.php';

use FacebookPixelPlugin\Core\FacebookPluginConfig;

/**
 * FacebookWordpressAddVersionNumberTask class.
 */
class FacebookWordpressAddVersionNumberTask extends BaseTask {

    /**
     * Path variable.
     *
     * @var string
     */
    private $path = null;

    /**
     * Setter for the path property.
     *
     * @param string $path The path to the file to
     * update with the plugin version.
     *
     * @return void
     */
    public function setPath( $path ) {
        $this->path = $path;
    }

    /**
     * Main function to update the version number in the specified file.
     *
     * This function retrieves the current plugin version and replaces the
     * placeholder '{*VERSION_NUMBER*}' in the file at the specified path
     * with the actual version number.
     *
     * @return void
     */
    public function main() {
        $this->setABSPATH();
        $version   = FacebookPluginConfig::PLUGIN_VERSION;
        $base_dir  = $this->getProject()->getBasedir();
        $full_path = $base_dir . DIRECTORY_SEPARATOR . $this->path;
        echo 'The current version is ' . $version . " \n";
        $file_contents = file_get_contents( $full_path );
        $file_contents = str_replace( '{*VERSION_NUMBER*}', $version, $file_contents );
        file_put_contents( $full_path, $file_contents );
    }
}
