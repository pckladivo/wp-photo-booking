/* WP Photo Booking — public forms (booking, contact, questionnaire) */
jQuery(function ($) {
    'use strict';

    var cfg     = window.wppb_form || {};
    var i18n    = cfg.i18n || {};
    var booking = window.wppb_booking || null;

    // -----------------------------------------------
    // Consistent look
    // Themes usually style text inputs but leave dropdowns and time inputs with
    // the browser default. Copy the look of a text input of the same form.
    // -----------------------------------------------
    var COPIED_STYLES = [
        'boxSizing', 'height', 'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing',
        'color', 'backgroundColor', 'borderRadius', 'boxShadow',
        'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft',
        'borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth',
        'borderTopStyle', 'borderRightStyle', 'borderBottomStyle', 'borderLeftStyle',
        'borderTopColor', 'borderRightColor', 'borderBottomColor', 'borderLeftColor'
    ];

    function matchInputStyles() {
        $('.wppb-form').each(function () {
            var $form = $(this);
            // Only real fields: the hidden honeypot input is not styled like one.
            var ref   = $form.find('.wppb-field input[type="text"], .wppb-field input[type="email"]').filter(':visible').get(0);
            if (!ref || !ref.offsetHeight || !window.getComputedStyle) return;

            var look = window.getComputedStyle(ref);
            // The submit button takes the same corner rounding as the fields.
            $form.closest('.wppb-form-wrap').get(0).style.setProperty('--wppb-input-radius', look.borderTopLeftRadius);
            $form.find('select, input[type="time"]').each(function () {
                for (var i = 0; i < COPIED_STYLES.length; i++) {
                    this.style[COPIED_STYLES[i]] = look[COPIED_STYLES[i]];
                }
            });
        });
    }

    matchInputStyles();
    // Again once all stylesheets and fonts are in.
    $(window).on('load', matchInputStyles);

    // -----------------------------------------------
    // Calendar
    // -----------------------------------------------
    if (booking && $.fn.datepicker) {
        var disabledDates = booking.disabled_dates || [];
        var workingDays   = (booking.working_days || []).map(Number);

        $('.wppb-date').datepicker({
            dateFormat:        'yy-mm-dd',
            minDate:           booking.min_days_ahead,
            showOtherMonths:   true,
            selectOtherMonths: false,
            changeMonth:       true,
            beforeShowDay: function (date) {
                var str = date.getFullYear() + '-' +
                    String(date.getMonth() + 1).padStart(2, '0') + '-' +
                    String(date.getDate()).padStart(2, '0');

                if (disabledDates.indexOf(str) !== -1) return [false, 'wppb-taken', ''];
                if (workingDays.indexOf(date.getDay()) === -1) return [false, 'wppb-not-working', ''];
                return [true, 'wppb-available', ''];
            },
            onSelect: function (dateStr) {
                var $slotField = $(this).closest('form').find('.wppb-time-slot-field');
                if ($slotField.length) loadSlots($slotField, dateStr);
            }
        });
    }

    // -----------------------------------------------
    // Time slots
    // -----------------------------------------------
    function hint(text, isError) {
        return $('<p class="wppb-slot-hint"></p>').toggleClass('wppb-slot-none', !!isError).text(text);
    }

    function loadSlots($field, date) {
        var $container = $field.find('.wppb-time-slot-container');
        $field.find('.wppb-time-slot').val('');
        $container.empty().append(hint(i18n.loading_slots));

        $.post(cfg.ajax_url, {
            action: 'wppb_get_available_slots',
            nonce:  cfg.nonce,
            date:   date
        }, function (res) {
            $container.empty();
            if (!res.success || !res.data.slots.length) {
                $container.append(hint(i18n.no_slots_available, true));
                return;
            }
            var $grid = $('<div class="wppb-slot-grid"></div>');
            $.each(res.data.slots, function (i, slot) {
                $('<button type="button" class="wppb-slot-btn"></button>')
                    .attr('data-slot', slot.value)
                    .text(slot.label)
                    .appendTo($grid);
            });
            $container.append($grid);
        }).fail(function () {
            $container.empty().append(hint(i18n.network_error, true));
        });
    }

    $(document).on('click', '.wppb-slot-btn', function () {
        var $field = $(this).closest('.wppb-time-slot-field');
        $field.find('.wppb-slot-btn').removeClass('wppb-slot-active');
        $(this).addClass('wppb-slot-active');
        $field.find('.wppb-time-slot').val($(this).data('slot'));
    });

    // -----------------------------------------------
    // Submission
    // -----------------------------------------------
    function recaptchaIndex($form) {
        return $('.g-recaptcha').index($form.find('.g-recaptcha'));
    }

    function resetRecaptcha($form) {
        var index = recaptchaIndex($form);
        if (index !== -1 && typeof grecaptcha !== 'undefined') {
            try { grecaptcha.reset(index); } catch (e) {}
        }
    }

    // Checks the browser cannot do: read-only date, hidden slot input, checkbox groups.
    function customErrors($form) {
        var errors = [];
        $form.find('.wppb-field[data-required="1"]').each(function () {
            var $f    = $(this);
            var type  = $f.data('type');
            var empty = false;

            if (type === 'booking_date')   empty = !$f.find('.wppb-date').val();
            else if (type === 'time_slot') empty = !$f.find('.wppb-time-slot').val();
            else if (type === 'checkbox')  empty = !$f.find('input:checked').length;

            if (empty) errors.push((i18n.required || '%s').replace('%s', $f.data('label')));
        });

        if ($form.find('.g-recaptcha').length && !$form.find('[name="g-recaptcha-response"]').val()) {
            errors.push(i18n.recaptcha_required);
        }
        return errors;
    }

    function scrollTo($el) {
        if ($el.length) $('html, body').animate({ scrollTop: $el.offset().top - 40 }, 300);
    }

    $(document).on('submit', '.wppb-form', function (e) {
        e.preventDefault();

        var $form  = $(this);
        var $wrap  = $form.closest('.wppb-form-wrap');
        var $error = $wrap.find('.wppb-notice-error');
        var $btn   = $form.find('.wppb-submit-btn');

        $wrap.find('.wppb-notice').hide().empty();

        if ($form[0].checkValidity && !$form[0].checkValidity()) {
            $form[0].reportValidity();
            return;
        }

        var errors = customErrors($form);
        if (errors.length) {
            $.each(errors, function (i, msg) { $error.append($('<div></div>').text(msg)); });
            $error.show();
            scrollTo($error);
            return;
        }

        var label = $btn.text();
        $btn.prop('disabled', true).text(i18n.sending);

        var data = $form.serializeArray();
        data.push({ name: 'action', value: $form.data('action') });
        data.push({ name: 'nonce',  value: cfg.nonce });

        function fail(message) {
            $error.text(message || i18n.generic_error).show();
            resetRecaptcha($form);
            $btn.prop('disabled', false).text(label);
            scrollTo($error);
        }

        $.post(cfg.ajax_url, data, function (res) {
            if (res && res.success) {
                var $success = $wrap.find('.wppb-notice-success');
                $form.slideUp(200);
                $success.text(res.data.message).show();
                scrollTo($success);
            } else {
                fail(res && res.data && res.data.message);
            }
        }).fail(function () {
            fail(i18n.network_error);
        });
    });
});
