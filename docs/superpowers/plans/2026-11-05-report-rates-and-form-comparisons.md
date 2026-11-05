# Report rates and form comparisons implementation plan

**Goal:** Implement the approved report formulas and per-form dashboard charts.
**Spec:** ../specs/2026-11-05-report-rates-and-form-comparisons-design.md
**Architecture:** Extend existing aggregation services and Blade charts; retain existing campaign authorization and date bases.
**Tech Stack:** Laravel 12, PHP 8.4, Blade, ApexCharts.

## Tasks

- [x] Add and run failing historical report tests for ANA subtraction, non-system contacts, zero and missing data.
- [x] Implement consistent summary/campaign/comparison rates and expose abandoned metrics in Reports; update affected expectations.
- [x] Add and run failing dashboard tests for per-form activity and daily comparison totals.
- [x] Extend dashboard aggregation payloads and chart controls with per-form data and zero buckets; preserve overall totals and cache invalidation.
- [x] Run affected PHPUnit suites, formatting, Blade compilation and frontend build; inspect the final diff.

## Review focus

Missing ANA columns on a complete report mean zero; failed or partial reports must not silently mean zero. System-only scopes have zero contacted calls. Preserve non-system exclusions and percentage-point comparisons. Avoid rounding before subtraction. Keep empty forms and unmatched previous-month days represented correctly.

## Execution notes

Work in the user-provided checkout so changes are available to the local application. No commits, remote writes, new dependencies or migrations are required. The user approved the design and implementation on 2026-11-05.

## Verification results

- 96 affected PHPUnit tests passed (662 assertions) using SQLite in memory.
- Vite production build, Blade compilation, Pint and git diff whitespace checks passed.
- Isolated Edge rendering verified form selection, the corresponding visible data table, named activity series and no captured JavaScript errors.
- Independent review caught shared-table custom-rule mixing; the regression test failed before the fix and passed afterward. Follow-up review found no additional actionable issue.
- Ruling: shared-table custom rules retain separate form results; Overall counts a row once, with its first qualifying configured rule supplying the amount.
