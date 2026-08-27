## 1. Upstream Scope and Request Contract

- [x] 1.1 Normalize CRM/VICIdial campaign-code scope resolution case-insensitively and verify all report requests use the selected server plus every enabled mapped campaign.
- [x] 1.2 Add configured report-timezone resolution and pass consistent local date boundaries to current and comparison VICIdial requests.
- [x] 1.3 Preserve transport response classification and extend report metadata so empty, unsupported, parse, permission, and transport states are distinguishable without exposing credentials.

## 2. Historical VICIdial Parsing and Aggregation

- [x] 2.1 Add fixture-backed parser coverage for headerless and headered call-status exports, normalized headers, hourly/status breakdowns, totals rows, malformed fields, and campaign case variants.
- [x] 2.2 Refactor historical call-status parsing to validate supported layouts, aggregate raw campaign/hour/status counts, calculate campaign and combined answer rates, and return explicit metric states.
- [x] 2.3 Refactor agent export parsing to map aliases safely, merge stable agent rows across mapped campaigns, preserve existing talk-time behavior, and parse supported ready/other fields without zero-filling unsupported values.
- [x] 2.4 Refactor disposition parsing to handle header variants and count/percentage display values, exclude totals/unmapped campaigns, aggregate normalized codes, calculate Pareto/contact/funnel values from counts, and preserve table rows.
- [x] 2.5 Reconcile summary, campaign comparison, funnel, time distribution, and source availability contracts so null/unavailable values cannot become confirmed zeros and comparison uses identical scope.

## 3. Browser Contract and UI States

- [x] 3.1 Update the historical dashboard response mapping to consume backend-normalized data only, retain the last successful snapshot for the same filter key, and render confirmed zero separately from unavailable/empty/failed states.
- [x] 3.2 Repair chart/table bindings for hourly volume, status mix, campaign comparison, disposition Pareto, report totals, disposition rows, and Ready/Other time; keep accessible text/table fallbacks and existing Agent Performance/Talk Time behavior.
- [x] 3.3 Remove or isolate legacy client-side VICIdial parsers and zero-coercing formatters that can overwrite backend truth.

## 4. Regression and Validation Coverage

- [x] 4.1 Expand PHPUnit unit/feature tests for summary cards, hourly/status totals, multi-campaign aggregation, campaign isolation, weighted rates, comparison periods, disposition Pareto/contact/table, and agent time states. Browser-only last-good behavior remains in 4.3.
- [x] 4.2 Run focused PHPUnit tests and Laravel Pint; fix regressions while preserving unrelated worktree changes.
- [ ] 4.3 Start/use the local application and validate the Reports page with Playwright at representative desktop/mobile widths, including cards, charts, tables, empty/unavailable states, filter changes, and browser console/network errors.
  - Blocked in this environment: the configured Playwright browser profile is already locked by another session; the session was not terminated.

## 5. Specification and Final Review

- [x] 5.1 Sync the implemented behavior into the main Telephony Operations and Reporting spec and record any intentional differences.
- [ ] 5.2 Review the final diff, run the final focused validation, and archive the completed OpenSpec change.
