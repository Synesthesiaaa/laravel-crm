# Phase 2 - UI stability fixes

All fixes land on the `33-attendance-status` branch as separate logical changes. Repro steps use the existing CRM dev loop (`composer dev` or `npm run build && php artisan serve`).

## 2.1 Theme toggle survives soft-nav (already done)

Implemented previously. Summary: vanilla script that bound `addEventListener` once on a node inside `#main-layout` is replaced by **event delegation on `document`** and a re-sync on the `soft-navigate` window event.

Files: [`resources/views/layouts/app.blade.php`](../../resources/views/layouts/app.blade.php).

Repro:

1. Log in, open any page with the header.
2. Toggle theme - confirm it works.
3. Click a sidebar link (soft nav).
4. Toggle theme - should still work without refresh.

## 2.2 ApexCharts leak on repeated soft-nav

**Problem.** [`resources/views/dashboard.blade.php`](../../resources/views/dashboard.blade.php) and [`resources/views/admin/dashboard.blade.php`](../../resources/views/admin/dashboard.blade.php) already sit inside `@push('scripts')`, so `soft-navigate.js` correctly re-executes them after each nav. But the previous chart instances were never destroyed, so every return to the dashboard stacked another `ApexCharts` object on top of the same `<div>` - listener leak + slowdown.

**Fix.** Track instances on `window.__crmDashboardCharts` / `window.__crmAdminDashboardCharts`, destroy all tracked instances before creating new ones. Also added element existence guards so the script no-ops cleanly if containers were removed.

Files changed:

- [`resources/views/dashboard.blade.php`](../../resources/views/dashboard.blade.php)
- [`resources/views/admin/dashboard.blade.php`](../../resources/views/admin/dashboard.blade.php)

Repro:

1. Open the dashboard. Charts render.
2. Navigate to any other page via sidebar.
3. Navigate back to dashboard. Chart should render once, tooltips should respond normally (not layered).
4. Repeat 5x. Browser memory / listener count should stay flat in DevTools.

## 2.3 Graceful logout extracted to `window.crmGracefulLogout`

**Problem.** The logout form in [`resources/views/layouts/app.blade.php`](../../resources/views/layouts/app.blade.php) contained a 15-line inline async IIFE inside `@submit.prevent`. Hard to read, impossible to unit-test, and the `document.getElementById('logout-form').submit()` call at the end could re-trigger Alpine's event handler on some browsers (double-submit risk).

**Fix.** Moved the flow to a named top-level function [`window.crmGracefulLogout`](../../resources/js/app.js) registered right before `Alpine.start()`. The form's `@submit.prevent` now just delegates. The final `form.submit()` uses `HTMLFormElement.prototype.submit.call(form)` to bypass the prevented handler and avoid re-entry.

Files changed:

- [`resources/js/app.js`](../../resources/js/app.js) (new `crmGracefulLogout`)
- [`resources/views/layouts/app.blade.php`](../../resources/views/layouts/app.blade.php) (form handler shrunk to one line)

Repro:

1. Start a call, then click Sign out. Confirm hangup + unregister happen before the form posts.
2. Sign out without any active call. Confirm no errors, clean redirect to login.
3. Sign out while on a page reached via soft nav. Confirm no double-submit and no "already sent headers" warning server-side.

## 2.4 Soft-nav safety rule published

New reference doc: [`docs/audit/ui-soft-nav-rules.md`](ui-soft-nav-rules.md). Explains when to use Alpine, when to use delegation, when to use `@push('scripts')`, and common anti-patterns. Link added to `docs/audit/README.md`.

## Not fixed in this phase (deferred)

- **Header dropdown "double-click"** - Could not reliably reproduce in isolation without a specific workflow. Suspect focus/trap race with Alpine `@click.outside`. Leaving as a watch-item. If it reappears, standardize on a `dropdown()` Alpine component + `@click.away` and test in isolation.
- **Admin forms inline `onclick`** in [`resources/views/admin/forms.blade.php`](../../resources/views/admin/forms.blade.php) line 71 - works, but style-inconsistent. Deferred to Phase 4 style sweep.

## Verification checklist

- `npm run build` succeeds.
- Manual flows above.
- `vendor/bin/pint --test` count is unchanged by these edits (no new style violations added).
