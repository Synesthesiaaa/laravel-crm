# Phase 4 - Backend cleanup

Low-risk code quality improvements, no behavior change unless called out.

## 4.1 Pint sweep

Command: `vendor/bin/pint`

- **Before:** 163 files with style issues across 283 scanned.
- **After:** 0 issues across 280 files (3 files excluded: `docs/vicidial/*.php` reference snippets).
- **Config change:** added `docs/` to `exclude` in [`pint.json`](../../pint.json) so third-party integration examples are not rewritten.
- **Scope:** formatting only. No method signatures, no logic changes.

Recommendation: run `vendor/bin/pint --test` as a CI gate. The existing workflow in [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) already invokes it.

## 4.2 N+1 in telephony diagnostics

**Problem.** [`app/Http/Controllers/Admin/TelephonyDiagnosticsController.php`](../../app/Http/Controllers/Admin/TelephonyDiagnosticsController.php) `checkCampaignServerMappings()` ran a `VicidialServer::where('campaign_code', $code)->where('is_active', true)->first()` inside a `foreach ($campaigns as $code)` loop.

For N campaigns this issues N+1 queries against the database on every admin health check.

**Fix.** Single `whereIn('campaign_code', $campaigns)` query returning a collection keyed by `campaign_code`. Loop then does in-memory lookup. Columns selected narrowly (`campaign_code`, `api_user`, `api_pass`) so we do not ship full rows.

**Query count:** 1 (was N+1).

## 4.3 Graceful logout (Phase 2 companion)

Extraction of the inline logout flow to `window.crmGracefulLogout` in [`resources/js/app.js`](../../resources/js/app.js) removed ~15 lines of inline JS from [`resources/views/layouts/app.blade.php`](../../resources/views/layouts/app.blade.php). Already documented in [`02-ui-fixes.md`](02-ui-fixes.md) §2.3. Counted here because it's a cleanup.

## 4.4 Raw SQL review (read-only)

`rg 'DB::raw\\(' app/` returned 8 hits across 2 files:

- [`app/Services/DashboardStatsService.php`](../../app/Services/DashboardStatsService.php)
- [`app/Http/Controllers/Api/SupervisorAgentsController.php`](../../app/Http/Controllers/Api/SupervisorAgentsController.php)

All uses are **static SQL fragments** for aggregate expressions (`COUNT(*)`, `AVG(CASE WHEN ...)`). No user input is concatenated. **No action**; deferred to a future phase where we can replace them with `selectRaw` bindings once feature tests exist.

## 4.5 Intentionally not done

- **FormRequest migration for top 5 endpoints.** Skipped. The audit plan listed this but the endpoints (call, disposition, attendance, vicidial-session, lead-search) each have different validation shapes and fair test coverage already depends on the current shape. Moving to `FormRequest` classes without tests as a safety net risks silent regression. Deferred to a separate tracked PR after the test suite is green (Phase 5).
- **Dead-code deletion.** Phase 1's scan found no confirmed dead code. Skipped.
- **Logging standardization.** Existing `TelephonyLogger` already covers the hot paths (telephony). Extending it to attendance/auth is a nice-to-have, not a bug fix. Deferred.

## Metrics

| Metric | Before | After |
|--------|--------|-------|
| Pint files with issues | 163 | 0 |
| Pint files scanned | 283 | 280 (3 excluded via config) |
| N+1 sites in diagnostics | 1 (campaign mapping) | 0 |
| Inline logout JS lines | ~17 in Blade | 1 in Blade + 34 in app.js (named, testable) |
| Composer classmap classes | unchanged | unchanged |
