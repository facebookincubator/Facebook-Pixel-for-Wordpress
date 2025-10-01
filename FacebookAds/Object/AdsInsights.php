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
use FacebookPixelPlugin\FacebookAds\Object\Fields\AdsInsightsFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdsInsightsActionAttributionWindowsValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdsInsightsActionBreakdownsValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdsInsightsActionReportTimeValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdsInsightsBreakdownsValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdsInsightsDatePresetValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdsInsightsLevelValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdsInsightsSummaryActionBreakdownsValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class AdsInsights extends AbstractObject {

  /**
   * @deprecated getEndpoint function is deprecated
   */
  protected function getEndpoint() {
    return 'insights';
  }

  /**
   * @return AdsInsightsFields
   */
  public static function getFieldsEnum() {
    return AdsInsightsFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['ActionAttributionWindows'] = AdsInsightsActionAttributionWindowsValues::getInstance()->getValues();
    $ref_enums['ActionBreakdowns'] = AdsInsightsActionBreakdownsValues::getInstance()->getValues();
    $ref_enums['ActionReportTime'] = AdsInsightsActionReportTimeValues::getInstance()->getValues();
    $ref_enums['Breakdowns'] = AdsInsightsBreakdownsValues::getInstance()->getValues();
    $ref_enums['DatePreset'] = AdsInsightsDatePresetValues::getInstance()->getValues();
    $ref_enums['Level'] = AdsInsightsLevelValues::getInstance()->getValues();
    $ref_enums['SummaryActionBreakdowns'] = AdsInsightsSummaryActionBreakdownsValues::getInstance()->getValues();
    return $ref_enums;
  }


}
