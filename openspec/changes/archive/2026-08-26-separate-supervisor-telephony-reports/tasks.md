## 1. Shared contracts and configuration

- [x] 1.1 Add normalized Supervisor agent-state and queue-health enums/value objects with safe `UNKNOWN` handling.
- [x] 1.2 Add configurable Supervisor queue thresholds and report disposition/comparison defaults without changing dependencies or schema.
- [x] 1.3 Add unit tests for VICIdial status mapping, duration parsing, queue-health thresholds, and percentage-point comparison math.

## 2. Supervisor operational backend

- [x] 2.1 Extract current Supervisor snapshot loading, campaign isolation, VICIdial batch results, and CRM fallback aggregation into a dedicated operational service.
- [x] 2.2 Move raw VICIdial row normalization and agent state interpretation behind the operational service; return additive normalized fields, queue snapshot, exceptions, source metadata, and freshness.
- [x] 2.3 Keep `/api/supervisor/agents` backward compatible while making its controller a thin adapter and preserving notification/action routes.
- [x] 2.4 Add feature coverage for normalized agent states, queue metrics/health, unavailable data, partial report failures, campaign/server isolation, and notification compatibility.

## 3. Historical reporting backend

- [x] 3.1 Add a historical report aggregation service and dashboard endpoint for date range, campaign, in-group, disposition scope, and comparison filters.
- [x] 3.2 Implement summary KPIs, previous-period calculation, campaign comparison, disposition Pareto grouping, and optional mapping-driven funnel output with partial-source states.
- [x] 3.3 Aggregate duplicate agent rows by stable VICIdial identifier and return agent performance/time-distribution data without display-name deduplication.
- [x] 3.4 Add unit and feature coverage for date filtering, comparisons, disposition scope, campaign aggregation, duplicate sessions, zero activity, and unavailable report sources.

## 4. Supervisor interface

- [x] 4.1 Replace Supervisor primary reporting KPIs with operational KPIs and explicit unavailable/loading states while retaining routing context and source/freshness text.
- [x] 4.2 Rename tabs to Agent Status, Queue Monitor, and Live Wallboard; add short-window queue trend and attention-required operational exceptions.
- [x] 4.3 Update agent cards and wallboard for normalized states, readable durations, responsive layout, accessible status text, reduced motion, and no fabricated values.

## 5. Reports interface

- [x] 5.1 Replace ambiguous report KPI cards with historical metrics including Agents With Activity, contact rate, average talk, calls per agent, and comparison indicators.
- [x] 5.2 Add comparison-period controls, call trend, campaign comparison, call funnel, Pareto disposition chart/table, agent performance, and time distribution sections using existing ApexCharts lifecycle helpers.
- [x] 5.3 Keep the collapsed diagnostic/recording utility restricted and add explicit loading, empty, partial-error, and responsive table states.

## 6. Verification and specification lifecycle

- [x] 6.1 Run Pint and focused PHPUnit tests for all changed PHP behavior.
- [x] 6.2 Run the frontend build/lint commands that exist in the repository and verify no new browser console errors.
- [x] 6.3 Use Playwright to validate Supervisor and Reports at desktop, tablet, and mobile widths, including empty and partial-failure states.
- [x] 6.4 Sync the OpenSpec capability with the implemented behavior and archive only after all required checks pass.
