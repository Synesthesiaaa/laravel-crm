## 1. Front-End Shell and Soft-Nav Lifecycle

- [x] 1.1 Add a shared soft-nav lifecycle hook in `resources/js/soft-navigate.js` for before-swap cleanup and after-swap re-init.
- [x] 1.2 Migrate the chart and widget pages to the shared lifecycle pattern and dynamic chart loader instead of ad hoc mount/teardown code.
- [x] 1.3 Audit the remaining `@push('scripts')` pages for one-shot init assumptions and convert the ones that still depend on page-local DOM state.

## 2. Telephony Runtime and Browser Ownership

- [x] 2.1 Make `resources/js/telephony-media-path.js` the only runtime helper used to decide SIP.js registration and telephony teardown.
- [x] 2.2 Update the authenticated layout, Vicidial session bootstrap, and agent screen code to honor the helper before calling `TelephonyCore.register()` or `TelephonyCore.destroy()`.
- [x] 2.3 Keep `both` mode migration-only by surfacing a clear warning and avoiding any behavior that treats it as a normal operating mode.

## 3. Backend Hardening and Portable Data Paths

- [x] 3.1 Convert the highest-risk mutation endpoints covered by the audit to the existing request-object patterns where validation is still inline or inconsistent.
- [x] 3.2 Move telephony diagnostics campaign mapping into a dedicated service and replace per-campaign lookups with batched map-based reads.
- [x] 3.3 Add production-only webhook secret enforcement and explicit rejection paths for missing or invalid secrets.
- [x] 3.4 Make the attendance-related migration portable across SQLite and production engines so PHPUnit stays green on the test database.

## 4. Tests and Release Verification

- [x] 4.1 Add or update PHPUnit coverage for soft-nav re-entry, widget lifecycle cleanup, and logout cleanup.
- [x] 4.2 Add or update PHPUnit coverage for telephony media-path gating, diagnostics batching, webhook rejection, and migration portability.
- [x] 4.3 Run `vendor/bin/pint --dirty --format agent`, the focused PHPUnit subset, and browser smoke checks for the affected flows.
