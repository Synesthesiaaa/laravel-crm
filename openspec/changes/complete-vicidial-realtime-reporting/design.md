## Context

The application already has a campaign-scoped `SupervisorOperationalService`, a historical report aggregation service, and a shared Non-Agent API transport. The current transport returns a generic failure for connection errors and treats any HTTP-success body as a successful report. The configured `mbsales` endpoint is reachable but returns a login error when credentials are not accepted, which is indistinguishable from an empty report to downstream parsers.

The application must keep CRM campaign isolation, server-side credential handling, and existing VICIdial function names. VICIdial installations differ in their application path and in which report columns they return, so the implementation must be tolerant of response shape while refusing to invent unsupported metrics.

## Goals / Non-Goals

**Goals:**

- Make every Non-Agent API result carry a safe diagnostic classification and transport metadata.
- Resolve all VICIdial endpoint categories from the selected server in one place.
- Keep Supervisor operational requests independent from historical Reports requests and make source health/freshness explicit.
- Add Live and Today report modes using one normalized backend snapshot per refresh, with bounded rolling metrics from available CRM data.
- Preserve successful values during transient refresh failures and expose stale/offline status instead of zeroing the dashboard.
- Exercise representative login, permission, network, parser, isolation, and partial-failure paths with sanitized fixtures.

**Non-Goals:**

- Automatically changing VICIdial users, permissions, firewall rules, SSL certificates, or CRM server records.
- Introducing direct VICIdial database queries without an existing authorized connection and validated schema contract.
- Exposing credentials, authentication URLs, raw customer data, or arbitrary raw report payloads to normal users.
- Claiming that a historical report is real-time or deriving conversion rates from unconfigured disposition names.

## Decisions

### 1. Add a central endpoint resolver

Create `VicidialEndpointResolver` with methods for Non-Agent API, Agent API, and known report endpoint categories. It prefers an explicit per-server URL, derives sibling paths from the server's configured Agent API path, and only uses global defaults when the server has no corresponding setting. Controllers and services will call the resolver rather than concatenate paths.

Alternatives considered: keeping URL logic in `VicidialNonAgentApiService` (would duplicate future report URL rules) and allowing the global URL to override a server (would violate campaign/server isolation).

### 2. Classify responses at the transport boundary

Extend `OperationResult` with a non-sensitive metadata/diagnostics array. The transport records endpoint category, duration, HTTP status, content type, response size, parsed row count, and an error classification. It identifies login/permission/error pages before parsing and maps exceptions to network, timeout, connection-refused, or SSL classifications. User-facing messages remain generic and safe.

Alternatives considered: parsing in each consumer (inconsistent failure semantics) and returning raw remote bodies in API responses (credential/PII exposure risk).

### 3. Keep one batch per normalized snapshot

Supervisor continues to issue one bounded concurrent batch for the independent live functions, and Reports historical mode continues to issue one batch for its three report families. Live Reports calls the normalized Supervisor snapshot contract rather than making one request per KPI. A short cache is not enabled by default until request volume is measured; the transport remains fast-fail with zero retries for Supervisor polling.

Alternatives considered: per-card polling (request storm) and long-lived caching (stale agent state).

### 4. Make freshness explicit in the response contract

Every snapshot returns generated time, source last-success time, source health, and stale threshold/status. A transient failure returns the last in-memory browser snapshot when available and marks it stale/degraded; the initial failure returns null/unavailable values. The backend never converts missing remote signals to confirmed zeroes.

Alternatives considered: resetting to zero (misleading operations) and leaving a permanent green Live badge (hides outages).

### 5. Use a single Live/Today/Historical report mode contract

Historical remains date-range aggregation. Today combines midnight-to-now historical totals with the current operational snapshot and labels the scopes separately. Live shows current agents/calls/queue plus rolling CRM event metrics only when enough timestamps exist. Dispositions are limited to completed calls and are marked unavailable when no reliable source exists.

Alternatives considered: duplicating Supervisor markup in Reports and labelling full-day totals as live.

## Risks / Trade-offs

- [VICIdial response columns vary by version] → Normalize known aliases, preserve raw status fields, and classify missing required columns as unavailable.
- [A valid empty report can look like an outage] → Treat transport-successful empty result sets as `EMPTY_DATA`, not connectivity failure; only parsed values populate metrics.
- [Live reporting can increase traffic] → Reuse the complete snapshot endpoint, use a centralized interval, prevent overlapping requests, and keep historical refresh slower.
- [Last-good browser data can become misleading] → Include age/threshold metadata and visibly mark stale/offline state.
- [No live VICIdial credentials or native dashboard are available in CI] → Use sanitized fixtures and clearly separate automated verification from the one-time native comparison required in deployment.
- [Configured credentials may be invalid on the mapped server] → Do not repair secrets automatically; report `AUTHENTICATION_FAILED` with administrator remediation guidance.

## Migration Plan

1. Deploy the application changes and run focused tests/builds.
2. Configure each active `vicidial_servers` row with its exact Agent API or Non-Agent API URL, encrypted API credentials, source, and campaign mapping.
3. From the server running Laravel, call the diagnostics endpoint and inspect classification, latency, and content type without exposing credentials.
4. On VICIdial, validate the API user, report access, allowed IP/network, and required Non-Agent API functions; then compare the Supervisor snapshot with the native live-agent/ingroup screens.
5. Rollback is code-version rollback only; no schema migration is introduced. Reverting the code restores the previous API contracts, while server configuration remains unchanged.

## Open Questions

- Which additional real-time VICIdial functions are enabled on each deployment beyond the verified Non-Agent functions? Unsupported functions remain unavailable until a sanitized response fixture is supplied.
- What exact local timezone should define the Today midnight boundary for each CRM campaign? The existing application timezone is used until campaign-specific timezone configuration is introduced.
