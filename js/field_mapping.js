/**
 * Copyright (C) 2017-present, Meta, Inc.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * Drives the Form Field Mapping section of the settings page. Every form of
 * the active form plugins is listed with what it sends (its saved mapping, or
 * what automatic detection finds); Review / Edit opens one form in the editor.
 * The admin-ajax URLs (with nonces), targets and rows come from
 * meta_field_mapping_params.
 */
(function ($) {
  'use strict';

  if (typeof meta_field_mapping_params === 'undefined') {
    return;
  }

  var params = meta_field_mapping_params;

  var $listBody = $('#fb-mapping-list').find('tbody');
  var $status = $('#fb-mapping-status');
  var $editor = $('#fb-mapping-editor');
  var $editorTitle = $('#fb-mapping-editor-title');
  var $fieldsTable = $('#fb-mapping-fields');
  var $fieldsBody = $fieldsTable.find('tbody');
  var $noFields = $('#fb-mapping-no-fields');
  var $suggested = $('#fb-mapping-suggested');
  var $saveButton = $('#fb-mapping-save');
  var $rejectButton = $('#fb-mapping-reject');

  // The form open in the editor: { integration, formId }.
  var current = null;

  var STATUS_LABELS = {
    automatic: 'Automatic (not reviewed)',
    saved: 'Saved',
    rejected: 'Rejected (no Lead events)',
  };

  var targetLabels = {};
  $.each(params.targets, function (i, target) {
    targetLabels[target.value] = target.label;
  });

  function showStatus(message, isError) {
    $status
      .text(message)
      .toggleClass('fb-mapping-error', !!isError)
      .stop(true, true)
      .show()
      .delay(4000)
      .fadeOut();
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

  function describeStatus(row) {
    var label = STATUS_LABELS[row.status] || row.status;
    if (!row.active) {
      return label + ' (plugin not active)';
    }
    return row.missing ? label + ' (form not found)' : label;
  }

  function describeTargets(row) {
    if (row.status === 'rejected') {
      return 'Nothing (no Lead events)';
    }
    if (!row.targets.length) {
      return row.status === 'automatic' ? 'Nothing detected' : 'Nothing';
    }
    return $.map(row.targets, function (target) {
      return targetLabels[target] || target;
    }).join(', ');
  }

  function actionLink(className, text, row) {
    return $('<a href="#">').addClass(className).text(text).data('row', row);
  }

  // One row per form. Forms of an inactive plugin, and saved mappings of a
  // form that no longer exists, can only be reset.
  function renderList(rows) {
    $listBody.empty();
    if (!rows || rows.length === 0) {
      $listBody.append(
        $('<tr>').append(
          $('<td colspan="5">').text(
            'No forms found in the active form plugins.',
          ),
        ),
      );
      return;
    }
    $.each(rows, function (i, row) {
      var $actions = $('<td>');
      if (row.active && !row.missing) {
        actionLink(
          'fb-mapping-edit',
          row.status === 'automatic' ? 'Review' : 'Edit',
          row,
        ).appendTo($actions);
      }
      if (row.status !== 'automatic') {
        if ($actions.children().length) {
          $actions.append(document.createTextNode(' | '));
        }
        actionLink('fb-mapping-delete', 'Reset', row)
          .attr('title', 'Remove the saved mapping and use automatic detection')
          .appendTo($actions);
      }

      $('<tr>')
        .addClass('fb-mapping-row--' + row.status)
        .append($('<td>').text(row.integration_label))
        .append($('<td>').text(row.form_title || row.form_id))
        .append($('<td>').text(describeStatus(row)))
        .append($('<td>').text(describeTargets(row)))
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

  function closeEditor() {
    current = null;
    resetFields();
    $editor.addClass('hidden');
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

  function openEditor(row) {
    current = { integration: row.integration_name, formId: row.form_id };
    $editorTitle.text(
      (row.form_title || row.form_id) + ' (' + row.integration_label + ')',
    );
    resetFields();
    $editor.removeClass('hidden');
    post(params.getFieldsUrl, {
      integration: current.integration,
      form_id: current.formId,
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
    $('html, body').animate({ scrollTop: $editor.offset().top - 40 }, 300);
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

  // Saves, rejects or resets, then shows the refreshed table.
  function send(url, data, successMessage, errorMessage) {
    post(url, data)
      .done(function (response) {
        if (response && response.success) {
          renderList(response.msg.list);
          closeEditor();
          showStatus(successMessage, false);
        } else {
          showStatus(errorMessage, true);
        }
      })
      .fail(function () {
        showStatus(errorMessage, true);
      });
  }

  function saveMapping(mapping, successMessage) {
    if (!current) {
      return;
    }
    send(
      params.saveUrl,
      {
        integration: current.integration,
        form_id: current.formId,
        mapping: JSON.stringify(mapping),
      },
      successMessage,
      params.saveError,
    );
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
    if (confirmReject()) {
      saveMapping({}, REJECTED_MESSAGE);
    }
  });

  $('#fb-mapping-cancel').on('click', function (e) {
    e.preventDefault();
    closeEditor();
  });

  $listBody.on('click', '.fb-mapping-edit', function (e) {
    e.preventDefault();
    openEditor($(this).data('row'));
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
    var row = $(this).data('row');
    send(
      params.deleteUrl,
      { integration: row.integration_name, form_id: row.form_id },
      'Mapping reset. The form uses automatic detection.',
      params.deleteError,
    );
  });

  renderList(params.list);
})(window.jQuery);
