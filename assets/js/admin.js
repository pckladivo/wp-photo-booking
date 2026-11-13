jQuery(function ($) {
    'use strict';

    // Datepicker for blocked date input in settings
    $('#wppb-blocked-date').datepicker({
        dateFormat:  'yy-mm-dd',
        minDate:     0,
        changeMonth: true,
    });
});
