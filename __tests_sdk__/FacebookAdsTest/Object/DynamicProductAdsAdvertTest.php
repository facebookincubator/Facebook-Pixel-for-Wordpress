<?php
/**
 * Copyright (c) 2014-present, Facebook, Inc. All rights reserved.
 *
 * You are hereby granted a non-exclusive, worldwide, royalty-free license to
 * use, copy, modify, and distribute this software in source code or binary
 * form for use in connection with the web services and APIs provided by
 * Facebook.
 *
 * As with any software that integrates with the Facebook platform, your use
 * of this software is subject to the Facebook Developer Principles and
 * Policies [http://developers.facebook.com/policy/]. This copyright notice
 * shall be included in all copies or substantial portions of the software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 *
 */

namespace FacebookPixelPlugin\FacebookAdsTest\Object;

use FacebookPixelPlugin\FacebookAds\Object\AdAccount;
use FacebookPixelPlugin\FacebookAds\Object\Campaign;
use FacebookPixelPlugin\FacebookAds\Object\AdCreative;
use FacebookPixelPlugin\FacebookAds\Object\Ad;
use FacebookPixelPlugin\FacebookAds\Object\AdSet;
use FacebookPixelPlugin\FacebookAds\Object\AdsPixel;
use FacebookPixelPlugin\FacebookAds\Object\ObjectStorySpec;
use FacebookPixelPlugin\FacebookAds\Object\ProductAudience;
use FacebookPixelPlugin\FacebookAds\Object\ProductCatalog;
use FacebookPixelPlugin\FacebookAds\Object\ProductSet;
use FacebookPixelPlugin\FacebookAds\Object\TargetingSpecs;
use FacebookPixelPlugin\FacebookAds\Object\ObjectStory\TemplateData;
use FacebookPixelPlugin\FacebookAds\Object\Fields\CampaignFields;
use FacebookPixelPlugin\FacebookAds\Object\Fields\AdCreativeFields;
use FacebookPixelPlugin\FacebookAds\Object\Fields\AdFields;
use FacebookPixelPlugin\FacebookAds\Object\Fields\AdSetFields;
use FacebookPixelPlugin\FacebookAds\Object\Fields\AdsPixelsFields;
use FacebookPixelPlugin\FacebookAds\Object\Fields\ObjectStory\TemplateDataFields;
use FacebookPixelPlugin\FacebookAds\Object\Fields\ObjectStorySpecFields;
use FacebookPixelPlugin\FacebookAds\Object\Fields\ProductAudienceFields;
use FacebookPixelPlugin\FacebookAds\Object\Fields\ProductCatalogFields;
use FacebookPixelPlugin\FacebookAds\Object\Fields\ProductSetFields;
use FacebookPixelPlugin\FacebookAds\Object\Fields\TargetingSpecsFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdObjectives;
use FacebookPixelPlugin\FacebookAds\Object\Values\BillingEvents;
use FacebookPixelPlugin\FacebookAds\Object\Values\CallToActionTypes;
use FacebookPixelPlugin\FacebookAds\Object\Values\OptimizationGoals;

class DynamicProductAdsAdvertTest extends AbstractCrudObjectTestCase {

  /**
   * @var ProductSet
   */
  protected $productSet;

  /**
   * @var ProductCatalog
   */
  protected $productCatalog;

  /**
   * @var AdsPixel
   */
  protected $adsPixel;

  /**
   * @var ProductAudience
   */
  protected $productAudience;

  /**
   * @var Campaign
   */
  protected $campaign;

  /**
   * @var AdSet
   */
  protected $adSet;

  /**
   * @var Ad
   */
  protected $ad;

  /**
   * @var AdCreative
   */
  protected $creative;

  public function setup() {
    parent::setup();

    $account = new AdAccount($this->getConfig()->accountId);
    $this->adsPixel = $account->getAdsPixels()->current();
    if ($this->adsPixel === null) {
      throw new \Exception('Ads Pixel is null');
    }

    $this->productCatalog =
      new ProductCatalog(null, $this->getConfig()->businessId);
    $this->productCatalog->setData(array(
      ProductCatalogFields::NAME => $this->getConfig()->testRunId,
    ));
    $this->productCatalog->create();

    $this->productSet =
      new ProductSet(null, $this->productCatalog->{ProductCatalogFields::ID});
    $this->productSet->setData(array(
      ProductSetFields::NAME => $this->getConfig()->testRunId,
      ProductSetFields::FILTER => array(
        'retailer_id' => array(
          'is_any' => array('pid1', 'pid2')
        )
      )
    ));
    $this->productSet->create();

    $this->productAudience
      = new ProductAudience(null, $this->getConfig()->accountId);
    $this->productAudience->setData(array(
      ProductAudienceFields::NAME => $this->getConfig()->testRunId,
      ProductAudienceFields::PRODUCT_SET_ID =>
        $this->productSet->{ProductSetFields::ID},
      ProductAudienceFields::PIXEL_ID =>
        $this->adsPixel->{AdsPixelsFields::ID},
      ProductAudienceFields::INCLUSIONS => array(array(
        'retention_seconds' => 86400,
        'rule' => array(
          'and' => array(
            array('event' => array('eq'=>'ViewContent')),
            array('userAgent' => array('i_contains'=>'iPhone'))
          )
        )
      )),
    ));

    $this->productAudience->create();
  }

