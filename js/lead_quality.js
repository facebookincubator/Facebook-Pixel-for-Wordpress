/**
 * Copyright (C) 2017-present, Meta, Inc.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * Fills the Lead event quality panel beside the Form Field Mapping table with
 * what the Dataset Quality API reports for the pixel's Lead events: a gauge of
 * their Event Match Quality (0-10, rated as in Events Manager) and the share of
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

  var RATINGS = { poor: 'Poor', ok: 'OK', good: 'Good', great: 'Great' };

  function formatPercent(value) {
    return value === null ? '' : Math.round(value) + '%';
  }

  function renderParameters(parameters) {
    var $list = $panel.find('.fb-lead-quality-parameters').empty();
    if (!parameters.length) {
      $('<li>')
        .addClass('fb-lead-quality-none')
        .text('No customer information received.')
        .appendTo($list);
      return;
    }
    $.each(parameters, function (i, parameter) {
      var coverage = parameter.coverage === null ? 0 : parameter.coverage;
      var $item = $('<li>')
        .addClass('fb-lead-quality-parameter')
        .attr('data-identifier', parameter.identifier);
      $('<span>')
        .addClass('fb-lead-quality-parameter-label')
        .text(parameter.label)
        .appendTo($item);
      $('<span>')
        .addClass('fb-lead-quality-parameter-value')
        .text(formatPercent(parameter.coverage))
        .appendTo($item);
      $('<span>')
        .addClass('fb-lead-quality-bar')
        .attr('aria-hidden', 'true')
        .append(
          $('<span>')
            .addClass('fb-lead-quality-bar-fill')
            .css('width', coverage + '%'),
        )
        .appendTo($item);
      $list.append($item);
    });
  }

  function render(result) {
    var rating = RATINGS[result.rating] || '';
    var $gauge = $panel.find('.fb-lead-quality-gauge');
    $gauge
      .attr('aria-valuenow', result.score)
      .attr('aria-valuetext', result.score + ' out of 10, ' + rating)
      .attr('data-rating', result.rating);
    // The arc's pathLength is 100: a 0-10 score fills score * 10 of it. A zero
    // score draws nothing (a round cap would still show a dot).
    $gauge
      .find('.fb-lead-quality-fill')
      .attr('stroke-dasharray', result.score * 10 + ' 100')
      .toggle(result.score > 0);
    $gauge.find('.fb-lead-quality-score').text(result.score.toFixed(1));
    $gauge.find('.fb-lead-quality-rating').text(rating);

    renderParameters(result.parameters);
    $panel.removeClass('hidden');
  }

  // The panel is shown only when Meta returns a score; otherwise (not
  // connected, no Lead events yet, or the request failed) it stays hidden.
  $.ajax({ type: 'post', dataType: 'json', url: params.url }).done(
    function (response) {
      if (
        response &&
        response.success &&
        response.msg &&
        response.msg.status === 'ok' &&
        typeof response.msg.score === 'number'
      ) {
        render(response.msg);
      }
    },
  );
})(jQuery);
