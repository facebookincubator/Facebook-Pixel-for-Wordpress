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
use FacebookPixelPlugin\FacebookAdsObject\Fields\ThirdPartyPartnerPanelScheduledFields;
use FacebookPixelPlugin\FacebookAdsObject\Values\ThirdPartyPartnerPanelScheduledStatusValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\ThirdPartyPartnerPanelScheduledStudyTypeValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class ThirdPartyPartnerPanelScheduled extends AbstractCrudObject {

  /**
   * @return ThirdPartyPartnerPanelScheduledFields
   */
  public static function getFieldsEnum() {
    return ThirdPartyPartnerPanelScheduledFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['Status'] = ThirdPartyPartnerPanelScheduledStatusValues::getInstance()->getValues();
    $ref_enums['StudyType'] = ThirdPartyPartnerPanelScheduledStudyTypeValues::getInstance()->getValues();
    return $ref_enums;
  }


  public function getSelf(array $fields = array(), array $params = array(), $pending = false) {
    $this->assureId();

    $param_types = array(
    );
    $enums = array(
    );

    $request = new ApiRequest(
      $this->api,
      $this->data['id'],
      RequestInterface::METHOD_GET,
      '/',
      new ThirdPartyPartnerPanelScheduled(),
      'NODE',
      ThirdPartyPartnerPanelScheduled::getFieldsEnum()->getValues(),
      new TypeChecker($param_types, $enums)
    );
    $request->addParams($params);
    $request->addFields($fields);
    return $pending ? $request : $request->execute();
  }

}
