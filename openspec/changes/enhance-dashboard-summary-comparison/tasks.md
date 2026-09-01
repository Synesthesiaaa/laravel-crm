## 1. Period and comparison domain logic

- [x] 1.1 Add a reusable `DashboardPeriodService` for month-to-date, capped previous-month, and explicitly completed-month ranges.
- [x] 1.2 Add centralized count/amount variance calculations with increase, decrease, unchanged, new-activity, and zero-baseline states.

## 2. Campaign summary aggregation

- [x] 2.1 Extend `DashboardStatsService` with a deterministic campaign summary method that resolves existing legacy/custom sales attribution rules.
- [x] 2.2 Aggregate current/previous totals and aligned daily count/amount buckets with one bounded chunked read per configured form table, preserving null/zero/negative amount semantics.
- [x] 2.3 Add dashboard currency code/symbol configuration and include period labels and amount-definition metadata in the summary data.
- [x] 2.4 Pass the summary to `DashboardController` without changing existing sales-range filters, permissions, or dashboard layout section behavior.

## 3. Dashboard presentation

- [x] 3.1 Extend the existing stat-card trend presentation to support absolute differences, zero-baseline text, unchanged states, and accessible trend labels.
- [x] 3.2 Add the four executive summary KPI cards and period context inside the existing KPI section.
- [x] 3.3 Add the responsive Volume/Amount comparison chart, visible legend, exact shared tooltip, loading/error/no-data states, and accessible daily table alternative using the existing ApexCharts loader.
- [x] 3.4 Verify chart refresh and teardown remain compatible with existing soft navigation and live dashboard updates.

## 4. Automated verification

- [x] 4.1 Add PHPUnit coverage for month boundaries, February/leap-year and completed-month ranges, comparison formulas, and zero baselines.
- [x] 4.2 Add PHPUnit coverage for legacy/custom attribution, campaign isolation, null/zero/negative amounts, daily alignment, missing days, and independent count/amount trends.
- [x] 4.3 Add feature assertions for rendered summary labels, period context, trend cues, currency formatting, mode controls, empty state, and existing dashboard filters.
- [ ] 4.4 Run Pint, focused PHPUnit tests, the frontend build, and Playwright checks at representative responsive widths; resolve regressions.
