<?php
 /*
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 * All rights reserved.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace FacebookPixelPlugin\FacebookAds\Object;

use FacebookPixelPlugin\FacebookAdsApiRequest;
use FacebookPixelPlugin\FacebookAdsCursor;
use FacebookPixelPlugin\FacebookAdsHttp\RequestInterface;
use FacebookPixelPlugin\FacebookAdsTypeChecker;
use FacebookPixelPlugin\FacebookAdsObject\Fields\TargetingFields;
use FacebookPixelPlugin\FacebookAdsObject\Values\TargetingDevicePlatformsValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\TargetingEffectiveDevicePlatformsValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class Targeting extends AbstractObject {

  /**
   * @return TargetingFields
   */
  public static function getFieldsEnum() {
    return TargetingFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['DevicePlatforms'] = TargetingDevicePlatformsValues::getInstance()->getValues();
    $ref_enums['EffectiveDevicePlatforms'] = TargetingEffectiveDevicePlatformsValues::getInstance()->getValues();
    return $ref_enums;
  }


}
