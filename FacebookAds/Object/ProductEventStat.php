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
use FacebookPixelPlugin\FacebookAdsObject\Fields\ProductEventStatFields;
use FacebookPixelPlugin\FacebookAdsObject\Values\ProductEventStatBreakdownsValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\ProductEventStatDeviceTypeValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\ProductEventStatEventValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class ProductEventStat extends AbstractObject {

  /**
   * @return ProductEventStatFields
   */
  public static function getFieldsEnum() {
    return ProductEventStatFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['DeviceType'] = ProductEventStatDeviceTypeValues::getInstance()->getValues();
    $ref_enums['Event'] = ProductEventStatEventValues::getInstance()->getValues();
    $ref_enums['Breakdowns'] = ProductEventStatBreakdownsValues::getInstance()->getValues();
    return $ref_enums;
  }


}
