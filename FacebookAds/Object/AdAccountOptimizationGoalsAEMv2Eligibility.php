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
use FacebookPixelPlugin\FacebookAds\Object\Fields\AdAccountOptimizationGoalsAEMv2EligibilityFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdAccountOptimizationGoalsAEMv2EligibilityOptimizationGoalValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class AdAccountOptimizationGoalsAEMv2Eligibility extends AbstractObject {

  /**
   * @return AdAccountOptimizationGoalsAEMv2EligibilityFields
   */
  public static function getFieldsEnum() {
    return AdAccountOptimizationGoalsAEMv2EligibilityFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['OptimizationGoal'] = AdAccountOptimizationGoalsAEMv2EligibilityOptimizationGoalValues::getInstance()->getValues();
    return $ref_enums;
  }


}
