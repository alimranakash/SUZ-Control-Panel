/* global suzPrefill, acf */
(function ($) {
    'use strict';

    /**
     * Parse "HH:MM" → total minutes.
     */
    function timeToMinutes(timeStr) {
        if (!timeStr) return null;
        var parts = timeStr.split(':');
        if (parts.length < 2) return null;
        var h = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10);
        if (isNaN(h) || isNaN(m)) return null;
        return h * 60 + m;
    }

    /**
     * Total minutes → "HH:MM".
     */
    function minutesToTime(mins) {
        if (mins === null || isNaN(mins)) return '';
        mins = ((mins % 1440) + 1440) % 1440; // wrap 24 h
        var h = Math.floor(mins / 60);
        var m = mins % 60;
        return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
    }

    /**
     * Duration select value → minutes.
     * Handles "15min", "20min", "30min", "45min" and plain integers.
     */
    function durationToMinutes(val) {
        if (!val) return null;
        var n = parseInt(val, 10);
        return isNaN(n) ? null : n;
    }

    /**
     * Set an ACF timepicker value both in the hidden input and the visible text input.
     * ACF stores HH:MM in the hidden input; the visible input shows the same value.
     */
    function setTimeField(fieldKey, timeStr) {
        var $field = acf.getField(fieldKey);
        if (!$field || !$field.length) return;
        var $input = $field.find('input[type="hidden"]');
        var $text  = $field.find('input[type="text"]');
        if ($input.length) {
            $input.val(timeStr).trigger('change');
        }
        if ($text.length) {
            $text.val(timeStr).trigger('change');
        }
    }

    /**
     * Get current value of an ACF timepicker (hidden input).
     */
    function getTimeFieldValue(fieldKey) {
        var $field = acf.getField(fieldKey);
        if (!$field || !$field.length) return '';
        var $input = $field.find('input[type="hidden"]');
        return $input.length ? $input.val() : '';
    }

    /**
     * Recalculate and set suz_time_to based on current suz_time_from + suz_lecture_duration.
     */
    function recalcTimeTo() {
        var fromStr  = getTimeFieldValue(suzPrefill.fieldKeys.time_from);
        var $durField = acf.getField(suzPrefill.fieldKeys.duration);
        var durVal    = $durField && $durField.length ? $durField.find('select').val() : null;

        var fromMins = timeToMinutes(fromStr);
        var durMins  = durationToMinutes(durVal);

        if (fromMins !== null && durMins !== null) {
            setTimeField(suzPrefill.fieldKeys.time_to, minutesToTime(fromMins + durMins));
        }
    }

    /**
     * Fetch last lecture's suz_time_to for a given conference tag slug via AJAX.
     * On success, set suz_time_from and recalc suz_time_to.
     */
    function fetchLastLectureTime(tagSlug) {
        if (!tagSlug) return;
        $.ajax({
            url:  suzPrefill.ajaxUrl,
            type: 'POST',
            data: {
                action:   'suz_get_last_lecture',
                nonce:    suzPrefill.nonce,
                tag_slug: tagSlug,
            },
            success: function (res) {
                if (res.success && res.data && res.data.time_to) {
                    setTimeField(suzPrefill.fieldKeys.time_from, res.data.time_to);
                    recalcTimeTo();
                }
            },
        });
    }

    /**
     * Pre-fill suz_event_tag taxonomy checkbox / radio.
     * ACF renders the taxonomy field as checkboxes (or radio buttons).
     * We tick the term whose slug matches suzPrefill.lastTag.
     */
    function prefillConferenceTag() {
        if (!suzPrefill.lastTag) return;
        var $field = acf.getField(suzPrefill.fieldKeys.event_tag);
        if (!$field || !$field.length) return;

        $field.find('input[type="checkbox"], input[type="radio"]').each(function () {
            if ($(this).val() === suzPrefill.lastTag) {
                $(this).prop('checked', true).trigger('change');
                return false; // stop each
            }
        });
    }

    /**
     * Pre-fill suz_lecture_day taxonomy checkbox / radio with Day 1 term.
     */
    function prefillLectureDay() {
        if (!suzPrefill.defaultDay) return;
        var $field = acf.getField(suzPrefill.fieldKeys.lecture_day);
        if (!$field || !$field.length) return;

        $field.find('input[type="checkbox"], input[type="radio"]').each(function () {
            if ($(this).val() === suzPrefill.defaultDay) {
                $(this).prop('checked', true).trigger('change');
                return false;
            }
        });
    }

    /**
     * Get currently selected suz_event_tag slug from the ACF field.
     */
    function getSelectedTag() {
        var $field = acf.getField(suzPrefill.fieldKeys.event_tag);
        if (!$field || !$field.length) return '';
        var $checked = $field.find('input[type="checkbox"]:checked, input[type="radio"]:checked').first();
        return $checked.length ? $checked.val() : '';
    }

    /**
     * Wire up onChange listeners for reactive recalc.
     */
    function bindListeners() {
        // suz_time_from changes → recalc suz_time_to
        var $timeFromField = acf.getField(suzPrefill.fieldKeys.time_from);
        if ($timeFromField && $timeFromField.length) {
            $timeFromField.find('input').on('change dp.change', function () {
                recalcTimeTo();
            });
        }

        // suz_lecture_duration changes → recalc suz_time_to
        var $durField = acf.getField(suzPrefill.fieldKeys.duration);
        if ($durField && $durField.length) {
            $durField.find('select').on('change', function () {
                recalcTimeTo();
            });
        }

        // suz_event_tag changes → fetch new last-lecture time
        var $tagField = acf.getField(suzPrefill.fieldKeys.event_tag);
        if ($tagField && $tagField.length) {
            $tagField.find('input[type="checkbox"], input[type="radio"]').on('change', function () {
                var slug = getSelectedTag();
                if (slug) {
                    fetchLastLectureTime(slug);
                }
            });
        }
    }

    // =========================================================================
    // Boot – wait for ACF to finish rendering all fields
    // =========================================================================
    acf.addAction('ready', function () {
        // 1. Pre-fill conference tag
        prefillConferenceTag();

        // 2. Pre-fill lecture day (Day 1)
        prefillLectureDay();

        // 3. Fetch last lecture time for the pre-filled tag (or default)
        var tagSlug = getSelectedTag() || suzPrefill.lastTag;
        if (tagSlug) {
            fetchLastLectureTime(tagSlug);
        }

        // 4. Bind reactive listeners
        bindListeners();
    });

}(jQuery));
