## 1. Persist sale-amount configuration

- [x] 1.1 Add the additive `is_sale_amount` boolean migration to `form_fields`.
- [x] 1.2 Add fillable and boolean cast metadata to `FormField`.
- [x] 1.3 Add failing and passing PHPUnit coverage for numeric and non-numeric persistence.

## 2. Configure sale fields in Field Logic

- [x] 2.1 Validate the optional `is_sale_amount` checkbox in store and update requests.
- [x] 2.2 Persist the flag only for fields whose effective type is `number`.
- [x] 2.3 Add the add/edit checkbox and sale-status list column with existing Blade/Alpine conventions.
- [x] 2.4 Add feature coverage for rendered controls and update behavior.

## 3. Aggregate field-driven dashboard sales

- [x] 3.1 Add failing service tests for one-sale-per-row counting, marked-value summing, empty/malformed values, agent grouping, and fallback behavior.
- [x] 3.2 Resolve marked numeric fields against registered form tables and ignore missing columns safely.
- [x] 3.3 Use field-driven sales for campaigns with marked fields in the rolling KPI and month-to-date leaderboard.
- [x] 3.4 Preserve the existing disposition/lead-data fallback when a campaign has no marked fields.
- [x] 3.5 Invalidate the affected campaign’s dashboard caches after Field Logic changes.

## 4. Verify and finalize

- [x] 4.1 Run Pint on modified PHP files.
- [x] 4.2 Run the focused PHPUnit tests and frontend build if needed.
- [x] 4.3 Verify the Field Logic and dashboard flow with Browser and inspect console health.
- [x] 4.4 Sync and archive the completed OpenSpec change after all checks pass.
