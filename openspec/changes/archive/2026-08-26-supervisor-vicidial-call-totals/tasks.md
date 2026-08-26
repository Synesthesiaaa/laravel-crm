## 1. VICIdial reporting integration

- [x] 1.1 Extend `ReportingService::callStatusStats` to accept the Supervisor's bounded HTTP options.
- [x] 1.2 Add campaign-mapped remote call-status retrieval and defensive parsing for total, answered, and hourly values.
- [x] 1.3 Prefer successful VICIdial aggregates and fall back to CRM call sessions without double-counting.
- [x] 1.4 Parse and use numeric `calls_today` values from logged-in-agent rows with a CRM fallback.

## 2. Supervisor dashboard and tests

- [x] 2.1 Keep KPI labels and chart data aligned with the selected source and preserve the existing campaign/server context.
- [x] 2.2 Add feature coverage for remote totals, remote failure fallback, campaign isolation, and per-agent calls-today enrichment.
- [x] 2.3 Add/update unit coverage for ReportingService request parameters and HTTP options.

## 3. Validation and documentation

- [x] 3.1 Run focused and full PHPUnit tests, Pint, and the frontend build.
- [x] 3.2 Validate configured and fallback Supervisor flows with Playwright and confirm no credentials or URLs are exposed.
- [x] 3.3 Sync the capability specification and archive the completed change.
