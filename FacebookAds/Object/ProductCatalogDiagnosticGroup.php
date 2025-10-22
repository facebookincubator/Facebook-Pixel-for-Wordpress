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
use FacebookPixelPlugin\FacebookAds\Object\Fields\ProductCatalogDiagnosticGroupFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\ProductCatalogDiagnosticGroupAffectedChannelsValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\ProductCatalogDiagnosticGroupAffectedEntitiesValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\ProductCatalogDiagnosticGroupAffectedEntityValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\ProductCatalogDiagnosticGroupAffectedFeaturesValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\ProductCatalogDiagnosticGroupSeveritiesValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\ProductCatalogDiagnosticGroupSeverityValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\ProductCatalogDiagnosticGroupTypeValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\ProductCatalogDiagnosticGroupTypesValues;

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
