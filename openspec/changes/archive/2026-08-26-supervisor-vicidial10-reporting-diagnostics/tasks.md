## 1. VICIdial Reporting Diagnostics

- [x] 1.1 Redact Non-Agent API username and password query values from caught connection-exception telemetry.
- [x] 1.2 Derive safe live, degraded, unavailable, and not-configured reporting states from the selected campaign's existing report results.

## 2. Supervisor Experience

- [x] 2.1 Return the safe reporting state and action-oriented message in Supervisor routing metadata without changing per-metric fallback sources.
- [x] 2.2 Present the mapped-server report state in the existing responsive, accessible routing section.

## 3. Tests and Validation

- [x] 3.1 Add regression coverage for report failure diagnostics and redacted connection telemetry.
- [x] 3.2 Run focused tests, Pint, the frontend build, browser validation, and strict OpenSpec validation.
