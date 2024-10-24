<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase WordPress.Files.FileName.InvalidClassFileName
/**
 * Facebook Pixel Plugin FacebookWordpressGetVersionNumberTask class.
 *
 * This file contains the main logic for FacebookWordpressGetVersionNumberTask.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressGetVersionNumberTask class.
 *
 * @return void
 */

use FacebookPixelPlugin\Core\FacebookPluginConfig;

/**
 * FacebookWordpressGetVersionNumberTask class.
 */
class FacebookWordpressGetVersionNumberTask extends BaseTask {

	/**
	 * Versionprop variable.
	 *
	 * @var [string]
	 */
	private $versionprop;


	/**
	 * Set the property in the project that should contain the version number of the plugin.
	 *
	 * @param string $versionprop The name of the property in the project that should contain the version number.
	 *
	 * @return void
	 */
	public function setVersionProp( $versionprop ) {
		$this->versionprop = $versionprop;
	}

	/**
	 * Sets the project property specified in $versionprop to the current version of the plugin.
	 *
	 * @return void
	 */
	public function main() {
		$version = FacebookPluginConfig::PLUGIN_VERSION;
		$this->project->setProperty( $this->versionprop, $version );
	}
}
