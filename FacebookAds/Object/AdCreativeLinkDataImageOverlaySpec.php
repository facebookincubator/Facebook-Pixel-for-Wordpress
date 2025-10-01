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
use FacebookPixelPlugin\FacebookAdsObject\Fields\AdCreativeLinkDataImageOverlaySpecFields;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdCreativeLinkDataImageOverlaySpecCustomTextTypeValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdCreativeLinkDataImageOverlaySpecOverlayTemplateValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdCreativeLinkDataImageOverlaySpecPositionValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdCreativeLinkDataImageOverlaySpecTextFontValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdCreativeLinkDataImageOverlaySpecTextTypeValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdCreativeLinkDataImageOverlaySpecThemeColorValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class AdCreativeLinkDataImageOverlaySpec extends AbstractObject {

  /**
   * @return AdCreativeLinkDataImageOverlaySpecFields
   */
  public static function getFieldsEnum() {
    return AdCreativeLinkDataImageOverlaySpecFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['CustomTextType'] = AdCreativeLinkDataImageOverlaySpecCustomTextTypeValues::getInstance()->getValues();
    $ref_enums['OverlayTemplate'] = AdCreativeLinkDataImageOverlaySpecOverlayTemplateValues::getInstance()->getValues();
    $ref_enums['Position'] = AdCreativeLinkDataImageOverlaySpecPositionValues::getInstance()->getValues();
    $ref_enums['TextFont'] = AdCreativeLinkDataImageOverlaySpecTextFontValues::getInstance()->getValues();
    $ref_enums['TextType'] = AdCreativeLinkDataImageOverlaySpecTextTypeValues::getInstance()->getValues();
    $ref_enums['ThemeColor'] = AdCreativeLinkDataImageOverlaySpecThemeColorValues::getInstance()->getValues();
    return $ref_enums;
  }


}
