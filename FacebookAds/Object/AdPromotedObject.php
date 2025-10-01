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
use FacebookPixelPlugin\FacebookAdsObject\Fields\AdPromotedObjectFields;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdPromotedObjectCustomEventTypeValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdPromotedObjectFullFunnelObjectiveValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdPromotedObjectLeadAdsCustomEventTypeValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class AdPromotedObject extends AbstractObject {

  /**
   * @return AdPromotedObjectFields
   */
  public static function getFieldsEnum() {
    return AdPromotedObjectFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['CustomEventType'] = AdPromotedObjectCustomEventTypeValues::getInstance()->getValues();
    $ref_enums['FullFunnelObjective'] = AdPromotedObjectFullFunnelObjectiveValues::getInstance()->getValues();
    $ref_enums['LeadAdsCustomEventType'] = AdPromotedObjectLeadAdsCustomEventTypeValues::getInstance()->getValues();
    return $ref_enums;
  }


}
