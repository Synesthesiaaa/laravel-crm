## Context

The current agent Call History page queries CRM-created `call_sessions`, while CRM form submissions live in `crm_call_history`. Neither is a complete historical telephony source. The existing VICIdial reporting service calls aggregate Non-Agent API functions, which cannot provide one row per call.

VICIdial server records already contain database connection credentials. The documented VICIdial schema exposes row-level outbound calls in `vicidial_log` and inbound/closer calls in `vicidial_closer_log`. Both tables include the call identifier, lead/list/campaign, call date, epochs, duration, status, phone number, user, and termination reason; closer rows additionally expose `queue_seconds`.

## Goals / Non-Goals

**Goals:**

- Make VICIdial row-level logs the authoritative source for both agent and admin Call History views.
- Query exactly one server selected by the existing CRM campaign scope resolver and only its enabled historical campaign mappings.
- Normalize both source tables into one typed internal record and one API/resource shape.
- Keep all filtering, privacy decisions, user association, pagination, and sorting on the server.
- Preserve unknown historical agents and unmapped statuses instead of dropping rows.
- Distinguish a confirmed empty result from a missing/failed remote database.
- Reuse the existing Blade/Tailwind design tokens and table/form/badge components.

**Non-Goals:**

- No synchronization table, background sync job, deduplication key, or local retention policy; VICIdial remains the live historical store.
- No use of aggregate Agent Performance or report rows as individual calls.
- No inferred talk time, direction, lead relationship, or phone-number rewrite when the source does not provide it.
- No new phone privacy policy; current Call History displays are preserved unless an existing policy is later introduced.
- No changes to CRM form submission history or live call-session state transitions.

## Decisions

### 1. Query the mapped VICIdial database on demand

`VicidialHistoricalCallProvider` will build a per-server Laravel MySQL connection from the `VicidialServer` database fields and execute read-only queries against `vicidial_log` and `vicidial_closer_log`. The two source queries will be combined with `UNION ALL`, filtered by date, mapped campaign codes, and user-provided filters before `LIMIT/OFFSET` pagination. The provider will also issue one scoped metadata query for distinct agent/status options, avoiding per-row remote lookups.

Alternative considered: Non-Agent API `phone_number_log` or aggregate reports. Rejected because the documented API is phone-number-specific or aggregate and cannot retrieve all campaign calls with complete row-level fields. Local synchronization was also rejected because it introduces freshness, retention, and deduplication responsibilities without an existing need.

### 2. Reuse the centralized historical CRM campaign scope

The service will call `CrmCampaignVicidialScopeResolver::resolve()` and `VicidialCampaignScope::narrowCampaignCodes(..., true)`. The selected server and codes are passed together to the provider. An invalid secondary campaign filter returns no authorized records and never reaches an unrestricted remote query.

### 3. Normalize from documented source columns

Outbound rows are identified by `source_table = vicidial_log` and inbound/closer rows by `source_table = vicidial_closer_log`; this is the authoritative direction signal. `length_in_sec` is the Call Duration value. `queue_seconds` is exposed as Wait/Ring Time only for closer rows. No talk-time value is emitted because the selected source tables do not expose an independent talk field. `call_date` is interpreted in the configured VICIdial report timezone, while `start_epoch` and `end_epoch` are converted to the same timezone when present.

### 4. Associate users by VICIdial login

After the remote page is normalized, local `User` records are loaded in one `withTrashed()` query keyed by `vici_user`. This preserves disabled/deleted CRM users and supports duplicate display names. If no mapping exists, the raw VICIdial user is retained as the agent name and the API marks the CRM user as unavailable.

### 5. Treat status and disposition as separate fields

The raw VICIdial `status` is preserved for display and filtering. A CRM disposition label is resolved from active campaign/global `DispositionCode` rows by status code; if no mapping exists the label is `Unmapped` and the raw code remains visible. Mapping is performed in Laravel, never hard-coded in JavaScript.

### 6. Use a shared result for SSR and API

`CallHistoryService` will expose the normalized paginated result to the agent controller, admin controller, and a new authenticated API controller. `HistoricalCallResource` will serialize the same DTO for JSON. The API includes pagination, effective scope/filter data, and safe source-health metadata; remote errors are returned as unavailable rather than as an empty success.

### 7. Keep the interface dense but responsive

The Call History table will show Date/Time, Agent, Phone, Status, Disposition, Duration, and Campaign, with optional source/list/lead/direction details in a native `<details>` disclosure. Filters use visible labels, responsive wrapping, stable design tokens, and a horizontal table scroll wrapper on narrow screens. Healthy empty, unavailable, and loaded states have distinct accessible copy; unavailable state includes a retry link.

## Risks / Trade-offs

- [A VICIdial installation lacks one of the documented log tables or database access] → return `UNAVAILABLE`, log only safe server/scope/query metadata, and never show a false zero.
- [Remote history is large] → constrain by campaign/date, use indexed `call_date`/`campaign_id` predicates, execute one count plus one page query, and keep pagination server-side.
- [Older VICIdial rows use an unknown status or missing user] → preserve the raw code/login and render `Unmapped` or `CRM user unavailable`.
- [Remote database call time affects page latency] → use the existing configured connection/timeout conventions where available and expose source-health copy so users understand the failure.
- [A source schema variant changes a selected column] → fail the provider safely and log a sanitized classification; do not guess alternate columns or fabricate values.
- [Existing tests expect local call sessions] → update Call History coverage to use provider fixtures/mocks while retaining separate tests for live `CallSession` behavior.

## Migration Plan

No database migration is required. Deploy the provider, service/controller/view, route, and tests together. Existing `call_sessions`, `crm_call_history`, and campaign mappings remain untouched. Rollback is a code rollback and does not modify VICIdial or CRM data.

## Open Questions

- Automated tests use sanitized rows and cannot claim live comparison. A read-only probe on 2026-09-02 against the configured server and mapped campaign returned `REMOTE_DATABASE_ERROR`, so native VICIdial rows could not be compared in this environment.
- If a future installation exposes a separate authoritative talk-time column, it can be added as a documented provider mapping without changing the API shape.
