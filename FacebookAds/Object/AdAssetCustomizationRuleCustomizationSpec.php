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
use FacebookPixelPlugin\FacebookAds\Http\RequestInterface;
use FacebookPixelPlugin\FacebookAdsTypeChecker;
use FacebookPixelPlugin\FacebookAds\Object\Fields\AdAssetCustomizationRuleCustomizationSpecFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdAssetCustomizationRuleCustomizationSpecDevicePlatformsValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class AdAssetCustomizationRuleCustomizationSpec extends AbstractObject {

  /**
   * @return AdAssetCustomizationRuleCustomizationSpecFields
   */
  public static function getFieldsEnum() {
    return AdAssetCustomizationRuleCustomizationSpecFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['DevicePlatforms'] = AdAssetCustomizationRuleCustomizationSpecDevicePlatformsValues::getInstance()->getValues();
    return $ref_enums;
  }


}
