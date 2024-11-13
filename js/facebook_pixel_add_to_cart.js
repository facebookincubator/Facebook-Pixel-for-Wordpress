jQuery(document).ready(function ($) {
    $('.edd-add-to-cart').click(function (e) {
        e.preventDefault();

        var _this = $(this), form = _this.closest('form');
        var download = _this.data('download-id');
        var currency = $('.edd_purchase_' + download + ' meta[itemprop="priceCurrency\']').attr('content');
        var form = _this.parents('form').last();
        var value = 0;
        var variable_price = _this.data('variable-price');
        var event_id = form.find("input[name='facebook_event_id']").val();

        if (variable_price == 'yes') {
            form.find('.edd_price_option_' + download + ':checked', form).each(function (index) {
                value = $(this).data('price');
            });
        } else {
            if (_this.data('price') && _this.data('price') > 0) {
                value = _this.data('price');
            }
        }

        var param = {
            'content_ids': [download],
            'content_type': 'product',
            'currency': currency,
            'fb_integration_key': facebookPixelData.fb_integration_key,
            'value': value
        };

        fbq('set', 'agent', facebookPixelData.agent_string, facebookPixelData.pixel_id);
        if (event_id) {
            fbq('track', 'AddToCart', param, { 'eventID': event_id });
        } else {
            fbq('track', 'AddToCart', param);
        }
    });
});
