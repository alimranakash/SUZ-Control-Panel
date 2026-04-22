/* global suzLecturePrefill */
(function ($) {
    'use strict';

    var config = window.suzLecturePrefill || {};
    var fieldKeys = config.fieldKeys || {};
    var fieldNames = config.fieldNames || {};
    var selectors = config.selectors || {};
    var taxonomies = {
        eventTag: 'suz_event_tag',
        lectureDay: 'suz_lecture_day'
    };
    var subscriptionsBound = false;
    var lastSeen = {
        tag: '',
        timeFrom: '',
        duration: ''
    };

    function toInt(value) {
        var n = parseInt(value, 10);
        return isNaN(n) ? 0 : n;
    }

    function normValue(value) {
        if (value === undefined || value === null) {
            return '';
        }
        return String(value).trim().toLowerCase();
    }

    function normalizeTime(value) {
        var match;
        var hours;
        var minutes;

        if (value === undefined || value === null) {
            return '';
        }

        match = String(value).trim().match(/(\d{1,2}):(\d{2})/);
        if (!match) {
            return '';
        }

        hours = parseInt(match[1], 10);
        minutes = parseInt(match[2], 10);

        if (isNaN(hours) || isNaN(minutes) || hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
            return '';
        }

        return (hours < 10 ? '0' : '') + hours + ':' + (minutes < 10 ? '0' : '') + minutes;
    }

    function timeToMinutes(value) {
        var normalized = normalizeTime(value);
        var parts;

        if (!normalized) {
            return null;
        }

        parts = normalized.split(':');
        return (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
    }

    function minutesToTime(minutes) {
        var safeMinutes;
        var h;
        var m;

        if (minutes === null || isNaN(minutes)) {
            return '';
        }

        safeMinutes = ((minutes % 1440) + 1440) % 1440;
        h = Math.floor(safeMinutes / 60);
        m = safeMinutes % 60;

        return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
    }

    function durationToMinutes(value) {
        var raw = String(value === undefined || value === null ? '' : value).trim();
        var parsed;
        var asTime;

        if (!raw) {
            return null;
        }

        if (raw.indexOf(':') > -1) {
            asTime = timeToMinutes(raw);
            return asTime === null ? null : asTime;
        }

        parsed = parseInt(raw, 10);
        return isNaN(parsed) ? null : parsed;
    }

    function canUseEditorStore() {
        return !!(window.wp && window.wp.data && typeof window.wp.data.select === 'function' && typeof window.wp.data.dispatch === 'function');
    }

    function getEditorSelector() {
        if (!canUseEditorStore()) {
            return null;
        }
        return window.wp.data.select('core/editor');
    }

    function getEditorDispatcher() {
        if (!canUseEditorStore()) {
            return null;
        }
        return window.wp.data.dispatch('core/editor');
    }

    function getEditedPostAttribute(attr) {
        var selector = getEditorSelector();
        if (!selector || typeof selector.getEditedPostAttribute !== 'function') {
            return null;
        }
        return selector.getEditedPostAttribute(attr);
    }

    function getEditedMeta() {
        var meta = getEditedPostAttribute('meta');
        return meta && typeof meta === 'object' ? meta : {};
    }

    function setEditedMetaValue(metaKey, value) {
        var dispatcher;
        var currentMeta;
        var nextMeta;

        if (!metaKey) {
            return;
        }

        dispatcher = getEditorDispatcher();
        if (!dispatcher || typeof dispatcher.editPost !== 'function') {
            return;
        }

        currentMeta = getEditedMeta();
        nextMeta = $.extend({}, currentMeta);
        nextMeta[metaKey] = value;
        dispatcher.editPost({ meta: nextMeta });
    }

    function getEditedTaxonomyValue(taxonomy) {
        var value = getEditedPostAttribute(taxonomy);

        if ($.isArray(value) && value.length) {
            return String(value[0] || '').trim();
        }

        if (value !== null && value !== undefined && value !== '') {
            return String(value).trim();
        }

        return '';
    }

    function setEditedTaxonomyValue(taxonomy, termId) {
        var dispatcher;
        var intId = toInt(termId);
        var payload = {};

        if (!taxonomy || intId <= 0) {
            return false;
        }

        dispatcher = getEditorDispatcher();
        if (!dispatcher || typeof dispatcher.editPost !== 'function') {
            return false;
        }

        payload[taxonomy] = [intId];
        dispatcher.editPost(payload);
        return true;
    }

    function getAcfFieldElement(fieldKey, fieldName) {
        var field;
        var $fallback;

        if (fieldKey && window.acf && typeof window.acf.getField === 'function') {
            field = window.acf.getField(fieldKey);
            if (field) {
                if (field.$el && field.$el.length) {
                    return field.$el;
                }
                if (field.length) {
                    return field;
                }
            }
        }

        if (fieldKey) {
            $fallback = $('.acf-field[data-key="' + fieldKey + '"]');
            if ($fallback.length) {
                return $fallback.first();
            }
        }

        if (fieldName) {
            $fallback = $('.acf-field[data-name="' + fieldName + '"]');
            if ($fallback.length) {
                return $fallback.first();
            }
        }

        return $();
    }

    function setTimeFieldValue(fieldKey, fieldName, selector, value) {
        var timeValue = normalizeTime(value);
        var $acfField;
        var $hidden;
        var $text;
        var $nativeInputs;
        var metaKey = fieldName || '';

        if (!timeValue) {
            return;
        }

        if (metaKey) {
            setEditedMetaValue(metaKey, timeValue);
        }

        $acfField = getAcfFieldElement(fieldKey, fieldName);
        if ($acfField.length) {
            $hidden = $acfField.find('input[type="hidden"]').first();
            $text = $acfField.find('input[type="text"], input[type="time"]').first();

            if ($hidden.length) {
                $hidden.val(timeValue).trigger('change');
            }
            if ($text.length) {
                $text.val(timeValue).trigger('change');
            }
        }

        if (selector) {
            $nativeInputs = $(selector);
            if ($nativeInputs.length) {
                $nativeInputs.val(timeValue).trigger('change');
            }
        }
    }

    function getTimeFieldValue(fieldKey, fieldName, selector) {
        var metaValue;
        var $acfField;
        var $hidden;
        var $text;
        var nativeValue;

        if (fieldName) {
            metaValue = getEditedMeta()[fieldName];
            if (normalizeTime(metaValue)) {
                return normalizeTime(metaValue);
            }
        }

        $acfField = getAcfFieldElement(fieldKey, fieldName);
        if ($acfField.length) {
            $hidden = $acfField.find('input[type="hidden"]').first();
            if ($hidden.length && normalizeTime($hidden.val())) {
                return normalizeTime($hidden.val());
            }

            $text = $acfField.find('input[type="text"], input[type="time"]').first();
            if ($text.length && normalizeTime($text.val())) {
                return normalizeTime($text.val());
            }
        }

        if (selector) {
            nativeValue = $(selector).first().val();
            if (normalizeTime(nativeValue)) {
                return normalizeTime(nativeValue);
            }
        }

        return '';
    }

    function setDurationValue(fieldKey, fieldName, selector, value) {
        var durationValue = String(value === undefined || value === null ? '' : value).trim();
        var $acfField;
        var $radioInputs;
        var $native;
        var setDone = false;

        if (!durationValue) {
            return;
        }

        if (fieldName) {
            setEditedMetaValue(fieldName, durationValue);
        }

        $acfField = getAcfFieldElement(fieldKey, fieldName);
        if ($acfField.length) {
            $radioInputs = $acfField.find('input[type="radio"]');
            if ($radioInputs.length) {
                $radioInputs.each(function () {
                    var $radio = $(this);
                    if (normValue($radio.val()) === normValue(durationValue)) {
                        $acfField.find('label').removeClass('selected');
                        $radio.prop('checked', true).trigger('change');
                        $radio.closest('label').addClass('selected');
                        setDone = true;
                        return false;
                    }
                    return undefined;
                });
            }

            if (!setDone) {
                $acfField.find('select').each(function () {
                    var $select = $(this);
                    $select.val(durationValue).trigger('change');
                    if (normValue($select.val()) === normValue(durationValue)) {
                        setDone = true;
                    }
                });
            }
        }

        if (selector) {
            $native = $(selector);
            $native.filter('input[type="radio"]').each(function () {
                var $radio = $(this);
                if (normValue($radio.val()) === normValue(durationValue)) {
                    $radio.prop('checked', true).trigger('change');
                    setDone = true;
                    return false;
                }
                return undefined;
            });

            if (!setDone) {
                $native.filter('select, input[type="number"], input[type="text"], input[type="hidden"]').each(function () {
                    var $input = $(this);
                    $input.val(durationValue).trigger('change');
                    setDone = true;
                });
            }
        }
    }

    function setTaxonomyTerm(fieldKey, fieldName, selector, desiredValues, taxonomySlug, preferredId) {
        var values = $.isArray(desiredValues) ? desiredValues : [desiredValues];
        var normalizedTargets = [];
        var $acfField;
        var $inputs;
        var $native;
        var selected = false;
        var i;

        if (taxonomySlug && preferredId) {
            selected = setEditedTaxonomyValue(taxonomySlug, preferredId) || selected;
        }

        $.each(values, function (_, item) {
            var normalized = normValue(item);
            if (normalized && normalized !== '0') {
                normalizedTargets.push(normalized);
            }
        });

        if (!normalizedTargets.length) {
            return selected;
        }

        $acfField = getAcfFieldElement(fieldKey, fieldName);
        if ($acfField.length) {
            $inputs = $acfField.find('input[type="checkbox"], input[type="radio"]');
            $inputs.each(function () {
                if ($.inArray(normValue($(this).val()), normalizedTargets) > -1) {
                    $(this).prop('checked', true).trigger('change');
                    selected = true;
                    return false;
                }
                return undefined;
            });

            $acfField.find('select').each(function () {
                var $select = $(this);
                for (i = 0; i < normalizedTargets.length; i += 1) {
                    $select.val(normalizedTargets[i]).trigger('change');
                    if (normValue($select.val()) === normalizedTargets[i]) {
                        selected = true;
                        break;
                    }
                }
            });
        }

        if (selector) {
            $native = $(selector);
            $native.filter('input[type="checkbox"], input[type="radio"]').each(function () {
                if ($.inArray(normValue($(this).val()), normalizedTargets) > -1) {
                    $(this).prop('checked', true).trigger('change');
                    selected = true;
                    return false;
                }
                return undefined;
            });

            $native.filter('select').each(function () {
                var $select = $(this);
                for (i = 0; i < normalizedTargets.length; i += 1) {
                    $select.val(normalizedTargets[i]).trigger('change');
                    if (normValue($select.val()) === normalizedTargets[i]) {
                        selected = true;
                        break;
                    }
                }
            });
        }

        return selected;
    }

    function getSelectedTaxonomyTerm(fieldKey, fieldName, selector, taxonomySlug) {
        var editorValue;
        var $acfField;
        var $checked;
        var $select;
        var val;
        var $native;
        var nativeChecked;
        var nativeSelect;

        if (taxonomySlug) {
            editorValue = getEditedTaxonomyValue(taxonomySlug);
            if (editorValue) {
                return editorValue;
            }
        }

        $acfField = getAcfFieldElement(fieldKey, fieldName);
        if ($acfField.length) {
            $checked = $acfField.find('input[type="checkbox"]:checked, input[type="radio"]:checked').first();
            if ($checked.length) {
                return String($checked.val() || '').trim();
            }

            $select = $acfField.find('select').first();
            if ($select.length) {
                val = $select.val();
                if ($.isArray(val)) {
                    return val.length ? String(val[0] || '').trim() : '';
                }
                return String(val || '').trim();
            }
        }

        if (selector) {
            $native = $(selector);
            nativeChecked = $native.filter('input[type="checkbox"]:checked, input[type="radio"]:checked').first();
            if (nativeChecked.length) {
                return String(nativeChecked.val() || '').trim();
            }

            nativeSelect = $native.filter('select').first();
            if (nativeSelect.length) {
                val = nativeSelect.val();
                if ($.isArray(val)) {
                    return val.length ? String(val[0] || '').trim() : '';
                }
                return String(val || '').trim();
            }
        }

        return '';
    }

    function getDurationValue() {
        var $acfField = getAcfFieldElement(fieldKeys.duration, fieldNames.duration);
        var metaValue;
        var $checkedRadio;
        var $acfInput;
        var $nativeInput;

        if (fieldNames.duration) {
            metaValue = getEditedMeta()[fieldNames.duration];
            if (metaValue !== undefined && metaValue !== null && String(metaValue).trim() !== '') {
                return metaValue;
            }
        }

        if ($acfField.length) {
            $checkedRadio = $acfField.find('input[type="radio"]:checked').first();
            if ($checkedRadio.length) {
                return $checkedRadio.val();
            }

            $acfInput = $acfField.find('select, input[type="number"], input[type="text"], input[type="hidden"]').first();
            if ($acfInput.length) {
                return $acfInput.val();
            }
        }

        if (selectors.duration) {
            $nativeInput = $(selectors.duration + ':checked').first();
            if ($nativeInput.length) {
                return $nativeInput.val();
            }
            $nativeInput = $(selectors.duration).first();
            if ($nativeInput.length) {
                return $nativeInput.val();
            }
        }

        return '';
    }

    function getEventTagWatchers() {
        var $watchers = $();
        var $acfField = getAcfFieldElement(fieldKeys.eventTag, fieldNames.eventTag);

        if ($acfField.length) {
            $watchers = $watchers.add($acfField.find('input[type="checkbox"], input[type="radio"], select'));
        }
        if (selectors.eventTag) {
            $watchers = $watchers.add($(selectors.eventTag));
        }

        return $watchers;
    }

    function getTimeFromWatchers() {
        var $watchers = $();
        var $acfField = getAcfFieldElement(fieldKeys.timeFrom, fieldNames.timeFrom);

        if ($acfField.length) {
            $watchers = $watchers.add($acfField.find('input'));
        }
        if (selectors.timeFrom) {
            $watchers = $watchers.add($(selectors.timeFrom));
        }

        return $watchers;
    }

    function getDurationWatchers() {
        var $watchers = $();
        var $acfField = getAcfFieldElement(fieldKeys.duration, fieldNames.duration);

        if ($acfField.length) {
            $watchers = $watchers.add($acfField.find('select, input'));
        }
        if (selectors.duration) {
            $watchers = $watchers.add($(selectors.duration));
        }

        return $watchers;
    }

    function recalculateTimeTo() {
        var timeFrom = getTimeFieldValue(fieldKeys.timeFrom, fieldNames.timeFrom, selectors.timeFrom);
        var duration = getDurationValue();
        var timeFromMins = timeToMinutes(timeFrom);
        var durationMins = durationToMinutes(duration);

        if (timeFromMins === null || durationMins === null) {
            return;
        }

        setTimeFieldValue(fieldKeys.timeTo, fieldNames.timeTo, selectors.timeTo, minutesToTime(timeFromMins + durationMins));
    }

    function fetchLatestTimeTo(eventTagRaw) {
        if (!eventTagRaw || !config.ajaxUrl || !config.nonce) {
            return;
        }

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'suz_lecture_autoprefill_latest_time',
                nonce: config.nonce,
                event_tag: eventTagRaw
            }
        }).done(function (response) {
            if (response && response.success && response.data && response.data.time_to) {
                if (response.data.duration) {
                    setDurationValue(fieldKeys.duration, fieldNames.duration, selectors.duration, response.data.duration);
                }
                setTimeFieldValue(fieldKeys.timeFrom, fieldNames.timeFrom, selectors.timeFrom, response.data.time_to);
                recalculateTimeTo();
            }
        });
    }

    function bindDomListeners() {
        var namespace = '.suzLectureAutoPrefill';

        getTimeFromWatchers()
            .off(namespace)
            .on('change' + namespace + ' input' + namespace, function () {
                recalculateTimeTo();
            });

        getDurationWatchers()
            .off(namespace)
            .on('change' + namespace + ' input' + namespace, function () {
                recalculateTimeTo();
            });

        getEventTagWatchers()
            .off(namespace)
            .on('change' + namespace, function () {
                var selectedTag = getSelectedTaxonomyTerm(
                    fieldKeys.eventTag,
                    fieldNames.eventTag,
                    selectors.eventTag,
                    taxonomies.eventTag
                );
                if (selectedTag && selectedTag !== lastSeen.tag) {
                    lastSeen.tag = selectedTag;
                    fetchLatestTimeTo(selectedTag);
                }
            });
    }

    function bindEditorSubscriptions() {
        if (subscriptionsBound || !canUseEditorStore()) {
            return;
        }

        window.wp.data.subscribe(function () {
            var selectedTag = getSelectedTaxonomyTerm(
                fieldKeys.eventTag,
                fieldNames.eventTag,
                selectors.eventTag,
                taxonomies.eventTag
            );
            var currentTimeFrom = getTimeFieldValue(fieldKeys.timeFrom, fieldNames.timeFrom, selectors.timeFrom);
            var currentDuration = String(getDurationValue() || '');

            if (selectedTag && selectedTag !== lastSeen.tag) {
                lastSeen.tag = selectedTag;
                fetchLatestTimeTo(selectedTag);
            }

            if (currentTimeFrom !== lastSeen.timeFrom || currentDuration !== lastSeen.duration) {
                lastSeen.timeFrom = currentTimeFrom;
                lastSeen.duration = currentDuration;
                recalculateTimeTo();
            }
        });

        subscriptionsBound = true;
    }

    function runPrefill() {
        var lastEventTagId = toInt(config.lastEventTagId);
        var lastEventTagSlug = String(config.lastEventTagSlug || '').trim();
        var defaultLectureDayId = toInt(config.defaultLectureDayId);
        var defaultLectureDaySlug = String(config.defaultLectureDaySlug || '').trim();
        var selectedTag;

        if (lastEventTagId || lastEventTagSlug) {
            setTaxonomyTerm(
                fieldKeys.eventTag,
                fieldNames.eventTag,
                selectors.eventTag,
                [String(lastEventTagId || ''), lastEventTagSlug],
                taxonomies.eventTag,
                lastEventTagId
            );
        }

        if (defaultLectureDayId || defaultLectureDaySlug) {
            setTaxonomyTerm(
                fieldKeys.lectureDay,
                fieldNames.lectureDay,
                selectors.lectureDay,
                [String(defaultLectureDayId || ''), defaultLectureDaySlug],
                taxonomies.lectureDay,
                defaultLectureDayId
            );
        }

        selectedTag = getSelectedTaxonomyTerm(
            fieldKeys.eventTag,
            fieldNames.eventTag,
            selectors.eventTag,
            taxonomies.eventTag
        );
        if (selectedTag) {
            lastSeen.tag = selectedTag;
            fetchLatestTimeTo(selectedTag);
        }

        recalculateTimeTo();
        bindDomListeners();
        bindEditorSubscriptions();
    }

    $(runPrefill);

    if (window.acf && typeof window.acf.addAction === 'function') {
        window.acf.addAction('ready', runPrefill);
    }

    $(window).on('load', function () {
        setTimeout(runPrefill, 300);
    });
}(jQuery));
