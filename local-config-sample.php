<?php
/**
 * Local development overrides (copy to local-config.php).
 *
 * This file is loaded by facebook-for-wordpress.php before anything else.
 * Define constants here to point the plugin at staging/dev environments.
 *
 * Usage:
 *   cp local-config-sample.php local-config.php
 *   Then edit local-config.php with your values.
 *
 * Note: local-config.php is gitignored and should never be committed.
 *
 * @package FacebookPixelPlugin
 */

// Point FBL4B at a staging Meta App and config.
// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
// define( 'FB_FBL4B_APP_ID', 'your_staging_app_id' );
// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
// define( 'FB_FBL4B_CONFIG_ID', 'your_staging_config_id' );

// Redirect FBL4B iframe and popup origin to a staging domain.
// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
// define( 'META_PIXEL_BASE_DOMAIN', 'your.staging.domain' );
