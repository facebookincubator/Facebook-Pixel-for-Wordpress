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
use FacebookPixelPlugin\FacebookAdsObject\Fields\InstagramInsightsResultFields;
use FacebookPixelPlugin\FacebookAdsObject\Values\InstagramInsightsResultBreakdownValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\InstagramInsightsResultMetricTypeValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\InstagramInsightsResultMetricValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\InstagramInsightsResultPeriodValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\InstagramInsightsResultTimeframeValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class InstagramInsightsResult extends AbstractCrudObject {

  /**
   * @return InstagramInsightsResultFields
   */
  public static function getFieldsEnum() {
    return InstagramInsightsResultFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['Breakdown'] = InstagramInsightsResultBreakdownValues::getInstance()->getValues();
    $ref_enums['Metric'] = InstagramInsightsResultMetricValues::getInstance()->getValues();
    $ref_enums['Period'] = InstagramInsightsResultPeriodValues::getInstance()->getValues();
    $ref_enums['MetricType'] = InstagramInsightsResultMetricTypeValues::getInstance()->getValues();
    $ref_enums['Timeframe'] = InstagramInsightsResultTimeframeValues::getInstance()->getValues();
    return $ref_enums;
  }


}
