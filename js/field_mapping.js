/**
 * Copyright (C) 2017-present, Meta, Inc.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * Drives the Form Field Mapping section of the settings page: lists the saved
 * per-form mappings and edits one form's mapping. The admin-ajax URLs (with
 * nonces), labels and saved mappings come from meta_field_mapping_params.
 */
(function ($) {
  'use strict';

  if (typeof meta_field_mapping_params === 'undefined') {
    return;
  }

  var params = meta_field_mapping_params;

  var $integration = $('#fb-mapping-integration');
  var $form = $('#fb-mapping-form');
  var $fieldsTable = $('#fb-mapping-fields');
  var $fieldsBody = $fieldsTable.find('tbody');
  var $noFields = $('#fb-mapping-no-fields');
  var $suggested = $('#fb-mapping-suggested');
  var $saveButton = $('#fb-mapping-save');
  var $rejectButton = $('#fb-mapping-reject');
  var $status = $('#fb-mapping-status');
  var $listBody = $('#fb-mapping-list').find('tbody');

  function showStatus(message, isError) {
    $status
      .text(message)
      .toggleClass('fb-mapping-error', !!isError)
      .stop(true, true)
      .show()
      .delay(3000)
      .fadeOut();
  }

  function isActive(integrationName) {
    return Object.prototype.hasOwnProperty.call(
      params.integrations,
      integrationName,
    );
  }

  function post(url, data) {
    return $.ajax({ type: 'post', dataType: 'json', url: url, data: data });
  }

  // The "Send as" select for one form field.
  function buildTargetSelect(selected) {
    var $select = $('<select>').addClass('fb-mapping-target');
    $select.append($('<option>').val('').text('Do not map'));
    $.each(params.targets, function (i, target) {
      $('<option>')
        .val(target.value)
        .text(target.label)
        .prop('selected', target.value === selected)
        .appendTo($select);
    });
    return $select;
  }

  // The overview table of saved mappings. Mappings of an inactive form
  // plugin can only be deleted: its forms cannot be listed for editing.
  function renderList(list) {
    $listBody.empty();
    if (!list || list.length === 0) {
      $listBody.append(
        $('<tr>').append($('<td colspan="4">').text('No mappings saved yet.')),
      );
      return;
    }
    $.each(list, function (i, row) {
      var active = isActive(row.integration_name);
      var $actions = $('<td>');
      if (active) {
        $('<a href="#">')
          .addClass('fb-mapping-edit')
          .text('Edit')
          .data('integration', row.integration_name)
          .data('form_id', row.form_id)
          .appendTo($actions);
        $actions.append(document.createTextNode(' | '));
      }
      $('<a href="#">')
        .addClass('fb-mapping-delete')
        .text('Reset')
        .attr('title', 'Remove the saved mapping and use automatic detection')
        .data('integration', row.integration_name)
        .data('form_id', row.form_id)
        .appendTo($actions);

      $('<tr>')
        .append(
          $('<td>').text(
            active
              ? params.integrations[row.integration_name]
              : row.integration_name + ' (inactive)',
          ),
        )
        .append($('<td>').text(row.form_title || row.form_id))
        .append(
          $('<td>').text(
            row.mapped_count > 0
              ? row.mapped_count
              : 'None (rejected, no Lead events)',
          ),
        )
        .append($actions)
        .appendTo($listBody);
    });
  }

  function resetFields() {
    $fieldsBody.empty();
    $fieldsTable.addClass('hidden');
    $noFields.addClass('hidden');
    $suggested.addClass('hidden');
    $saveButton.addClass('hidden');
    $rejectButton.addClass('hidden');
  }

  // One row per form field, preselecting the saved mapping or, for a form
  // without one, what automatic detection sends today. An unsaved form can
  // also be rejected, which saves a mapping with no fields.
  function renderFields(fields, saved, mapping, suggested) {
    resetFields();
    if (!fields || fields.length === 0) {
      $noFields.removeClass('hidden');
      return;
    }
    var selected = (saved ? mapping : suggested) || {};
    $.each(fields, function (i, field) {
      var $target = buildTargetSelect(selected[field.id] || '');
      $target.data('field_id', field.id);
      $('<tr>')
        .append($('<td>').text(field.label))
        .append($('<td>').text(field.type))
        .append($('<td>').append($target))
        .appendTo($fieldsBody);
    });
    $suggested.toggleClass('hidden', !!saved);
    $rejectButton.toggleClass('hidden', !!saved);
    $fieldsTable.removeClass('hidden');
    $saveButton.removeClass('hidden');
  }

  function resetForms() {
    $form
      .empty()
      .append($('<option>').val('').text('Select a form'))
      .prop('disabled', true);
  }

  function loadForms(integration, selectedFormId) {
    return post(params.getFormsUrl, { integration: integration })
      .then(function (response) {
        if (!response || !response.success) {
          return $.Deferred().reject();
        }
        resetForms();
        $.each(response.msg.forms, function (i, form) {
          $('<option>')
            .val(form.id)
            .text(form.title || form.id)
            .prop('selected', String(form.id) === String(selectedFormId))
            .appendTo($form);
        });
        $form.prop('disabled', false);
      })
      .fail(function () {
        showStatus(params.loadError, true);
      });
  }

  function loadFields(integration, formId) {
    return post(params.getFieldsUrl, {
      integration: integration,
      form_id: formId,
    })
      .done(function (response) {
        if (response && response.success) {
          renderFields(
            response.msg.fields,
            response.msg.saved,
            response.msg.mapping,
            response.msg.suggested,
          );
        } else {
          showStatus(params.loadError, true);
        }
      })
      .fail(function () {
        showStatus(params.loadError, true);
      });
  }

  function collectMapping() {
    var mapping = {};
    $fieldsBody.find('.fb-mapping-target').each(function () {
      var value = $(this).val();
      if (value) {
        mapping[$(this).data('field_id')] = value;
      }
    });
    return mapping;
  }

  function onListResponse(successMessage, errorMessage) {
    return {
      done: function (response) {
        if (response && response.success) {
          renderList(response.msg.list);
          showStatus(successMessage, false);
        } else {
          showStatus(errorMessage, true);
        }
      },
      fail: function () {
        showStatus(errorMessage, true);
      },
    };
  }

  $integration.on('change', function () {
    var integration = $(this).val();
    resetFields();
    resetForms();
    if (integration) {
      loadForms(integration, null);
    }
  });

  $form.on('change', function () {
    var integration = $integration.val();
    var formId = $(this).val();
    resetFields();
    if (integration && formId) {
      loadFields(integration, formId);
    }
  });

  function saveMapping(mapping, successMessage) {
    var integration = $integration.val();
    var formId = $form.val();
    if (!integration || !formId) {
      return;
    }
    var handlers = onListResponse(successMessage, params.saveError);
    post(params.saveUrl, {
      integration: integration,
      form_id: formId,
      mapping: JSON.stringify(mapping),
    })
      .done(function (response) {
        handlers.done(response);
        if (response && response.success) {
          // The form now has a saved mapping: no more suggestion or reject.
          $suggested.addClass('hidden');
          $rejectButton.addClass('hidden');
        }
      })
      .fail(handlers.fail);
  }

  // A mapping with no fields rejects the form: it stops sending Lead events.
  function confirmReject() {
    return window.confirm(
      'Reject this mapping? Submitting this form will stop sending Lead events until you save a mapping with at least one field.',
    );
  }

  var REJECTED_MESSAGE =
    'Mapping rejected. This form no longer sends Lead events.';

  $saveButton.on('click', function (e) {
    e.preventDefault();
    var mapping = collectMapping();
    if ($.isEmptyObject(mapping)) {
      if (confirmReject()) {
        saveMapping(mapping, REJECTED_MESSAGE);
      }
      return;
    }
    saveMapping(mapping, 'Mapping saved.');
  });

  $rejectButton.on('click', function (e) {
    e.preventDefault();
    if (!confirmReject()) {
      return;
    }
    $fieldsBody.find('.fb-mapping-target').val('');
    saveMapping({}, REJECTED_MESSAGE);
  });

  $listBody.on('click', '.fb-mapping-edit', function (e) {
    e.preventDefault();
    var integration = $(this).data('integration');
    var formId = String($(this).data('form_id'));
    $integration.val(integration);
    resetFields();
    loadForms(integration, formId).done(function () {
      loadFields(integration, formId);
    });
    $('html, body').animate(
      { scrollTop: $('#fb-mapping-editor').offset().top - 40 },
      300,
    );
  });

  $listBody.on('click', '.fb-mapping-delete', function (e) {
    e.preventDefault();
    if (
      !window.confirm(
        "Reset this form's mapping? The saved mapping is removed and the form goes back to automatic detection.",
      )
    ) {
      return;
    }
    var handlers = onListResponse(
      'Mapping reset. The form uses automatic detection.',
      params.deleteError,
    );
    post(params.deleteUrl, {
      integration: $(this).data('integration'),
      form_id: String($(this).data('form_id')),
    })
      .done(handlers.done)
      .fail(handlers.fail);
  });

  renderList(params.list);
})(window.jQuery);
