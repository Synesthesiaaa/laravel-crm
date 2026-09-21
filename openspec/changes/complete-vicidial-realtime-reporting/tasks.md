## 1. Transport diagnostics and endpoint resolution

- [x] 1.1 Add structured metadata to `OperationResult` without breaking existing callers.
- [x] 1.2 Create `VicidialEndpointResolver` and route Non-Agent URL construction through it while preserving explicit per-server URLs and application paths.
- [x] 1.3 Classify HTTP, HTML, error, empty, malformed, timeout, connection-refused, and SSL responses at the Non-Agent API boundary.
- [x] 1.4 Emit safe structured request diagnostics and ensure all connection/body logging is credential- and PII-safe.
- [x] 1.5 Add an admin-readable campaign/server diagnostic summary that exposes classification and remediation without secrets.

## 2. Real-time snapshot reliability

- [x] 2.1 Extend Supervisor snapshots with source health, last-successful timestamps, stale thresholds, and explicit unsupported/null values.
- [x] 2.2 Add bounded rolling CRM metrics for completed calls, answered calls, wait/talk durations, and dispositions where timestamps support them.
- [x] 2.3 Preserve campaign/server isolation and partial success when an individual VICIdial source fails; add attention reasons for stale/unavailable data.
- [x] 2.4 Add short-lived server-scoped caching only where it reduces duplicate concurrent snapshots without making agent state stale.

## 3. Reports modes and UI

- [x] 3.1 Add Live and Today report API modes while retaining Historical date-range behavior and explicit time-scope labels.
- [x] 3.2 Add Live/Today selectors, operational cards, rolling-window sections, source health, stale state, and section-level retry/empty states to Reports.
- [x] 3.3 Prevent overlapping report polls, reuse one snapshot per refresh, and clean up timers/charts on navigation or mode changes.
- [x] 3.4 Preserve accessible labels, responsive layout, reduced-motion behavior, raw-debug restrictions, and existing design tokens.

## 4. Verification

- [x] 4.1 Add unit tests for endpoint derivation, response classifications, redaction, empty/parse failures, stale states, and rolling metric nullability.
- [x] 4.2 Add feature tests for campaign/server isolation, partial failures, Live/Today/Historical API responses, and diagnostic privacy.
- [x] 4.3 Add sanitized VICIdial fixtures and run PHPUnit, Pint, frontend build, browser flow/responsive checks, and strict OpenSpec validation.
- [ ] 4.4 Compare the configured `mbsales` snapshot with the native VICIdial dashboard when native-dashboard access is available; local validation verified the authenticated API responses, but did not open the production VICIdial supervisor screen.
