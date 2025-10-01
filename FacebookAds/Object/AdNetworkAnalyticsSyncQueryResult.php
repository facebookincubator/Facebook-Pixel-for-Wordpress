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
use FacebookPixelPlugin\FacebookAds\Object\Fields\AdNetworkAnalyticsSyncQueryResultFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdNetworkAnalyticsSyncQueryResultAggregationPeriodValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdNetworkAnalyticsSyncQueryResultBreakdownsValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdNetworkAnalyticsSyncQueryResultMetricsValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdNetworkAnalyticsSyncQueryResultOrderingColumnValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdNetworkAnalyticsSyncQueryResultOrderingTypeValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class AdNetworkAnalyticsSyncQueryResult extends AbstractObject {

  /**
   * @return AdNetworkAnalyticsSyncQueryResultFields
   */
  public static function getFieldsEnum() {
    return AdNetworkAnalyticsSyncQueryResultFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['AggregationPeriod'] = AdNetworkAnalyticsSyncQueryResultAggregationPeriodValues::getInstance()->getValues();
    $ref_enums['Breakdowns'] = AdNetworkAnalyticsSyncQueryResultBreakdownsValues::getInstance()->getValues();
    $ref_enums['Metrics'] = AdNetworkAnalyticsSyncQueryResultMetricsValues::getInstance()->getValues();
    $ref_enums['OrderingColumn'] = AdNetworkAnalyticsSyncQueryResultOrderingColumnValues::getInstance()->getValues();
    $ref_enums['OrderingType'] = AdNetworkAnalyticsSyncQueryResultOrderingTypeValues::getInstance()->getValues();
    return $ref_enums;
  }


}
