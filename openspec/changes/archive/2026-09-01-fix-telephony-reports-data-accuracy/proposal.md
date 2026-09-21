## Why

Telephony Reports currently loses VICIdial report meaning between transport, parsing, aggregation, and the browser. Fixed positional assumptions, incomplete historical time parsing, case-sensitive scope edges, UTC-only date construction, and zero-filling of empty or unparseable data make valid VICIdial metrics appear empty or inaccurate. This change establishes VICIdial as the authoritative source while preserving the last trustworthy snapshot when a refresh fails.

## What Changes

- Normalize and classify VICIdial report responses before metric parsing, including successful empty results, unsupported fields, parse failures, and transport or permission failures.
- Parse supported VICIdial export headers and report variants by normalized column name where possible, with explicit diagnostics for unsupported layouts.
- Resolve one selected VICIdial server and every enabled mapped campaign for the selected CRM campaign, using case-insensitive campaign-code matching and preventing scope escape.
- Aggregate raw campaign, status, disposition, agent, and time totals before calculating rates, percentages, Pareto ordering, contact rate, and comparison metrics.
- Honor the selected local date range and configured report timezone when constructing VICIdial request boundaries.
- Return a stable historical dashboard contract with confirmed zero, empty, unavailable, and failed metric states; never silently convert unavailable values to zero.
- Populate hourly volume, status mix, campaign comparison, disposition totals/table, and supported Ready/Other agent-time metrics from the normalized backend response.
- Keep existing Agent Performance and Talk Time behavior intact while retaining successful dashboard data when a later refresh fails.
- Update the report UI to render truthful unavailable/empty states and accessible chart fallbacks without changing the existing visual language.
- Add regression and browser coverage for parsing, scope isolation, weighted aggregation, comparison periods, availability states, and affected visual sections.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `telephony-operations-and-reporting`: Make historical report parsing, aggregation, availability semantics, date/time handling, and browser presentation authoritative to the scoped VICIdial reports.

## Impact

- Historical report service, shared reporting/transport services, CRM-to-VICIdial scope handling, report API response contract, Reports Blade/Alpine view, and related PHPUnit/feature/browser tests.
- No new dependencies or database tables are expected.
- Existing Supervisor operational flows and already-correct Agent Performance/Talk Time data remain compatibility constraints.
