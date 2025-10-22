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
use FacebookPixelPlugin\FacebookAds\Object\Fields\PlacementFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\PlacementDevicePlatformsValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\PlacementEffectiveDevicePlatformsValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class Placement extends AbstractObject {

  /**
   * @return PlacementFields
   */
  public static function getFieldsEnum() {
    return PlacementFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['DevicePlatforms'] = PlacementDevicePlatformsValues::getInstance()->getValues();
    $ref_enums['EffectiveDevicePlatforms'] = PlacementEffectiveDevicePlatformsValues::getInstance()->getValues();
    return $ref_enums;
  }


}
