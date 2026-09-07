## 1. Remove stale comparison presentation code

- [x] 1.1 Remove the commented Campaign Comparison section from `resources/views/reports/index.blade.php`.
- [x] 1.2 Remove chart-only campaign state, element lookup, and ApexCharts registration/rendering while preserving campaign rows used by active report sections.

## 2. Add regression coverage

- [x] 2.1 Extend the Reports view feature coverage to assert the campaign-comparison heading and chart element are absent while active report sections remain present.

## 3. Validate and finalize

- [x] 3.1 Run the focused PHPUnit test and format any modified PHP files with Laravel Pint.
- [x] 3.2 Validate the Reports page in Playwright at representative desktop and mobile widths, including browser console health.
- [x] 3.3 Sync the OpenSpec delta into the main reporting specification.
- [x] 3.4 Archive the completed OpenSpec change.
