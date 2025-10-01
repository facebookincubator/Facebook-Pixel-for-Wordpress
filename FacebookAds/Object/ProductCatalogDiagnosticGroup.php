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
use FacebookPixelPlugin\FacebookAdsObject\Fields\ProductCatalogDiagnosticGroupFields;
use FacebookPixelPlugin\FacebookAdsObject\Values\ProductCatalogDiagnosticGroupAffectedChannelsValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\ProductCatalogDiagnosticGroupAffectedEntitiesValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\ProductCatalogDiagnosticGroupAffectedEntityValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\ProductCatalogDiagnosticGroupAffectedFeaturesValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\ProductCatalogDiagnosticGroupSeveritiesValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\ProductCatalogDiagnosticGroupSeverityValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\ProductCatalogDiagnosticGroupTypeValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\ProductCatalogDiagnosticGroupTypesValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class ProductCatalogDiagnosticGroup extends AbstractObject {

  /**
   * @return ProductCatalogDiagnosticGroupFields
   */
  public static function getFieldsEnum() {
    return ProductCatalogDiagnosticGroupFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['AffectedEntity'] = ProductCatalogDiagnosticGroupAffectedEntityValues::getInstance()->getValues();
    $ref_enums['AffectedFeatures'] = ProductCatalogDiagnosticGroupAffectedFeaturesValues::getInstance()->getValues();
    $ref_enums['Severity'] = ProductCatalogDiagnosticGroupSeverityValues::getInstance()->getValues();
    $ref_enums['Type'] = ProductCatalogDiagnosticGroupTypeValues::getInstance()->getValues();
    $ref_enums['AffectedChannels'] = ProductCatalogDiagnosticGroupAffectedChannelsValues::getInstance()->getValues();
    $ref_enums['AffectedEntities'] = ProductCatalogDiagnosticGroupAffectedEntitiesValues::getInstance()->getValues();
    $ref_enums['Severities'] = ProductCatalogDiagnosticGroupSeveritiesValues::getInstance()->getValues();
    $ref_enums['Types'] = ProductCatalogDiagnosticGroupTypesValues::getInstance()->getValues();
    return $ref_enums;
  }


}
