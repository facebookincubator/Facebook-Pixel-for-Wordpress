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

/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Core;

defined('ABSPATH') or die('Direct access not allowed');

class FacebookWordpressPixelInjection {
  public static $renderCache = array();

  public function __construct() {
  }

  public function inject() {
    $pixel_id = FacebookWordpressOptions::getPixelId();
    if (FacebookPluginUtils::isPositiveInteger($pixel_id)) {
      add_action(
        'wp_head',
        array($this, 'injectPixelCode'));
      add_action(
        'wp_head',
        array($this, 'injectPixelNoscriptCode'));
      foreach (FacebookPluginConfig::integrationConfig() as $key => $value) {
        $class_name = 'FacebookPixelPlugin\\Integration\\'.$value;
        $class_name::injectPixelCode();
      }
      add_action(
        'wp_footer',
        array($this, 'sendPendingEvents'));
    }
  }

  public function sendPendingEvents(){
    $pending_events =
      FacebookServerSideEvent::getInstance()->getPendingEvents();
    if(count($pending_events) > 0){
      do_action(
        'send_server_events',
        $pending_events,
        count($pending_events)
      );
    }
  }

  public function injectPixelCode() {
    $pixel_id = FacebookPixel::getPixelId();
    if (
      (isset(self::$renderCache[FacebookPluginConfig::IS_PIXEL_RENDERED]) &&
      self::$renderCache[FacebookPluginConfig::IS_PIXEL_RENDERED] === true) ||
      empty($pixel_id)
    ) {
      return;
    }

    self::$renderCache[FacebookPluginConfig::IS_PIXEL_RENDERED] = true;
    echo(FacebookPixel::getPixelBaseCode());
    $capiIntegrationStatus =
    FacebookWordpressOptions::getCapiIntegrationStatus();
    if($capiIntegrationStatus === '1'){
      echo(FacebookPixel::getOpenBridgeConfigCode());
    }
    echo(FacebookPixel::getPixelInitCode(
      FacebookWordpressOptions::getAgentString(),
      FacebookWordpressOptions::getUserInfo()));
    echo(FacebookPixel::getPixelPageViewCode());
  }

  public function injectPixelNoscriptCode() {
    echo(FacebookPixel::getPixelNoscriptCode());
  }
}
