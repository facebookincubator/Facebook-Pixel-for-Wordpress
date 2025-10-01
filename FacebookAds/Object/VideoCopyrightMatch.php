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
use FacebookPixelPlugin\FacebookAds\Object\Fields\VideoCopyrightMatchFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\VideoCopyrightMatchActionReasonValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\VideoCopyrightMatchActionValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\VideoCopyrightMatchMatchContentTypeValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class VideoCopyrightMatch extends AbstractCrudObject {

  /**
   * @return VideoCopyrightMatchFields
   */
  public static function getFieldsEnum() {
    return VideoCopyrightMatchFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['Action'] = VideoCopyrightMatchActionValues::getInstance()->getValues();
    $ref_enums['ActionReason'] = VideoCopyrightMatchActionReasonValues::getInstance()->getValues();
    $ref_enums['MatchContentType'] = VideoCopyrightMatchMatchContentTypeValues::getInstance()->getValues();
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
      new VideoCopyrightMatch(),
      'NODE',
      VideoCopyrightMatch::getFieldsEnum()->getValues(),
      new TypeChecker($param_types, $enums)
    );
    $request->addParams($params);
    $request->addFields($fields);
    return $pending ? $request : $request->execute();
  }

}
