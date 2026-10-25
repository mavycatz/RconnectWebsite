/* Compact reporting-period labels for Finance analytics charts.
   Formats server-resolved wall-clock Y-m-d values only; it performs no
   timezone conversion and computes no date boundaries. */
(function (global, factory) {
    "use strict";

    var api = factory();
    if (typeof module !== "undefined" && module && module.exports) {
        module.exports = api;
    }
    if (global) {
        global.FinancePeriodLabel = api;
    }
})(typeof window !== "undefined" ? window : null, function () {
    "use strict";

    var DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

    function toDate(iso) {
        if (!DATE_RE.test(String(iso || ""))) {
            return null;
        }
        var parts = String(iso).split("-");
        var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return isNaN(date.getTime()) ? null : date;
    }

    function monthLong(iso) {
        var date = toDate(iso);
        return date ? date.toLocaleDateString("en-US", { month: "long" }) : "";
    }

    function monthShort(iso) {
        var date = toDate(iso);
        return date ? date.toLocaleDateString("en-US", { month: "short" }) : "";
    }

    function single(start, date) {
        return monthShort(start) + " " + date.getDate() + ", " + date.getFullYear();
    }

    function range(start, end) {
        var startDate = toDate(start);
        var endDate = toDate(end);

        if (!startDate) {
            return endDate ? single(end, endDate) : "";
        }
        if (!endDate || start === end) {
            return single(start, startDate);
        }
        if (startDate.getFullYear() === endDate.getFullYear()) {
            if (startDate.getMonth() === endDate.getMonth()) {
                return (
                    monthShort(start) +
                    " " +
                    startDate.getDate() +
                    "\u2013" +
                    endDate.getDate() +
                    ", " +
                    endDate.getFullYear()
                );
            }
            return (
                monthShort(start) +
                " " +
                startDate.getDate() +
                " \u2013 " +
                monthShort(end) +
                " " +
                endDate.getDate() +
                ", " +
                endDate.getFullYear()
            );
        }
        return single(start, startDate) + " \u2013 " + single(end, endDate);
    }

    function period(periodValue, start, end) {
        var key = String(periodValue || "").toLowerCase();

        if (key === "today" || key === "daily") {
            return "Today";
        }

        var startDate = toDate(start);

        if (key === "monthly") {
            return startDate ? "Monthly \u00b7 " + monthLong(start) + " " + startDate.getFullYear() : "Monthly";
        }
        if (key === "quarterly") {
            return startDate
                ? "Quarterly \u00b7 Q" + (Math.floor(startDate.getMonth() / 3) + 1) + " " + startDate.getFullYear()
                : "Quarterly";
        }
        if (key === "semi_annually") {
            return startDate
                ? "Semi-Annually \u00b7 " + (startDate.getMonth() < 6 ? "H1" : "H2") + " " + startDate.getFullYear()
                : "Semi-Annually";
        }
        if (key === "annually") {
            return startDate ? "Annually \u00b7 " + startDate.getFullYear() : "Annually";
        }

        var rangeText = range(start, end);
        if (key === "custom") {
            return rangeText ? "Custom Range \u00b7 " + rangeText : "Custom Range";
        }

        return rangeText || "";
    }

    return {
        period: period,
        range: range,
    };
});
