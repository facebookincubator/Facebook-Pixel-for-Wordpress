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
 *
 * Drives the admin Form Field Mapping screen: lists existing per-form
 * mappings and provides an editor to create or modify a single form's
 * mapping. All persistence goes through admin-ajax endpoints whose URLs
 * (with nonces) and action names are provided in meta_field_mapping_params.
 */
(function ($) {
    "use strict";

    if (typeof meta_field_mapping_params === "undefined") {
        return;
    }

    var params = meta_field_mapping_params;

    var $integration = $("#fb-mapping-integration");
    var $form = $("#fb-mapping-form");
    var $fieldsTable = $("#fb-mapping-fields");
    var $fieldsBody = $fieldsTable.find("tbody");
    var $noFields = $("#fb-mapping-no-fields");
    var $saveButton = $("#fb-mapping-save");
    var $status = $("#fb-mapping-status");
    var $listBody = $("#fb-mapping-list").find("tbody");

    function escapeHtml(value) {
        return $("<div>").text(value == null ? "" : value).html();
    }

    function showStatus(message, isError) {
        $status
            .text(message)
            .css("color", isError ? "#d63638" : "#008a20")
            .stop(true, true)
            .show()
            .delay(3000)
            .fadeOut();
    }

    function integrationLabel(trackingName) {
        return params.integrations[trackingName] || trackingName;
    }

    // Builds the "Standard Field" <select> for a single form field row.
    function buildTargetSelect(selected) {
        var $select = $("<select>").addClass("fb-mapping-target");
        $select.append(
            $("<option>").val("").text("— Do not map —")
        );
        $.each(params.groupedFields, function (groupLabel, fields) {
            var $group = $("<optgroup>").attr("label", groupLabel);
            $.each(fields, function (key, label) {
                var $option = $("<option>").val(key).text(label);
                if (key === selected) {
                    $option.prop("selected", true);
                }
                $group.append($option);
            });
            $select.append($group);
        });
        return $select;
    }

    // Renders the overview table of all stored mappings.
    function renderList(list) {
        $listBody.empty();
        if (!list || list.length === 0) {
            $listBody.append(
                $("<tr>").append(
                    $("<td colspan='4'>").text("No mappings configured yet.")
                )
            );
            return;
        }
        $.each(list, function (i, row) {
            var $actions = $("<td>");
            $("<a href='#'>")
                .addClass("fb-mapping-edit")
                .text("Edit")
                .data("integration", row.tracking_name)
                .data("form_id", row.form_id)
                .appendTo($actions);
            $actions.append(document.createTextNode(" | "));
            $("<a href='#'>")
                .addClass("fb-mapping-delete")
                .text("Delete")
                .data("integration", row.tracking_name)
                .data("form_id", row.form_id)
                .appendTo($actions);

            $("<tr>")
                .append(
                    $("<td>").text(integrationLabel(row.tracking_name))
                )
                .append($("<td>").text(row.form_title || row.form_id))
                .append($("<td>").text(row.mapped_count))
                .append($actions)
                .appendTo($listBody);
        });
    }

    function resetFields() {
        $fieldsBody.empty();
        $fieldsTable.addClass("hidden");
        $noFields.addClass("hidden");
        $saveButton.addClass("hidden");
    }

    // Renders one row per form field, preselecting any saved target.
    function renderFields(fields, mapping) {
        $fieldsBody.empty();
        mapping = mapping || {};

        if (!fields || fields.length === 0) {
            $fieldsTable.addClass("hidden");
            $noFields.removeClass("hidden");
            $saveButton.addClass("hidden");
            return;
        }

        $.each(fields, function (i, field) {
            var $target = buildTargetSelect(mapping[field.id] || "");
            $target.data("field_id", field.id);
            $("<tr>")
                .append($("<td>").text(field.label || field.id))
                .append($("<td>").text(field.type || ""))
                .append($("<td>").append($target))
                .appendTo($fieldsBody);
        });

        $fieldsTable.removeClass("hidden");
        $noFields.addClass("hidden");
        $saveButton.removeClass("hidden");
    }

    function populateForms(forms, selectedFormId) {
        $form.empty().append($("<option>").val("").text("Select a form"));
        $.each(forms || [], function (i, form) {
            var $option = $("<option>").val(form.id).text(
                form.title || form.id
            );
            if (selectedFormId && String(form.id) === String(selectedFormId)) {
                $option.prop("selected", true);
            }
            $form.append($option);
        });
        $form.prop("disabled", false);
    }

    function loadForms(integration, selectedFormId, onLoaded) {
        $.ajax({
            type: "post",
            dataType: "json",
            url: params.getFormsUrl,
            data: { action: params.getFormsAction, integration: integration }
        })
            .done(function (response) {
                if (response && response.success && response.msg) {
                    populateForms(response.msg.forms, selectedFormId);
                    if (onLoaded) {
                        onLoaded();
                    }
                }
            })
            .fail(function () {
                showStatus(params.saveError, true);
            });
    }

    function loadFields(integration, formId) {
        $.ajax({
            type: "post",
            dataType: "json",
            url: params.getFieldsUrl,
            data: {
                action: params.getFieldsAction,
                integration: integration,
                form_id: formId
            }
        })
            .done(function (response) {
                if (response && response.success && response.msg) {
                    renderFields(response.msg.fields, response.msg.mapping);
                }
            })
            .fail(function () {
                showStatus(params.saveError, true);
            });
    }

    function collectMapping() {
        var mapping = {};
        $fieldsBody.find(".fb-mapping-target").each(function () {
            var value = $(this).val();
            if (value) {
                mapping[$(this).data("field_id")] = value;
            }
        });
        return mapping;
    }

    function saveMapping() {
        var integration = $integration.val();
        var formId = $form.val();
        if (!integration || !formId) {
            return;
        }
        var formTitle = $form.find("option:selected").text().trim();

        $.ajax({
            type: "post",
            dataType: "json",
            url: params.saveUrl,
            data: {
                action: params.saveAction,
                integration: integration,
                form_id: formId,
                form_title: formTitle,
                mapping: JSON.stringify(collectMapping())
            }
        })
            .done(function (response) {
                if (response && response.success && response.msg) {
                    renderList(response.msg.list);
                    showStatus("Mapping saved.", false);
                } else {
                    showStatus(params.saveError, true);
                }
            })
            .fail(function () {
                showStatus(params.saveError, true);
            });
    }

    function deleteMapping(integration, formId) {
        $.ajax({
            type: "post",
            dataType: "json",
            url: params.deleteUrl,
            data: {
                action: params.deleteAction,
                integration: integration,
                form_id: formId
            }
        })
            .done(function (response) {
                if (response && response.success && response.msg) {
                    renderList(response.msg.list);
                    showStatus("Mapping deleted.", false);
                } else {
                    showStatus(params.deleteError, true);
                }
            })
            .fail(function () {
                showStatus(params.deleteError, true);
            });
    }

    $integration.on("change", function () {
        var integration = $(this).val();
        resetFields();
        $form.empty()
            .append($("<option>").val("").text("Select a form"))
            .prop("disabled", true);
        if (integration) {
            loadForms(integration, null, null);
        }
    });

    $form.on("change", function () {
        var integration = $integration.val();
        var formId = $(this).val();
        resetFields();
        if (integration && formId) {
            loadFields(integration, formId);
        }
    });

    $saveButton.on("click", function (e) {
        e.preventDefault();
        saveMapping();
    });

    $listBody.on("click", ".fb-mapping-edit", function (e) {
        e.preventDefault();
        var integration = $(this).data("integration");
        var formId = String($(this).data("form_id"));
        $integration.val(integration);
        resetFields();
        loadForms(integration, formId, function () {
            loadFields(integration, formId);
        });
        $("html, body").animate(
            { scrollTop: $("#fb-mapping-editor").offset().top - 40 },
            300
        );
    });

    $listBody.on("click", ".fb-mapping-delete", function (e) {
        e.preventDefault();
        if (!window.confirm("Delete this form's field mapping?")) {
            return;
        }
        deleteMapping(
            $(this).data("integration"),
            String($(this).data("form_id"))
        );
    });

    // Initial render of the overview table.
    renderList(params.list);
})(window.jQuery);
