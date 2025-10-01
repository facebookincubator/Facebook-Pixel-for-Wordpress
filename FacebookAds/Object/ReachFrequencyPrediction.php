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
use FacebookPixelPlugin\FacebookAds\Object\Fields\ReachFrequencyPredictionFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\ReachFrequencyPredictionActionValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\ReachFrequencyPredictionBuyingTypeValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\ReachFrequencyPredictionInstreamPackagesValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class ReachFrequencyPrediction extends AbstractCrudObject {

  /**
   * @deprecated getEndpoint function is deprecated
   */
  protected function getEndpoint() {
    return 'reachfrequencypredictions';
  }

  /**
   * @return ReachFrequencyPredictionFields
   */
  public static function getFieldsEnum() {
    return ReachFrequencyPredictionFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['Action'] = ReachFrequencyPredictionActionValues::getInstance()->getValues();
    $ref_enums['BuyingType'] = ReachFrequencyPredictionBuyingTypeValues::getInstance()->getValues();
    $ref_enums['InstreamPackages'] = ReachFrequencyPredictionInstreamPackagesValues::getInstance()->getValues();
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
      new ReachFrequencyPrediction(),
      'NODE',
      ReachFrequencyPrediction::getFieldsEnum()->getValues(),
      new TypeChecker($param_types, $enums)
    );
    $request->addParams($params);
    $request->addFields($fields);
    return $pending ? $request : $request->execute();
  }

}
