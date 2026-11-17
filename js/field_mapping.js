/**
 * Copyright (C) 2017-present, Meta, Inc.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * Drives the Form Field Mapping section of the settings page. Every form of
 * the active form plugins is listed with what it sends (its saved mapping, or
 * what automatic detection finds); Review / Edit opens one form in the editor,
 * a native <dialog> shown as a modal popup.
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
  var $editorStatus = $('#fb-mapping-editor-status');
  var $editor = $('#fb-mapping-editor');
  var editor = $editor[0];
  var $editorTitle = $('#fb-mapping-editor-title');
  var $fieldsTable = $('#fb-mapping-fields');
  var $fieldsBody = $fieldsTable.find('tbody');
  var $noFields = $('#fb-mapping-no-fields');
  var $suggested = $('#fb-mapping-suggested');
  var $missing = $('#fb-mapping-missing');
  var $missingList = $('#fb-mapping-missing-list');
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

  // While the editor is open the page behind it is covered, so its messages
  // are shown inside the editor.
  function showStatus(message, isError) {
    var $target = editor && editor.open ? $editorStatus : $status;
    $target
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

  // The "Send as" select for one form field, named after the field for
  // screen readers (the visible field label is in another cell).
  function buildTargetSelect(selected, fieldLabel) {
    var $select = $('<select>')
      .addClass('fb-mapping-target')
      .attr('aria-label', 'Send "' + fieldLabel + '" as');
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

  // A saved mapping whose fields are partly gone from the form (deleted, or
  // renamed in a plugin that identifies fields by name) sends nothing for
  // them; the status cell asks for a review.
  function statusCell(row) {
    var $cell = $('<td>').text(describeStatus(row));
    var gone = row.missing_fields || [];
    if (gone.length) {
      $('<span>')
        .addClass('fb-mapping-needs-review')
        .text(
          'Needs review: ' +
            (gone.length === 1
              ? 'mapped field ' + gone[0] + ' is'
              : 'mapped fields ' + gone.join(', ') + ' are') +
            ' no longer in the form',
        )
        .appendTo($cell);
    }
    return $cell;
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

  function formTitle(row) {
    return row.form_title || '(untitled)';
  }

  // The form's title, linked to the form plugin's own editor (new tab) when
  // the form exists, followed by its id: titles need not be unique, but the
  // plugin and id identify the form.
  function formCell(row) {
    var $cell = $('<td>');
    if (/^https?:\/\//i.test(row.edit_url || '')) {
      $('<a>')
        .addClass('fb-mapping-form-link')
        .attr({
          href: row.edit_url,
          target: '_blank',
          rel: 'noopener noreferrer',
          title: 'Open this form in ' + row.integration_label + ' (new tab)',
        })
        .text(formTitle(row))
        .appendTo($cell);
    } else {
      $cell.append(document.createTextNode(formTitle(row)));
    }
    $('<span>')
      .addClass('fb-mapping-form-id')
      .text('#' + row.form_id)
      .appendTo($cell);
    return $cell;
  }

  // Saved mappings of a deleted form or an inactive plugin. The server lists
  // them after every available form.
  function isUnavailable(row) {
    return !row.active || row.missing;
  }

  function groupRow() {
    return $('<tr>')
      .addClass('fb-mapping-group')
      .append(
        $('<th colspan="5">')
          .text('No longer available')
          .append(
            $('<span>')
              .addClass('fb-mapping-group-desc')
              .text(
                'Saved mappings of forms that were deleted or whose plugin is not active. Reset them to remove them.',
              ),
          ),
      );
  }

  function actionLink(className, text, row) {
    return $('<a href="#">').addClass(className).text(text).data('row', row);
  }

  // One row per form. Forms of an inactive plugin, and saved mappings of a
  // form that no longer exists, can only be reset; they are grouped last.
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
    var groupShown = false;
    $.each(rows, function (i, row) {
      if (!groupShown && isUnavailable(row)) {
        groupShown = true;
        $listBody.append(groupRow());
      }
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
        .append(formCell(row))
        .append(statusCell(row))
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
    $missing.addClass('hidden');
    $missingList.text('');
    $saveButton.addClass('hidden');
    $rejectButton.addClass('hidden');
  }

  // Cancel, Escape and a successful save all close the dialog; its close
  // event then clears it.
  function closeEditor() {
    if (editor && editor.open) {
      editor.close();
    }
  }

  if (editor) {
    $editor.on('close', function () {
      current = null;
      resetFields();
      $editorStatus.stop(true, true).hide().text('');
    });
  }

  // Lists the saved mapping's fields the form no longer has.
  function renderMissingFields(missingFields) {
    if (!missingFields || !missingFields.length) {
      return;
    }
    $missingList.text(
      $.map(missingFields, function (field) {
        return (
          field.id + ' (' + (targetLabels[field.target] || field.target) + ')'
        );
      }).join(', '),
    );
    $missing.removeClass('hidden');
  }

  // One row per form field, preselecting the saved mapping or, for a form
  // without one, what automatic detection sends today. An unsaved form can
  // also be rejected, which saves a mapping with no fields.
  function renderFields(fields, saved, mapping, suggested, missingFields) {
    resetFields();
    renderMissingFields(missingFields);
    if (!fields || fields.length === 0) {
      $noFields.removeClass('hidden');
      return;
    }
    var selected = (saved ? mapping : suggested) || {};
    $.each(fields, function (i, field) {
      var $target = buildTargetSelect(selected[field.id] || '', field.label);
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
    var opened = { integration: row.integration_name, formId: row.form_id };
    current = opened;
    $editorTitle.text(
      formTitle(row) + ' #' + row.form_id + ' (' + row.integration_label + ')',
    );
    editor.showModal();
    loadFields(opened);
  }

  // Loads the fields of the form open in the editor. Also used to reload
  // them when the form changed while the editor was open.
  function loadFields(opened) {
    resetFields();
    post(params.getFieldsUrl, {
      integration: opened.integration,
      form_id: opened.formId,
    })
      .done(function (response) {
        // Ignore a late response for an editor that was closed meanwhile.
        if (current !== opened) {
          return;
        }
        if (response && response.success) {
          renderFields(
            response.msg.fields,
            response.msg.saved,
            response.msg.mapping,
            response.msg.suggested,
            response.msg.missing_fields,
          );
        } else {
          showStatus(params.loadError, true);
        }
      })
      .fail(function () {
        if (current === opened) {
          showStatus(params.loadError, true);
        }
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

  // The error code of a failed request, e.g. 'form_changed'.
  function errorCode(xhr) {
    var body = xhr && xhr.responseJSON;
    return body && body.msg && body.msg.code ? body.msg.code : '';
  }

  // Saves, rejects or resets, then shows the refreshed table. onFail, when
  // given, handles a failed request first and returns true if it did.
  function send(url, data, successMessage, errorMessage, onFail) {
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
      .fail(function (xhr) {
        if (!onFail || !onFail(xhr)) {
          showStatus(errorMessage, true);
        }
      });
  }

  // Nothing is saved when the form changed while the editor was open (a
  // mapped field is gone): its fields are reloaded for another review.
  function saveMapping(mapping, successMessage) {
    if (!current) {
      return;
    }
    var opened = current;
    send(
      params.saveUrl,
      {
        integration: current.integration,
        form_id: current.formId,
        mapping: JSON.stringify(mapping),
      },
      successMessage,
      params.saveError,
      function (xhr) {
        if (errorCode(xhr) !== 'form_changed') {
          return false;
        }
        if (current === opened) {
          loadFields(opened);
          showStatus(params.formChanged, true);
        }
        return true;
      },
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

  // What resetting a row's mapping does. A deleted form sends nothing either
  // way, and a form of an inactive plugin sends nothing until the plugin is
  // activated again, so for them the saved mapping is only removed.
  function describeReset(row) {
    if (row.missing) {
      return {
        confirm:
          'Remove the saved mapping of this deleted form? The form no longer exists, so nothing else changes.',
        done: 'Mapping removed.',
      };
    }
    if (!row.active) {
      return {
        confirm:
          'Remove the saved mapping of this form? If its plugin is activated again, the form will use automatic detection.',
        done: 'Mapping removed. If its plugin is activated again, the form will use automatic detection.',
      };
    }
    return {
      confirm:
        "Reset this form's mapping? The saved mapping is removed and the form goes back to automatic detection.",
      done: 'Mapping reset. The form uses automatic detection.',
    };
  }

  $listBody.on('click', '.fb-mapping-delete', function (e) {
    e.preventDefault();
    var row = $(this).data('row');
    var reset = describeReset(row);
    if (!window.confirm(reset.confirm)) {
      return;
    }
    send(
      params.deleteUrl,
      { integration: row.integration_name, form_id: row.form_id },
      reset.done,
      params.deleteError,
    );
  });

  renderList(params.list);
})(window.jQuery);
