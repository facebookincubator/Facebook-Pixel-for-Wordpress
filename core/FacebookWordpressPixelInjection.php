<?php
/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Core;

defined('ABSPATH') or die('Direct access not allowed');

class FacebookWordpressPixelInjection {
  public static $renderCache = array();

  public function __construct() {
    $pixel_id = FacebookWordpressOptions::getPixelId();
    if (FacebookPluginUtils::isPositiveInteger($pixel_id)) {
      add_action(
        'wp_head',
        array($this, 'injectPixelCode'));
      add_action(
        'wp_head',
        array($this, 'injectPixelNoscriptCode'));

      foreach (FacebookPluginConfig::INTEGRATION_CONFIG as $key => $value) {
        $class_name = 'FacebookPixelPlugin\\Integration\\'.$value;
        $class_name::injectPixelCode();
      }
    }
  }

  public function injectPixelCode() {
    if (
      (isset(self::$renderCache[FacebookPluginConfig::IS_PIXEL_RENDERED]) &&
      self::$renderCache[FacebookPluginConfig::IS_PIXEL_RENDERED] === true) ||
      empty(FacebookPixel::getPixelId())
    ) {
      return;
    }

    self::$renderCache[FacebookPluginConfig::IS_PIXEL_RENDERED] = true;
    echo(FacebookPixel::getPixelBaseCode());
    echo(FacebookPixel::getPixelInitCode(
      FacebookWordpressOptions::getAgentString(),
      FacebookWordpressOptions::getUserInfo()));
    echo(FacebookPixel::getPixelPageViewCode());
  }

  public function injectPixelNoscriptCode() {
    echo(FacebookPixel::getPixelNoscriptCode());
  }
}
