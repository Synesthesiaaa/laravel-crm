## Context

The existing Supervisor endpoint combines campaign resolution, VICIdial batch requests, raw-row parsing, CRM fallback queries, agent state interpretation, KPI aggregation, and response shaping in `SupervisorAgentsController`. `ReportingService` is also used by both the Reports endpoints and the Supervisor endpoint, while the Reports page performs historical calculations in Alpine. The repository already has a shared `VicidialNonAgentApiService`, campaign-to-server repository, existing report payload contracts, ApexCharts lifecycle helpers, and campaign-scoped Supervisor tests.

The refactor must preserve the selected CRM campaign as the only server-routing key, keep the existing notification endpoint and permission boundary, avoid new dependencies or schema changes, and remain compatible with existing response consumers while introducing clearer contracts for new UI behavior.

## Goals / Non-Goals

**Goals:**

- Establish a dedicated `SupervisorOperationalService` for current-state aggregation and a dedicated historical report aggregation service for date-range analysis.
- Centralize VICIdial status normalization and queue-health rules outside Blade templates and controllers.
- Return explicit source, freshness, availability, and unknown values so unavailable data is not represented as a fabricated zero or healthy state.
- Make Supervisor primary UI content operational and Reports primary UI content historical.
- Aggregate duplicate historical agent rows by stable VICIdial username, not display name.
- Add comparison-period calculations using percentage points for rate metrics.
- Keep low-level VICIdial calls shared and batched; do not create per-agent request waterfalls.

**Non-Goals:**

- No database migrations, new third-party chart libraries, or replacement of the existing CRM visual system.
- No changes to VICIdial authentication, Non-Agent API parameter conventions, campaign/server mapping, notification permissions, or existing telephony action endpoints.
- No inference of unavailable wait, pause, funnel, conversion, occupancy, or heatmap data.
- No removal of legacy API keys that existing clients use; new normalized keys are additive until consumers migrate.

## Decisions

1. **Use focused services with shared transport.** Add `SupervisorOperationalService` and `HistoricalTelephonyReportService`; keep `VicidialNonAgentApiService` as the transport/parser boundary and retain `ReportingService` as the low-level report request facade for compatibility. The controllers become thin request/response adapters. A separate service is preferred over a single configurable aggregator because operational and historical failure semantics, freshness, and time scopes differ.

2. **Represent agent status with a backed enum and additive response fields.** Add a normalized agent-state enum with `AVAILABLE`, `ON_CALL`, `PAUSED`, `RINGING`, `QUEUE`, `OFFLINE`, and `UNKNOWN` values. The service maps raw VICIdial status/sub-status once, then returns `state` and `state_label`; existing lowercase `status` and `status_label` remain during the migration. This prevents VICIdial-specific matching from leaking into Blade.

3. **Represent queue health from configured thresholds and required-data checks.** Add queue thresholds to `config/vicidial.php`. The service returns `healthy`, `warning`, `critical`, or `unknown`, plus the evaluated signals and threshold scope. If the configured required signals are missing, health is `unknown`; the UI uses text labels and icons as well as semantic color.

4. **Keep real-time and historical upstream failures independent.** Supervisor uses its own bounded batch request and CRM lifecycle fallback. Reports use a separate dashboard aggregation call or existing report calls and return partial sections with per-section availability. A failed historical source does not replace a successful operational snapshot and vice versa.

5. **Move historical calculations to a server-side report contract.** Add a dashboard report endpoint that accepts campaign/date/disposition/comparison filters and returns summary, comparison, call trend, campaign comparison, disposition Pareto, optional funnel stages, and deduplicated agent rows. The existing individual report endpoints remain available for compatibility and diagnostics.

6. **Use visible tables as chart fallbacks.** ApexCharts remains the existing visualization layer. Every analytical chart is backed by a semantic table or labelled summary, and charts are sorted/limited for readability. Supervisor queue trend uses only the short operational window available from the snapshot; Reports owns full selected-range trends.

7. **Apply UI guidance to the existing system.** Keep current pink accent, cards, typography, spacing, and icon components. Use dense responsive grids, explicit loading/empty/error states, keyboard-accessible tabs/details, `aria-live` for failures and freshness, reduced-motion chart settings, and no status meaning conveyed by color alone.

## Risks / Trade-offs

- [Risk] VICIdial export headers and row formats vary by deployment. → Mitigation: reuse defensive row normalization, preserve raw diagnostics, and mark missing metrics as `null`/`—` rather than guessing.
- [Risk] Moving aggregation from browser to PHP can change edge-case totals. → Mitigation: add unit tests with representative pipe-delimited rows and compare the new contract with existing UI normalizers during rollout.
- [Risk] A dashboard endpoint could increase initial response time. → Mitigation: batch independent VICIdial requests, memoize parsed inputs within one request, and return section-level partial results.
- [Risk] Existing API consumers depend on legacy lowercase status and historical keys. → Mitigation: keep additive compatibility fields and retain existing routes while the UI consumes normalized fields.
- [Risk] Queue thresholds may be deployed without enough data. → Mitigation: make unknown the safe default and expose the evaluated data-availability state.

## Migration Plan

1. Add value objects/enums and focused service tests without changing routes.
2. Extract Supervisor aggregation behind the existing `/api/supervisor/agents` response, preserving legacy fields.
3. Add the historical dashboard contract and have Reports consume it while retaining raw diagnostic endpoints.
4. Update Supervisor and Reports views, then validate desktop/tablet/mobile and partial-failure states with Playwright.
5. Sync the capability spec, run Pint and focused PHPUnit tests, and archive only after browser and regression checks pass.

Rollback is a code rollback: the existing VICIdial client, routes, and legacy response fields remain available, and no data migration is required.

## Open Questions

- Which VICIdial fields are guaranteed in every deployment for oldest wait, abandonment, pause duration, and disposition classifications? Until configured/validated, those fields remain unavailable rather than inferred.
- Should the future reporting dashboard endpoint replace the individual report endpoints after all frontend consumers migrate? This change keeps both paths for compatibility.
