const assert = require('node:assert/strict');
const { period, range } = require('../assets/js/financeperiodlabel.js');

let checks = 0;
function check(actual, expected, message) {
    assert.equal(actual, expected, message);
    checks++;
}

check(period('today', '2026-10-13', '2026-10-13'), 'Today', 'today label');
check(period('daily', '', ''), 'Today', 'legacy daily label');
check(period('monthly', '2026-10-01', '2026-10-31'), 'Monthly · October 2026', 'monthly label uses the actual month');
check(period('monthly', '', ''), 'Monthly', 'monthly without dates still names the period');

check(period('quarterly', '2026-10-01', '2026-12-31'), 'Quarterly · Q4 2026', 'Q4 label');
check(period('quarterly', '2026-04-01', '2026-06-30'), 'Quarterly · Q2 2026', 'Q2 label');

check(period('semi_annually', '2026-07-01', '2026-12-31'), 'Semi-Annually · H2 2026', 'H2 label');
check(period('semi_annually', '2026-01-01', '2026-06-30'), 'Semi-Annually · H1 2026', 'H1 label');

check(period('annually', '2026-01-01', '2026-12-31'), 'Annually · 2026', 'annual label');

check(period('custom', '2026-10-13', '2026-10-13'), 'Custom Range · Oct 13, 2026', 'custom same-day range');
check(period('custom', '2026-10-01', '2026-10-15'), 'Custom Range · Oct 1–15, 2026', 'custom same-month range');
check(period('custom', '2026-10-28', '2026-11-03'), 'Custom Range · Oct 28 – Nov 3, 2026', 'custom cross-month range');
check(period('custom', '2026-12-28', '2027-01-03'), 'Custom Range · Dec 28, 2026 – Jan 3, 2027', 'custom cross-year range');
check(period('custom', '', ''), 'Custom Range', 'custom without dates still names the period');

check(range('2026-10-13', '2026-10-13'), 'Oct 13, 2026', 'single-day range');
check(range('2026-10-01', '2026-10-15'), 'Oct 1–15, 2026', 'same-month range');
check(range('2026-12-28', '2027-01-03'), 'Dec 28, 2026 – Jan 3, 2027', 'cross-year range');
check(range('', '2026-10-13'), 'Oct 13, 2026', 'end-only range');
check(range('', ''), '', 'empty range');

check(period('', '', ''), '', 'unknown period without dates yields no label');

console.log(`${checks} finance period label checks passed.`);
