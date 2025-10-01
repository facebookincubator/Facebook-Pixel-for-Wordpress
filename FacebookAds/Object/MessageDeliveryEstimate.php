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
use FacebookPixelPlugin\FacebookAds\Object\Fields\MessageDeliveryEstimateFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\MessageDeliveryEstimateOptimizationGoalValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\MessageDeliveryEstimatePacingTypeValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class MessageDeliveryEstimate extends AbstractObject {

  /**
   * @return MessageDeliveryEstimateFields
   */
  public static function getFieldsEnum() {
    return MessageDeliveryEstimateFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['OptimizationGoal'] = MessageDeliveryEstimateOptimizationGoalValues::getInstance()->getValues();
    $ref_enums['PacingType'] = MessageDeliveryEstimatePacingTypeValues::getInstance()->getValues();
    return $ref_enums;
  }


}
