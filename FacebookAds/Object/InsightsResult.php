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
use FacebookPixelPlugin\FacebookAds\Object\Fields\InsightsResultFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\InsightsResultBreakdownValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\InsightsResultDatePresetValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\InsightsResultMetricTypeValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\InsightsResultMetricValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\InsightsResultPeriodValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\InsightsResultTimeframeValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class InsightsResult extends AbstractCrudObject {

  /**
   * @return InsightsResultFields
   */
  public static function getFieldsEnum() {
    return InsightsResultFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['Breakdown'] = InsightsResultBreakdownValues::getInstance()->getValues();
    $ref_enums['Metric'] = InsightsResultMetricValues::getInstance()->getValues();
    $ref_enums['Period'] = InsightsResultPeriodValues::getInstance()->getValues();
    $ref_enums['DatePreset'] = InsightsResultDatePresetValues::getInstance()->getValues();
    $ref_enums['MetricType'] = InsightsResultMetricTypeValues::getInstance()->getValues();
    $ref_enums['Timeframe'] = InsightsResultTimeframeValues::getInstance()->getValues();
    return $ref_enums;
  }


}
