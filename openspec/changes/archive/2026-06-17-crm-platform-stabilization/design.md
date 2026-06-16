## Context

This repository is a Laravel 12 CRM with a persistent Blade shell, a soft-navigation layer that swaps `#main-layout`, and long-lived telephony state in the browser. The current structure works, but audit findings show that a few cross-cutting problems are now affecting real users: page-local scripts can stop working after navigation, repeated dashboard visits can duplicate chart instances, browser telephony ownership can become ambiguous, and a MySQL-specific migration previously blocked the SQLite test path.

The change needs to improve the whole platform without replacing the current architecture. The app must remain server-rendered, keep the persistent shell that preserves telephony and iframe state, and continue to use Laravel, Alpine, Reverb, and the existing Vicidial/SIP.js integration.

## Goals / Non-Goals

**Goals:**
- Make soft-navigation re-entry reliable for all authenticated pages.
- Ensure page-scoped widgets are disposed and recreated safely.
- Keep exactly one browser media path authoritative for any session.
- Make the most user-facing backend flows portable, validated, and easier to test.
- Close the known operational gaps around logout, diagnostics, migrations, and webhook handling.

**Non-Goals:**
- Replace Blade or convert the app into an SPA.
- Replace Vicidial, Asterisk, SIP.js, or Reverb.
- Add a new frontend framework or a new CI stack.
- Rewrite the entire controller layer in one pass.

## Decisions

### 1. Keep the current soft-navigation shell, but add explicit page lifecycle boundaries

The shell already owns the navigation swap. The design keeps that model and adds a small lifecycle contract so page-scoped code can clean up before a swap and mount again after a swap.

Recommended shape:
- `resources/js/soft-navigate.js` remains the owner of same-origin interception and `#main-layout` replacement.
- Before the swap, the shell emits a cleanup signal and destroys the Alpine tree for `#main-layout`.
- After the swap, the shell re-executes the page scripts that were pushed after `#soft-nav-scripts-marker`, then re-initializes the Alpine tree.
- Page scripts that create charts, timers, observers, or other long-lived objects register a dispose callback and must clear any prior instance before constructing a new one.

Why this over a full SPA or per-page one-off fixes:
- A SPA would disrupt the current telephony/iframe persistence model and add a large migration risk.
- One-off patches fix known leaks but do not create a reusable boundary, so the next page script can regress in the same way.

### 2. Make `window.TelephonyMediaPath` the source of truth for browser audio ownership

The codebase already has a media-path helper in `resources/js/telephony-media-path.js`. The design keeps one runtime switch and makes the authenticated layout read that switch before it calls `TelephonyCore.register()`.

Recommended shape:
- `sipjs` remains the default path.
- `viciphone` skips SIP.js registration and leaves Vicidial in control.
- `both` stays migration-only and must warn operators because dual registration is not normal operating mode.
- Every browser call site that can register or destroy `TelephonyCore` must honor the same helper, including the authenticated layout, the Vicidial session bootstrap, and the agent screen.

Why this over implicit dual registration:
- The audit already observed race conditions and call-setup flakiness when both paths try to own the same extension.
- An explicit switch is easier to diagnose, test, and roll back than "let both register and hope Asterisk sorts it out."

Data flow:
- `config/webrtc.php` defines the deployment choice.
- `resources/views/layouts/app.blade.php` exposes that choice to the browser as a bootstrap value.
- `resources/js/telephony-media-path.js` normalizes the value and exposes helper predicates.
- `resources/js/app.js`, `resources/js/vicidial-session.js`, and the agent screen entry points use the helper before they call `TelephonyCore.register()` or `TelephonyCore.destroy()`.

### 3. Keep controllers thin and move validation and diagnostics into focused request/service boundaries

The repo already uses request classes in several areas, so the design extends that pattern to the hot paths that currently rely on inline validation or embedded query logic.

Recommended shape:
- Mutation endpoints with meaningful user input should use dedicated request objects.
- Telephony diagnostics should move into a dedicated service that returns structured results instead of scattering query logic through the controller.
- Query-heavy checks should operate on batched collections keyed by business identifiers, not on per-item database lookups.

Why this over leaving validation and checks inline:
- Request objects make the input contract visible and testable.
- Service boundaries make the N+1 fixes easy to unit test and reduce the chance that future diagnostics reintroduce the same pattern.

### 4. Load heavyweight front-end code only on the pages that need it

The dashboard, supervisor, and reporting-style pages are the main consumers of charting. The design keeps `ApexCharts` as a dynamic import and pairs that with explicit chart disposal on soft-nav exit.

Why this over eager global bundling:
- A global chart bundle penalizes pages that never render charts.
- Dynamic import keeps the core app shell lighter while still supporting rich dashboard pages.

### 5. Verify the stabilization slice with targeted tests, not a new testing framework

The repository already has PHPUnit coverage and a working browser stack. The design uses both:
- PHPUnit feature and unit tests for backend contracts, telemetry, and migration portability.
- A small browser smoke matrix for soft-nav return, dashboard revisit, and logout cleanup.

Why this over adopting a large new E2E stack now:
- The current failures are specific and regression-prone, not broad enough to justify a framework migration.
- The stabilization slice needs coverage quickly, and the existing test layers are already in place.

## Risks / Trade-offs

- [Lifecycle hooks can be missed by page code] -> Mitigation: make the cleanup/mount pattern part of the page script template and enforce it in the affected dashboard pages first.
- [Telephony mode misconfiguration can still happen operationally] -> Mitigation: keep the default on `sipjs`, surface a diagnostics warning for `both`, and fail closed on invalid runtime assumptions.
- [Request/service extraction can reveal hidden assumptions] -> Mitigation: implement the smallest viable request objects and preserve existing response shapes until tests confirm the new boundaries.
- [Browser smoke tests add maintenance cost] -> Mitigation: limit them to the two or three user flows that have already regressed in the audit.
- [Dynamic imports can fail at runtime if pages are not guarded] -> Mitigation: guard chart initialization by container presence and no-op cleanly when the page does not render a chart.

## Migration Plan

1. Refactor the app shell and soft-nav lifecycle so page code can clean up before swaps and rehydrate after swaps.
2. Convert the chart/dashboard pages to the lifecycle pattern and remove duplicate-instance behavior.
3. Move logout cleanup and telephony media-path checks into shared browser code with explicit error handling.
4. Extract request objects and service methods for the highest-risk backend flows, starting with diagnostics and mutation endpoints already covered by the audit.
5. Fix the portable migration and add regression tests that run under SQLite and the production database engine.
6. Add a browser smoke check for returning to a page after soft-nav and for logging out during an active telephony session.

Rollback strategy:
- Revert the per-phase code changes independently if a regression is introduced.
- Keep `TELEPHONY_MEDIA_PATH=sipjs` as the safe fallback.
- Remove or disable the browser smoke checks temporarily if they block the stabilization slice, but keep the PHPUnit coverage.

## Open Questions

- Should production remain on `sipjs` by default for this stabilization change, or should a deployment-specific override move the default to `viciphone` after operator validation?
- Should the browser smoke checks be added to CI immediately, or should they start as a manual gate while the shell lifecycle changes settle?
- Should webhook secret enforcement use `401/403` for invalid traffic or `503` when the secret is missing in production?
