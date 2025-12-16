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
use FacebookPixelPlugin\FacebookAds\Object\Fields\AdAccountMatchedSearchApplicationsEdgeDataFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdAccountMatchedSearchApplicationsEdgeDataAppStoreValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdAccountMatchedSearchApplicationsEdgeDataStoresToFilterValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class AdAccountMatchedSearchApplicationsEdgeData extends AbstractObject {

  /**
   * @return AdAccountMatchedSearchApplicationsEdgeDataFields
   */
  public static function getFieldsEnum() {
    return AdAccountMatchedSearchApplicationsEdgeDataFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['AppStore'] = AdAccountMatchedSearchApplicationsEdgeDataAppStoreValues::getInstance()->getValues();
    $ref_enums['StoresToFilter'] = AdAccountMatchedSearchApplicationsEdgeDataStoresToFilterValues::getInstance()->getValues();
    return $ref_enums;
  }


}
