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
use FacebookPixelPlugin\FacebookAdsObject\Fields\AdAccountUserFields;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class AdAccountUser extends AbstractCrudObject {

  /**
   * @deprecated getEndpoint function is deprecated
   */
  protected function getEndpoint() {
    return 'users';
  }

  /**
   * @return AdAccountUserFields
   */
  public static function getFieldsEnum() {
    return AdAccountUserFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    return $ref_enums;
  }


  public function getAdAccounts(
    array $fields = array(), array $params = array()) {
    return $this->getManyByConnection(AdAccount::className(), $fields, $params);
  }

  public function getAdAccountGroups(
    array $fields = array(), array $params = array()) {
    return $this->getManyByConnection(
      AdAccountGroup::className(),
      $fields,
      $params);
  }
}
