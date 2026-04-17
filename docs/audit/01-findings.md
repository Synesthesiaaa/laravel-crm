# Phase 1 - Discovery findings

Prioritized backlog of bugs and cleanup opportunities identified during the audit.

Severity:
- **P1** - user-visible bug or data-risk
- **P2** - quality / maintainability / moderate risk
- **P3** - nice-to-have

## Summary

| # | Area | File | Severity | Symptom | Fix location |
|---|------|------|----------|---------|--------------|
| 1 | Migration / Tests | [`database/migrations/2026_04_15_100002_extend_attendance_logs_event_type_length.php`](../../database/migrations/2026_04_15_100002_extend_attendance_logs_event_type_length.php) | **P1** | MySQL-only `ALTER TABLE ... MODIFY` breaks SQLite test runs; whole suite halts | Phase 5 |
| 2 | UI soft-nav | [`resources/views/dashboard.blade.php`](../../resources/views/dashboard.blade.php), [`resources/views/admin/dashboard.blade.php`](../../resources/views/admin/dashboard.blade.php) | **P1** | ApexCharts instances leak on repeated soft-nav (no `destroy()` on re-init) | Phase 2 |
| 3 | UI soft-nav | [`resources/views/layouts/app.blade.php`](../../resources/views/layouts/app.blade.php) | **P1** | Theme toggle dead after first soft-nav (already fixed via event delegation) | **done** |
| 4 | UI soft-nav | [`resources/views/layouts/app.blade.php`](../../resources/views/layouts/app.blade.php) lines 199-215 | **P2** | Logout flow uses inline axios + iframe reset + form submit inside `@submit.prevent`; hard to test; no error surfacing if axios rejects | Phase 2 |
| 5 | N+1 | [`app/Http/Controllers/Admin/TelephonyDiagnosticsController.php`](../../app/Http/Controllers/Admin/TelephonyDiagnosticsController.php) `checkCampaignServerMappings()` | **P2** | One `VicidialServer::where(...)->first()` per campaign in a loop | Phase 4 |
| 6 | Telephony | [`resources/views/layouts/app.blade.php`](../../resources/views/layouts/app.blade.php) lines 520-528 + Vicidial iframe | **P2** | `TelephonyCore.register()` and Vicidial agent iframe can both run for the same extension; no single source of truth documented | Phase 3 |
| 7 | Dead/stub code | [`tests/Unit/ExampleTest.php`](../../tests/Unit/ExampleTest.php), [`tests/Feature/ExampleTest.php`](../../tests/Feature/ExampleTest.php) | **P3** | Auto-generated stubs with no real coverage | Phase 5 |
| 8 | Raw SQL | [`app/Services/DashboardStatsService.php`](../../app/Services/DashboardStatsService.php), [`app/Http/Controllers/Api/SupervisorAgentsController.php`](../../app/Http/Controllers/Api/SupervisorAgentsController.php) | **P3** | 8 `DB::raw(` calls; safe but untested | Phase 4 (review only) |
| 9 | Validation | 51 controllers, many API endpoints | **P2** | No `FormRequest` classes; validation inline. Acceptable but inconsistent | Phase 4 (top 5 endpoints) |
| 10 | Style | 283 PHP files, 163 style issues | **P3** | `vendor/bin/pint` flagged 163 files, mostly tests and old migrations | Phase 4 (single commit) |
| 11 | Security | `league/commonmark` 2.x | **P3** | 2 medium CVEs (embed allowed_domains bypass, DisallowedRawHtml whitespace bypass). Transitive via Laravel | Phase 6 |
| 12 | UI | [`resources/views/admin/forms.blade.php`](../../resources/views/admin/forms.blade.php) line 71 | **P3** | Inline `onclick` on row Edit button hard to reason about; works but style inconsistent with Alpine pattern used elsewhere | Phase 4 (optional) |

## Soft-nav safety review (detailed)

Files containing inline `<script>` that touch DOM inside `#main-layout`:

| File | Pattern | Status |
|------|---------|--------|
| [`resources/views/dashboard.blade.php`](../../resources/views/dashboard.blade.php) | `@push('scripts')` + `getElementById('chart-activity')` | Re-executes after soft-nav, but does **not destroy** the previous chart. Leak. **Fix in Phase 2.** |
| [`resources/views/admin/dashboard.blade.php`](../../resources/views/admin/dashboard.blade.php) | Same | Same leak. **Fix in Phase 2.** |
| [`resources/views/admin/supervisor.blade.php`](../../resources/views/admin/supervisor.blade.php) | Alpine component (`window.supervisorDashboard`) + `.innerHTML = ''` before new chart | **Safe** (clears container before re-render). No change needed. |
| [`resources/views/admin/telephony-monitor.blade.php`](../../resources/views/admin/telephony-monitor.blade.php) | Alpine component (`window.telephonyMonitor`) | **Safe** - re-mounts via Alpine initTree. |
| [`resources/views/admin/configuration.blade.php`](../../resources/views/admin/configuration.blade.php) | Alpine component (`window.telephonyDiagnostics`) | **Safe**. |
| [`resources/views/reports/index.blade.php`](../../resources/views/reports/index.blade.php) | Alpine component (`window.telephonyReports`) | **Safe** (likely). |
| [`resources/views/agent/index.blade.php`](../../resources/views/agent/index.blade.php) | Alpine component (`window.agentScreen`) | **Safe** - already re-initialized on soft-nav. |
| [`resources/views/layouts/app.blade.php`](../../resources/views/layouts/app.blade.php) | Theme toggle, logout, telephony init | Theme fixed via delegation. Logout to be refactored (P2). |

## Dead code scan

- Grep for `TODO|FIXME|XXX|HACK|@deprecated` under `app/` and `resources/js/`: **0 real matches** (two `XXX` are phone-mask placeholders).
- No `dd()`, `dump()`, `var_dump()` in non-test source.
- One `console.log(` - inside [`resources/js/telephony-logger.js`](../../resources/js/telephony-logger.js), intentional.
- No `markTestSkipped`, `@ts-ignore`, `eslint-disable`, or `@phpstan-ignore` anywhere.
- All 11 JS modules reachable from `app.js` or `login-page.js`. No orphans.

The codebase is notably clean of residue. Most improvement areas are behavioral (soft-nav), not stylistic.

## Deferred (intentionally out of scope)

- **Asterisk-side config** (PJSIP endpoints, SRTP, ViciPhone URL): requires server access, not a repo change.
- **PHPStan / Larastan adoption**: revisit after tests are green.
- **Playwright / Cypress**: manual repro steps documented per fix is enough for now.
