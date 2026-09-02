/**
 * Copyright (C) 2017-present, Meta, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the License.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

jQuery(document).ready(function ($) {
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

    fbq('set', 'agent', agentString, pixelId);
    if (event_id) {
      fbq('track', 'AddToCart', param, { eventID: event_id });
    } else {
      fbq('track', 'AddToCart', param);
    }
  });
});
