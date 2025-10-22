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
use FacebookPixelPlugin\FacebookAds\Object\Fields\ProductFeedUploadFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\ProductFeedUploadErrorErrorPriorityValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\ProductFeedUploadInputMethodValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class ProductFeedUpload extends AbstractCrudObject {

  /**
   * @deprecated getEndpoint function is deprecated
   */
  protected function getEndpoint() {
    return 'uploads';
  }

  /**
   * @return ProductFeedUploadFields
   */
  public static function getFieldsEnum() {
    return ProductFeedUploadFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['InputMethod'] = ProductFeedUploadInputMethodValues::getInstance()->getValues();
    return $ref_enums;
  }


  public function createErrorReport(array $fields = array(), array $params = array(), $pending = false) {
    $this->assureId();

    $param_types = array(
    );
    $enums = array(
    );

    $request = new ApiRequest(
      $this->api,
      $this->data['id'],
      RequestInterface::METHOD_POST,
      '/error_report',
      new ProductFeedUpload(),
      'EDGE',
      ProductFeedUpload::getFieldsEnum()->getValues(),
      new TypeChecker($param_types, $enums)
    );
    $request->addParams($params);
    $request->addFields($fields);
    return $pending ? $request : $request->execute();
  }

  public function getErrors(array $fields = array(), array $params = array(), $pending = false) {
    $this->assureId();

    $param_types = array(
      'error_priority' => 'error_priority_enum',
    );
    $enums = array(
      'error_priority_enum' => ProductFeedUploadErrorErrorPriorityValues::getInstance()->getValues(),
    );

    $request = new ApiRequest(
      $this->api,
      $this->data['id'],
      RequestInterface::METHOD_GET,
      '/errors',
      new ProductFeedUploadError(),
      'EDGE',
      ProductFeedUploadError::getFieldsEnum()->getValues(),
      new TypeChecker($param_types, $enums)
    );
    $request->addParams($params);
    $request->addFields($fields);
    return $pending ? $request : $request->execute();
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
      new ProductFeedUpload(),
      'NODE',
      ProductFeedUpload::getFieldsEnum()->getValues(),
      new TypeChecker($param_types, $enums)
    );
    $request->addParams($params);
    $request->addFields($fields);
    return $pending ? $request : $request->execute();
  }

}
