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
use FacebookPixelPlugin\FacebookAds\Object\Fields\TransactableItemFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\OverrideDetailsTypeValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\TransactableItemImageFetchStatusValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\TransactableItemVisibilityValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class TransactableItem extends AbstractCrudObject {

  /**
   * @return TransactableItemFields
   */
  public static function getFieldsEnum() {
    return TransactableItemFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['ImageFetchStatus'] = TransactableItemImageFetchStatusValues::getInstance()->getValues();
    $ref_enums['Visibility'] = TransactableItemVisibilityValues::getInstance()->getValues();
    return $ref_enums;
  }


  public function getChannelsToIntegrityStatus(array $fields = array(), array $params = array(), $pending = false) {
    $this->assureId();

    $param_types = array(
    );
    $enums = array(
    );

    $request = new ApiRequest(
      $this->api,
      $this->data['id'],
      RequestInterface::METHOD_GET,
      '/channels_to_integrity_status',
      new CatalogItemChannelsToIntegrityStatus(),
      'EDGE',
      CatalogItemChannelsToIntegrityStatus::getFieldsEnum()->getValues(),
      new TypeChecker($param_types, $enums)
    );
    $request->addParams($params);
    $request->addFields($fields);
    return $pending ? $request : $request->execute();
  }

  public function getOverrideDetails(array $fields = array(), array $params = array(), $pending = false) {
    $this->assureId();

    $param_types = array(
      'keys' => 'list<string>',
      'type' => 'type_enum',
    );
    $enums = array(
      'type_enum' => OverrideDetailsTypeValues::getInstance()->getValues(),
    );

    $request = new ApiRequest(
      $this->api,
      $this->data['id'],
      RequestInterface::METHOD_GET,
      '/override_details',
      new OverrideDetails(),
      'EDGE',
      OverrideDetails::getFieldsEnum()->getValues(),
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
      new TransactableItem(),
      'NODE',
      TransactableItem::getFieldsEnum()->getValues(),
      new TypeChecker($param_types, $enums)
    );
    $request->addParams($params);
    $request->addFields($fields);
    return $pending ? $request : $request->execute();
  }

}
