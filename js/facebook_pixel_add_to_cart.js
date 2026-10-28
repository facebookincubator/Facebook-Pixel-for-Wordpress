/**
 * Copyright (C) 2017-present, Meta, Inc.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

jQuery(document).ready(function ($) {
  // Every add-to-cart form carries an event id the server minted when it
  // rendered the page. The form posts the id back, so the Conversions API
  // event and the Pixel event share it. A cached copy of the page hands the
  // same id to every visitor who receives it, so on a stale page the forms
  // get ids minted here instead, before any click: the browser fires them,
  // the form posts them, and they belong to this visitor alone.
  if (
    window.FacebookSignal &&
    window.FacebookSignal.isStalePage &&
    window.FacebookSignal.generateEventId &&
    window.FacebookSignal.isStalePage()
  ) {
    $("input[name='facebook_event_id']").each(function () {
      $(this).val(window.FacebookSignal.generateEventId());
    });
  }

  $('.edd-add-to-cart').click(function (e) {
    e.preventDefault();

    var integrationKey =
      facebookPixelData.fbIntegrationKey ||
      facebookPixelData.fb_integration_key;
    var trackingName =
      facebookPixelData.trackingName || facebookPixelData.tracking_name;
    var agentString =
      facebookPixelData.agentString || facebookPixelData.agent_string;
    var pixelId = facebookPixelData.pixelId || facebookPixelData.pixel_id;
    var _this = $(this),
      form = _this.closest('form');
    var download = _this.data('download-id');
    var currency = $(
      '.edd_purchase_' + download + ' meta[itemprop="priceCurrency\']',
    ).attr('content');
    var form = _this.parents('form').last();
    var value = 0;
    var variable_price = _this.data('variable-price');
    var event_id = form.find("input[name='facebook_event_id']").val();

    if (variable_price == 'yes') {
      form
        .find('.edd_price_option_' + download + ':checked', form)
        .each(function (index) {
          value = $(this).data('price');
        });
    } else {
      if (_this.data('price') && _this.data('price') > 0) {
        value = _this.data('price');
      }
    }

    var param = {
      content_ids: [download],
      content_type: 'product',
      currency: currency,
      fb_integration_tracking: trackingName || integrationKey,
      value: value,
    };

    if (window.FacebookSignal && window.FacebookSignal._held) {
      if (event_id) {
        param.eventID = event_id;
      }
      window.FacebookSignal.trackEvent('AddToCart', param);
      return;
    }

    if (window.FacebookSignal && window.FacebookSignal.tagAgent) {
      agentString = window.FacebookSignal.tagAgent(agentString);
    }
    fbq('set', 'agent', agentString, pixelId);
    if (event_id) {
      fbq('track', 'AddToCart', param, { eventID: event_id });
    } else {
      fbq('track', 'AddToCart', param);
    }
  });
});