  public function tearDown() {
    if ($this->productSet) {
      $this->productSet->deleteSelf();
      $this->productSet = null;
    }

    if ($this->productCatalog) {
      $this->productCatalog->deleteSelf();
      $this->productCatalog = null;
    }

    if ($this->productAudience) {
      $this->productAudience->deleteSelf();
      $this->productAudience = null;
    }

    if ($this->campaign) {
      $this->campaign->deleteSelf();
      $this->campaign = null;
    }

    if ($this->adSet) {
      $this->adSet->deleteSelf();
      $this->adSet = null;
    }

    if ($this->ad) {
      $this->ad->deleteSelf();
      $this->ad = null;
    }

    if ($this->creative) {
      $this->creative->deleteSelf();
      $this->creative = null;
    }

    parent::tearDown();
  }

  public function testDynamicProductAdsCreation() {
    $this->campaign = new Campaign(null, $this->getConfig()->accountId);
    $this->campaign->setData(array(
      CampaignFields::NAME => $this->getConfig()->testRunId,
      CampaignFields::OBJECTIVE => AdObjectives::PRODUCT_CATALOG_SALES,
      CampaignFields::PROMOTED_OBJECT =>
        array('product_catalog_id' =>
          $this->productCatalog->{ProductCatalogFields::ID})
     ));
    $this->assertCanCreate($this->campaign);

    $targeting = new TargetingSpecs();
    $targeting->{TargetingSpecsFields::GEO_LOCATIONS} =
      array('countries' => array('US'));
    $targeting->{TargetingSpecsFields::DYNAMIC_AUDIENCE_IDS} =
      array($this->productAudience->{ProductAudienceFields::ID});

    $this->adSet = new AdSet(null, $this->getConfig()->accountId);
    $this->adSet->setData(array(
      AdSetFields::NAME => $this->getConfig()->testRunId,
      AdSetFields::OPTIMIZATION_GOAL => OptimizationGoals::LINK_CLICKS,
      AdSetFields::BILLING_EVENT => BillingEvents::IMPRESSIONS,
      AdSetFields::BID_AMOUNT => 2,
      AdSetFields::DAILY_BUDGET => 2000,
      AdSetFields::CAMPAIGN_ID =>
        $this->campaign->{CampaignFields::ID},
      AdSetFields::TARGETING => $targeting,
      AdsetFields::PROMOTED_OBJECT =>
        array(
          'product_set_id' => $this->productSet->{ProductSetFields::ID},
        ),
    ));
    $this->assertCanCreate($this->adSet);

    $template = new TemplateData();
    $template->setData(array(
      TemplateDataFields::DESCRIPTION => '{{product.description}}',
      TemplateDataFields::LINK => 'http://www.example.com/',
      TemplateDataFields::MESSAGE => 'Test DPA Ad Message',
      TemplateDataFields::NAME => '{{product.name | titleize}}',
      TemplateDataFields::CALL_TO_ACTION => array(
        'type' => CallToActionTypes::SHOP_NOW
      ),
    ));

    $story = new ObjectStorySpec();
    $story->setData(array(
      ObjectStorySpecFields::PAGE_ID => $this->getConfig()->pageId,
      ObjectStorySpecFields::TEMPLATE_DATA => $template,
    ));

    $this->creative = new AdCreative(null, $this->getConfig()->accountId);
    $this->creative->setData(array(
      AdCreativeFields::NAME => $this->getConfig()->testRunId,
      AdCreativeFields::OBJECT_STORY_SPEC => $story,
      AdCreativeFields::PRODUCT_SET_ID =>
        $this->productSet->{ProductSetFields::ID},
    ));
    $this->assertCanCreate($this->creative);

    $this->ad = new Ad(null, $this->getConfig()->accountId);
    $this->ad->setData(array(
      AdFields::NAME => 'DPA Test Ad 1 '.$this->getConfig()->testRunId,
      AdFields::ADSET_ID => $this->adSet->{AdSetFields::ID},
      AdFields::CREATIVE =>
        array('creative_id' => $this->creative->{AdCreativeFields::ID}),
    ));
    $this->assertCanCreate($this->ad);
  }
}
