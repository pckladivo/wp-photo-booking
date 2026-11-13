jQuery(function ($) {
    'use strict';

    var cfg       = window.wppb_builder || {};
    var formType  = $('#wppb-form-type').val() || 'contact';
    var TYPES     = (cfg.types && cfg.types[formType]) || {};
    var ROLES     = cfg.roles || {};
    var ROLE_OK   = cfg.role_types || [];

    var HAS_OPTIONS    = ['select', 'radio', 'checkbox'];
    var NO_PLACEHOLDER = ['radio', 'checkbox', 'consent', 'heading', 'time_slot'];
    var NO_REQUIRED    = ['heading', 'booking_date'];
    var NO_WIDTH       = ['heading'];
    var SINGLETON      = ['booking_date', 'time_slot'];
    var LABEL_NAME     = { consent: 'Consent text', heading: 'Heading text' };

    var $container = $('#wppb-fields-container');
    var counter    = 0;

    function newId() {
        counter++;
        return 'field_' + Date.now().toString(36) + counter;
    }

    if ($.fn.wpColorPicker) {
        $('.wppb-color-field').wpColorPicker();
    }

    // -----------------------------------------------
    // Init: render existing fields
    // -----------------------------------------------
    try {
        var existing = JSON.parse($('#wppb-fields-json').val() || '[]');
        $.each(existing, function (i, f) { addFieldRow(f); });
    } catch (e) {}
    refresh();

    if ($.fn.sortable) {
        $container.sortable({
            handle:      '.wppb-field-handle',
            placeholder: 'wppb-field-row-placeholder',
            axis:        'y',
            update:      serialize
        });
    }

    // -----------------------------------------------
    // Events
    // -----------------------------------------------
    $('#wppb-add-field').on('click', function () {
        addFieldRow({ id: newId(), type: 'text', label: '', placeholder: '', required: false, options: '', role: '', width: 'full' });
        refresh();
        $container.find('.wppb-field-row').last().find('.wppb-field-label').trigger('focus');
    });

    $container.on('click', '.wppb-remove-field', function () {
        $(this).closest('.wppb-field-row').remove();
        refresh();
    });

    $container.on('click', '.wppb-field-up', function () {
        var $row  = $(this).closest('.wppb-field-row');
        var $prev = $row.prev('.wppb-field-row');
        if ($prev.length) { $row.insertBefore($prev); serialize(); }
    });
    $container.on('click', '.wppb-field-down', function () {
        var $row  = $(this).closest('.wppb-field-row');
        var $next = $row.next('.wppb-field-row');
        if ($next.length) { $row.insertAfter($next); serialize(); }
    });

    $container.on('input change', 'input, select, textarea', refresh);

    // -----------------------------------------------
    // Build a field row
    // -----------------------------------------------
    function optionList(map, selected) {
        var html = '';
        $.each(map, function (value, label) {
            html += '<option value="' + ea(value) + '"' + (selected === value ? ' selected' : '') + '>' + eh(label) + '</option>';
        });
        return html;
    }

    function addFieldRow(f) {
        var type = TYPES[f.type] ? f.type : 'text';
        var role = (f.role && ROLES[f.role]) ? f.role : '';

        var $row = $(
            '<div class="wppb-field-row">' +
                '<div class="wppb-field-row-header">' +
                    '<span class="wppb-field-handle dashicons dashicons-move" title="Drag to reorder"></span>' +
                    '<select class="wppb-field-type">' + optionList(TYPES, type) + '</select>' +
                    '<span class="wppb-field-row-title"></span>' +
                    '<div class="wppb-field-row-order">' +
                        '<button type="button" class="button wppb-field-up"   title="Move up">&#8593;</button>' +
                        '<button type="button" class="button wppb-field-down" title="Move down">&#8595;</button>' +
                    '</div>' +
                    '<button type="button" class="button wppb-remove-field" title="Remove field">&times; Remove</button>' +
                '</div>' +
                '<div class="wppb-field-row-body">' +
                    '<div class="wppb-field-meta">' +
                        '<div class="wppb-field-meta-group">' +
                            '<label class="wppb-label-name">Label</label>' +
                            '<input type="text" class="wppb-field-label" value="' + ea(f.label || '') + '">' +
                        '</div>' +
                        '<div class="wppb-field-meta-group wppb-placeholder-group">' +
                            '<label>Placeholder</label>' +
                            '<input type="text" class="wppb-field-placeholder" value="' + ea(f.placeholder || '') + '" placeholder="Optional">' +
                        '</div>' +
                        '<div class="wppb-field-meta-group wppb-role-group">' +
                            '<label>Saves as</label>' +
                            '<select class="wppb-field-role">' +
                                '<option value="">Extra field</option>' + optionList(ROLES, role) +
                            '</select>' +
                        '</div>' +
                        '<div class="wppb-field-meta-group wppb-width-group" style="min-width:110px;">' +
                            '<label>Width</label>' +
                            '<select class="wppb-field-width">' +
                                optionList({ full: 'Full', half: 'Half' }, f.width === 'half' ? 'half' : 'full') +
                            '</select>' +
                        '</div>' +
                        '<div class="wppb-field-meta-group wppb-required-group" style="min-width:90px;">' +
                            '<label>&nbsp;</label>' +
                            '<label class="wppb-required-label"><input type="checkbox" class="wppb-field-required"' + (f.required ? ' checked' : '') + '> Required</label>' +
                        '</div>' +
                    '</div>' +
                    '<div class="wppb-field-options-group">' +
                        '<label>Options <span class="description">(one per line)</span></label>' +
                        '<textarea class="wppb-field-options" rows="4" placeholder="Option 1&#10;Option 2&#10;Option 3">' + eh(f.options || '') + '</textarea>' +
                    '</div>' +
                    '<p class="wppb-field-note"></p>' +
                    '<input type="hidden" class="wppb-field-id" value="' + ea(f.id || newId()) + '">' +
                '</div>' +
            '</div>'
        );

        $container.append($row);
    }

    // -----------------------------------------------
    // Keep the rows consistent, then store them
    // -----------------------------------------------
    function has(list, value) { return list.indexOf(value) !== -1; }

    function refresh() {
        var $rows = $container.find('.wppb-field-row');

        // Which singleton types and roles are taken, and by which row
        var typeOwner = {};
        var roleOwner = {};
        $rows.each(function (i) {
            var type = $(this).find('.wppb-field-type').val();
            var role = $(this).find('.wppb-field-role').val();
            if (has(SINGLETON, type) && typeOwner[type] === undefined) typeOwner[type] = i;
            if (role && has(ROLE_OK, type) && roleOwner[role] === undefined) roleOwner[role] = i;
        });

        $rows.each(function (i) {
            var $row  = $(this);
            var $type = $row.find('.wppb-field-type');
            var $role = $row.find('.wppb-field-role');
            var type  = $type.val();

            // A second date / time slot field makes no sense: fall back to text
            if (has(SINGLETON, type) && typeOwner[type] !== i) {
                type = 'text';
                $type.val(type);
            }
            $type.find('option').each(function () {
                var t = this.value;
                this.disabled = has(SINGLETON, t) && typeOwner[t] !== undefined && typeOwner[t] !== i;
            });

            // Each role can be used once
            if (!has(ROLE_OK, type) || ($role.val() && roleOwner[$role.val()] !== i)) {
                $role.val('');
            }
            $role.find('option').each(function () {
                var r = this.value;
                this.disabled = !!r && roleOwner[r] !== undefined && roleOwner[r] !== i;
            });

            $row.find('.wppb-field-options-group').toggle(has(HAS_OPTIONS, type));
            $row.find('.wppb-placeholder-group').toggle(!has(NO_PLACEHOLDER, type));
            $row.find('.wppb-required-group').toggle(!has(NO_REQUIRED, type));
            $row.find('.wppb-width-group').toggle(!has(NO_WIDTH, type));
            $row.find('.wppb-role-group').toggle(has(ROLE_OK, type));
            $row.find('.wppb-label-name').text(LABEL_NAME[type] || 'Label');
            $row.find('.wppb-field-row-title').text($row.find('.wppb-field-label').val());

            var note = '';
            if (type === 'booking_date') note = 'Shows the availability calendar. Always required.';
            if (type === 'time_slot')    note = 'With time slots configured in Settings: the free slots of the chosen date (always required, one booking per slot). Without: a plain time input for the preferred time.';
            if (type === 'consent')      note = 'A single checkbox, e.g. for a privacy policy. Links are allowed: <a href="/privacy">privacy policy</a>.';
            $row.find('.wppb-field-note').text(note).toggle(!!note);
        });

        serialize();
    }

    function serialize() {
        var fields = [];
        $container.find('.wppb-field-row').each(function () {
            var $r   = $(this);
            var type = $r.find('.wppb-field-type').val();
            fields.push({
                id:          $r.find('.wppb-field-id').val(),
                type:        type,
                label:       $r.find('.wppb-field-label').val(),
                placeholder: has(NO_PLACEHOLDER, type) ? '' : $r.find('.wppb-field-placeholder').val(),
                required:    type === 'booking_date' || (!has(NO_REQUIRED, type) && $r.find('.wppb-field-required').is(':checked')),
                options:     has(HAS_OPTIONS, type) ? $r.find('.wppb-field-options').val() : '',
                role:        has(SINGLETON, type) ? type : (has(ROLE_OK, type) ? $r.find('.wppb-field-role').val() : ''),
                width:       has(NO_WIDTH, type) ? 'full' : $r.find('.wppb-field-width').val()
            });
        });
        $('#wppb-fields-json').val(JSON.stringify(fields));
    }

    // Escape helpers
    function ea(s) { return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    function eh(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
});
