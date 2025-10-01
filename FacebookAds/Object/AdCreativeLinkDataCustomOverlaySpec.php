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
use FacebookPixelPlugin\FacebookAdsObject\Fields\AdCreativeLinkDataCustomOverlaySpecFields;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdCreativeLinkDataCustomOverlaySpecBackgroundColorValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdCreativeLinkDataCustomOverlaySpecFontValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdCreativeLinkDataCustomOverlaySpecOptionValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdCreativeLinkDataCustomOverlaySpecPositionValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdCreativeLinkDataCustomOverlaySpecTemplateValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdCreativeLinkDataCustomOverlaySpecTextColorValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class AdCreativeLinkDataCustomOverlaySpec extends AbstractObject {

  /**
   * @return AdCreativeLinkDataCustomOverlaySpecFields
   */
  public static function getFieldsEnum() {
    return AdCreativeLinkDataCustomOverlaySpecFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['BackgroundColor'] = AdCreativeLinkDataCustomOverlaySpecBackgroundColorValues::getInstance()->getValues();
    $ref_enums['Font'] = AdCreativeLinkDataCustomOverlaySpecFontValues::getInstance()->getValues();
    $ref_enums['Option'] = AdCreativeLinkDataCustomOverlaySpecOptionValues::getInstance()->getValues();
    $ref_enums['Position'] = AdCreativeLinkDataCustomOverlaySpecPositionValues::getInstance()->getValues();
    $ref_enums['Template'] = AdCreativeLinkDataCustomOverlaySpecTemplateValues::getInstance()->getValues();
    $ref_enums['TextColor'] = AdCreativeLinkDataCustomOverlaySpecTextColorValues::getInstance()->getValues();
    return $ref_enums;
  }


}
