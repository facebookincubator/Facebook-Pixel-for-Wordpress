<?php
 /*
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 * All rights reserved.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace FacebookPixelPlugin\FacebookAds\Object;

use FacebookPixelPlugin\FacebookAds\ApiRequest;
use FacebookPixelPlugin\FacebookAds\Cursor;
use FacebookPixelPlugin\FacebookAds\Http\RequestInterface;
use FacebookPixelPlugin\FacebookAds\TypeChecker;
use FacebookPixelPlugin\FacebookAds\Object\Fields\AdCreativeVideoDataCustomOverlaySpecFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdCreativeVideoDataCustomOverlaySpecBackgroundOpacityValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdCreativeVideoDataCustomOverlaySpecOptionValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdCreativeVideoDataCustomOverlaySpecPositionValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdCreativeVideoDataCustomOverlaySpecTemplateValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class AdCreativeVideoDataCustomOverlaySpec extends AbstractObject {

  /**
   * @return AdCreativeVideoDataCustomOverlaySpecFields
   */
  public static function getFieldsEnum() {
    return AdCreativeVideoDataCustomOverlaySpecFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['BackgroundOpacity'] = AdCreativeVideoDataCustomOverlaySpecBackgroundOpacityValues::getInstance()->getValues();
    $ref_enums['Option'] = AdCreativeVideoDataCustomOverlaySpecOptionValues::getInstance()->getValues();
    $ref_enums['Position'] = AdCreativeVideoDataCustomOverlaySpecPositionValues::getInstance()->getValues();
    $ref_enums['Template'] = AdCreativeVideoDataCustomOverlaySpecTemplateValues::getInstance()->getValues();
    return $ref_enums;
  }


}
