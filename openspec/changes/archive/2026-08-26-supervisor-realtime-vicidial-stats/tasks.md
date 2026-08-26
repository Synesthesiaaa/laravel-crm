## 1. VICIdial Reporting Client

- [x] 1.1 Add a campaign-scoped named request batch to the Non-Agent API client with shared credentials, bounded HTTP options, response parsing, and independent failures.
- [x] 1.2 Add a reporting-service Supervisor snapshot that concurrently requests `logged_in_agents`, `agent_stats_export`, and `call_status_stats`, and allow bounded options for `user_group_status`.

## 2. Supervisor Aggregation

- [x] 2.1 Parse normalized logged-agent and active-today agent-performance rows, match them to CRM users, and retain per-field CRM fallbacks.
- [x] 2.2 Request and parse `user_group_status` for discovered server user groups and use it for current operational KPIs.
- [x] 2.3 Return per-family source and freshness metadata without exposing connection details or double-counting CRM/VICIdial totals.

## 3. Supervisor Interface

- [x] 3.1 Show operational, performance, and call-total source labels plus latest update time using existing responsive dashboard patterns.
- [x] 3.2 Keep charts and agent cards readable, read-only, and stable during polling/fallback states.

## 4. Tests and Validation

- [x] 4.1 Add unit coverage for batched requests, report parameters, and partial remote failures.
- [x] 4.2 Add Supervisor API regression coverage for mapped-server isolation, real-time group KPIs, agent timing metrics, malformed rows, and CRM fallbacks.
- [x] 4.3 Run focused/full automated tests, Pint, the frontend build, and OpenSpec validation.
- [x] 4.4 Validate the Supervisor dashboard, responsive layout, source labels, and browser health with Playwright.
