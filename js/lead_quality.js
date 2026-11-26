/**
 * Copyright (C) 2017-present, Meta, Inc.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * Fills the Lead event quality panel beside the Form Field Mapping table with
 * what the Dataset Quality API reports for the pixel's Lead events: a gauge of
 * their Event Match Quality (0-10, red to green) and the share of
 * them that include each customer information parameter. The panel is shown
 * only when Meta returns a score.
 * The admin-ajax URL (with its nonce) comes from meta_lead_quality_params.
 */
(function ($) {
  'use strict';

  if (typeof meta_lead_quality_params === 'undefined') {
    return;
  }

  var params = meta_lead_quality_params;
  var $panel = $('#fb-lead-quality');
  if (!$panel.length) {
    return;
  }

  // When Meta reports no coverage, that is all the panel can say: it does not
  // mean the events lacked customer information.
  var NOT_REPORTED = 'Not reported';

  function renderParameters(parameters) {
    var $list = $panel.find('.fb-lead-quality-parameters').empty();
    if (!parameters.length) {
      $('<li>')
        .addClass('fb-lead-quality-none')
        .text('Meta has not reported which parameters these events include.')
        .appendTo($list);
      return;
    }
    $.each(parameters, function (i, parameter) {
      var reported = parameter.coverage !== null;
      var $item = $('<li>')
        .addClass('fb-lead-quality-parameter')
        .attr('data-identifier', parameter.identifier);
      $('<span>')
        .addClass('fb-lead-quality-parameter-label')
        .text(parameter.label)
        .appendTo($item);
      $('<span>')
        .addClass('fb-lead-quality-parameter-value')
        .toggleClass('fb-lead-quality-parameter-value--unknown', !reported)
        .text(reported ? Math.round(parameter.coverage) + '%' : NOT_REPORTED)
        .appendTo($item);
      // No bar without a share: an empty one would read as 0%.
      if (reported) {
        $('<span>')
          .addClass('fb-lead-quality-bar')
          .attr('aria-hidden', 'true')
          .append(
            $('<span>')
              .addClass('fb-lead-quality-bar-fill')
              .css('width', parameter.coverage + '%'),
          )
          .appendTo($item);
      }
      $list.append($item);
    });
  }

  function render(result) {
    var $gauge = $panel.find('.fb-lead-quality-gauge');
    $gauge
      .attr('aria-valuenow', result.score)
      .attr('aria-valuetext', result.score + ' out of 10');
    // The arc's pathLength is 100: a 0-10 score fills score * 10 of it, red at
    // 0 through to green at 10. A zero score draws nothing (a round cap would
    // still show a dot).
    $gauge
      .find('.fb-lead-quality-fill')
      .attr('stroke-dasharray', Math.round(result.score * 100) / 10 + ' 100')
      .toggle(result.score > 0);
    $gauge.find('.fb-lead-quality-score').text(result.score.toFixed(1));

    renderParameters(result.parameters);
    $panel.removeClass('hidden');
  }

  // The request in flight, so a newer one (after a connection change) can
  // replace it and a late answer for the old pixel is never shown.
  var pending = null;

  // The panel is shown only when Meta returns a score; otherwise (not
  // connected, no Lead events yet, or the request failed) it stays hidden.
  function load() {
    if (pending) {
      pending.abort();
    }
    var request = $.ajax({ type: 'post', dataType: 'json', url: params.url });
    pending = request;
    request
      .done(function (response) {
        if (request !== pending) {
          return;
        }
        if (
          response &&
          response.success &&
          response.msg &&
          response.msg.status === 'ok' &&
          typeof response.msg.score === 'number'
        ) {
          render(response.msg);
        } else {
          $panel.addClass('hidden');
        }
      })
      .always(function () {
        if (request === pending) {
          pending = null;
        }
      });
  }

  // The connection flow (js/lib/fbe.js) fires this when the plugin connects,
  // switches pixel or disconnects without a page reload: hide what was shown
  // for the previous pixel, and ask again for the new one.
  $(document).on('facebook-pixel:connection-changed', function (e, connected) {
    $panel.addClass('hidden');
    if (connected) {
      load();
    } else if (pending) {
      pending.abort();
      pending = null;
    }
  });

  load();
})(jQuery);
