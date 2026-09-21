## Why

The CRM has grown into a multi-surface call-center platform, and several cross-cutting defects are now visible to end users: soft-navigation swaps can break page-local scripts, dashboard widgets can leak or duplicate on revisit, telephony bootstrap can race between SIP.js and Vicidial iframe audio paths, and one database migration previously blocked the SQLite test suite. We need a stabilization pass that makes navigation, telephony, backend behavior, and automated verification reliable before more features are added.

This change focuses on user-facing stability and operational safety. The goal is to reduce "works on refresh" behavior, eliminate ambiguous runtime ownership, and make the codebase easier to trust when agents, supervisors, and admins move through the app all day.

## What Changes

- Stabilize the persistent app shell so soft-navigation re-entry consistently rehydrates page UI and page-level scripts.
- Make chart and widget lifecycles explicit so repeated navigation does not leak or stack instances.
- Consolidate logout and telephony cleanup paths into testable, reusable browser logic.
- Define one source of truth for telephony media path selection so SIP.js and Vicidial audio do not fight for the same session.
- Harden backend hotspots by reducing N+1 queries, tightening request validation, and keeping public-facing webhooks and session behavior safe in production.
- Restore and expand test coverage around the known failure modes, including portable migrations, logout cleanup, soft-nav regressions, and telephony diagnostics.
- Improve responsiveness where it matters by avoiding unnecessary always-on front-end payloads for pages that do not need them.

## Capabilities

### New Capabilities

- `platform-stabilization`: Cross-cutting reliability improvements for the CRM shell, front-end lifecycle, telephony runtime, backend contracts, and test/security gates.

### Modified Capabilities

## Impact

- Affects the authenticated app shell in `resources/views/layouts/app.blade.php` and the soft-navigation runtime in `resources/js/soft-navigate.js`.
- Affects reusable front-end bootstrap code in `resources/js/app.js` and page-level dashboard/admin scripts that depend on soft-nav re-entry.
- Affects telephony controllers and services that currently rely on implicit runtime ownership or repeated status polling.
- Affects migrations and PHPUnit coverage where portability and regression detection are currently incomplete.
- Affects public webhook handling, session defaults, and admin diagnostics that surface failures to operators and supervisors.
- Does not require dependency changes to ship the first stabilization slice.
