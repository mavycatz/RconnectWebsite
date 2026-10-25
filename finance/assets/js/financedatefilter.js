/* Shared Finance date-period control.
   Boundary math lives in Finance\Services\DateFilterService (PHP); this script
   renders the control from the embedded config and reports the active range. */
(function () {
    "use strict";

    var PANEL_SELECTOR = "[data-fin-date-filter]";
    var CONFIG_SELECTOR = "[data-fin-config]";
    var DEFAULT_LABEL = "Custom Range";
    var DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

    function readConfig(panel) {
        var node = panel.querySelector(CONFIG_SELECTOR);
        if (!node) {
            return null;
        }
        try {
            return JSON.parse(node.textContent || "{}");
        } catch (e) {
            return null;
        }
    }

    function toDate(iso) {
        if (!DATE_RE.test(String(iso || ""))) {
            return null;
        }
        var parts = String(iso).split("-");
        var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return isNaN(date.getTime()) ? null : date;
    }

    function formatLong(iso) {
        var date = toDate(iso);
        return date
            ? date.toLocaleDateString("en-US", { month: "long", day: "numeric", year: "numeric" })
            : "";
    }

    function formatMonthDay(iso) {
        var date = toDate(iso);
        return date
            ? date.toLocaleDateString("en-US", { month: "long", day: "numeric" })
            : "";
    }

    function formatRange(label, start, end) {
        if (!toDate(start) || !toDate(end)) {
            return label;
        }
        var startDate = toDate(start);
        var endDate = toDate(end);

        if (start === end) {
            return label + " \u00b7 " + formatLong(start);
        }
        if (startDate.getFullYear() !== endDate.getFullYear()) {
            return label + " \u00b7 " + formatLong(start) + "\u2013" + formatLong(end);
        }
        if (startDate.getMonth() === endDate.getMonth()) {
            return label + " \u00b7 " + formatMonthDay(start) + "\u2013"
                + endDate.getDate() + ", " + endDate.getFullYear();
        }
        return label + " \u00b7 " + formatMonthDay(start) + "\u2013"
            + formatMonthDay(end) + ", " + endDate.getFullYear();
    }

    function normalizeRange(from, to) {
        if (!DATE_RE.test(String(from || "")) || !DATE_RE.test(String(to || ""))) {
            return null;
        }
        return from <= to ? { from: from, to: to } : { from: to, to: from };
    }

    function updateSummaries(text) {
        var nodes = document.querySelectorAll("[data-fin-summary]");
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].textContent = text;
        }
    }

    function setSummary(text) {
        if (typeof text === "string" && text) {
            updateSummaries(text);
        }
    }

    function init(panel) {
        if (!panel || panel.getAttribute("data-fin-ready") === "1") {
            return;
        }

        var config = readConfig(panel);
        if (!config) {
            return;
        }
        panel.setAttribute("data-fin-ready", "1");

        var buttons = panel.querySelectorAll("[data-period]");
        var customBox = panel.querySelector("[data-fin-custom]");
        var fromInput = panel.querySelector("[data-fin-from]");
        var toInput = panel.querySelector("[data-fin-to]");
        var applyButton = panel.querySelector("[data-fin-apply]");
        var submitForm = panel.getAttribute("data-fin-submit-form") === "1";
        var presets = config.presets || {};
        var state = {
            period: config.selected || "monthly",
            from: config.from || "",
            to: config.to || ""
        };

        function setActive(period) {
            state.period = period;
            for (var i = 0; i < buttons.length; i++) {
                var isActive = buttons[i].getAttribute("data-period") === period;
                buttons[i].classList.toggle("active", isActive);
                buttons[i].setAttribute("aria-pressed", isActive ? "true" : "false");
            }
            if (customBox) {
                customBox.hidden = period !== "custom";
            }
            if (period === "custom") {
                if (fromInput && !fromInput.value && state.from) {
                    fromInput.value = state.from;
                }
                if (toInput && !toInput.value && state.to) {
                    toInput.value = state.to;
                }
            }
        }

        function currentDetail() {
            if (state.period === "custom") {
                var range = normalizeRange(
                    fromInput ? fromInput.value : state.from,
                    toInput ? toInput.value : state.to
                );
                if (!range) {
                    return null;
                }
                state.from = range.from;
                state.to = range.to;
                return {
                    period: "custom",
                    from: range.from,
                    to: range.to,
                    start: range.from,
                    end: range.to,
                    label: DEFAULT_LABEL,
                    summary: formatRange(DEFAULT_LABEL, range.from, range.to)
                };
            }

            var preset = presets[state.period];
            if (!preset || !preset.start || !preset.end) {
                return null;
            }
            return {
                period: state.period,
                from: "",
                to: "",
                start: preset.start,
                end: preset.end,
                label: preset.label,
                summary: preset.summary
            };
        }

        function syncHidden(detail) {
            var periodInput = panel.querySelector("[data-fin-input-period]");
            var fromField = panel.querySelector("[data-fin-input-from]");
            var toField = panel.querySelector("[data-fin-input-to]");
            if (periodInput) {
                periodInput.value = detail.period;
            }
            if (fromField) {
                fromField.value = detail.period === "custom" ? detail.start : "";
            }
            if (toField) {
                toField.value = detail.period === "custom" ? detail.end : "";
            }
        }

        function emit(detail, submit) {
            if (!detail) {
                return;
            }
            state.from = detail.period === "custom" ? detail.start : state.from;
            state.to = detail.period === "custom" ? detail.end : state.to;
            updateSummaries(detail.summary);
            if (submitForm) {
                syncHidden(detail);
                var form = panel.closest("form");
                if (submit && form) {
                    form.submit();
                }
            }
            panel.dispatchEvent(new CustomEvent("financedatefilter:change", {
                bubbles: true,
                detail: detail
            }));
        }

        function activate(period, submit) {
            setActive(period);
            var detail = currentDetail();
            if (detail) {
                emit(detail, submit);
            }
        }

        for (var i = 0; i < buttons.length; i++) {
            (function (button) {
                button.addEventListener("click", function () {
                    activate(button.getAttribute("data-period"), true);
                });
            })(buttons[i]);
        }

        function applyCustom() {
            var detail = currentDetail();
            if (detail) {
                emit(detail, true);
                return;
            }
            // Missing or invalid custom bounds fall back to Monthly, matching
            // the server-side resolution rule.
            activate("monthly", true);
        }

        if (applyButton) {
            applyButton.addEventListener("click", applyCustom);
        }

        if (fromInput && toInput) {
            var onInputChange = function () {
                if (state.period !== "custom") {
                    return;
                }
                if (normalizeRange(fromInput.value, toInput.value)) {
                    applyCustom();
                }
            };
            fromInput.addEventListener("change", onInputChange);
            toInput.addEventListener("change", onInputChange);
        }

        setActive(state.period);
        var initial = currentDetail();
        if (initial) {
            updateSummaries(initial.summary);
            syncHidden(initial);
        }
    }

    function initAll(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var panels = scope.querySelectorAll(PANEL_SELECTOR);
        for (var i = 0; i < panels.length; i++) {
            init(panels[i]);
        }
    }

    window.FinanceDateFilter = {
        init: init,
        initAll: initAll,
        formatRange: formatRange,
        normalizeRange: normalizeRange,
        setSummary: setSummary
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () {
            initAll(document);
        });
    } else {
        initAll(document);
    }
})();
