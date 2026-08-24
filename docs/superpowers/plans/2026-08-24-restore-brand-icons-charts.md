# Restore Brand, Icon, and Chart Visual System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restore the established magenta/charcoal business palette and make shared SVG icons and ApexCharts consistent, accessible, theme-aware, and responsive across the CRM.

**Architecture:** Keep the existing Blade SVG icon map and ApexCharts renderer. Restore CSS semantic tokens in place, extend the icon component backward-compatibly, and add a pure `window.crmChartTheme()` helper in the existing Vite entrypoint; page views continue to own chart data and instances while consuming shared visual defaults.

**Tech Stack:** Laravel 12 Blade, Tailwind CSS 4, Alpine.js 3, Vite, ApexCharts, PHPUnit 11, Node test runner, Playwright.

**Spec:** `openspec/changes/restore-brand-icons-charts/design.md` and `openspec/changes/restore-brand-icons-charts/specs/`

## Global Constraints

- Preserve existing chart data, filters, endpoints, business calculations, authorization, and soft-navigation lifecycle.
- Add no dependencies and keep ApexCharts dynamically loaded through `window.ApexChartsLoader`.
- Use semantic CSS variables for shared colors; do not introduce page-local brand hex values.
- Use SVG icons only, keep the existing outline family, and preserve icon-only control accessible names.
- Maintain 44px minimum shared control targets, visible focus states, reduced motion, and no document-level horizontal overflow.
- Do not stage or modify the pre-existing `.gitignore` change.

---

### Task 1: Restore the established brand tokens

**Files:**
- Modify: `resources/css/app.css`
- Test: `tests/Feature/ViewLifecycleRenderTest.php`

**Interfaces:**
- Consumes: existing shared semantic CSS token names and responsive shell rules.
- Produces: the pre-refresh `--color-primary: #e91e8c` contract and matching charcoal/gray surfaces for all existing consumers.

- [ ] **Step 1: Write the failing render assertion**

Add a Blade render assertion that the authenticated shell response includes the established primary token value in the loaded stylesheet/source contract or a stable token marker exposed by the shell.

- [ ] **Step 2: Run the focused test to verify it fails**

Run: `php artisan test --compact tests/Feature/ViewLifecycleRenderTest.php`

Expected: the new assertion fails because the current token is `#38bdf8`.

- [ ] **Step 3: Restore only the palette values**

Restore the pre-refresh values from `HEAD^` for primary, dark/light surfaces, text, borders, shadow glow, and header height. Keep later responsive additions such as `100dvh`, `overflow-x:hidden`, focus-visible rules, responsive content padding, and reduced-motion media rules.

- [ ] **Step 4: Run the focused test to verify it passes**

Run: `php artisan test --compact tests/Feature/ViewLifecycleRenderTest.php`

Expected: PASS, with the responsive shell landmark assertions still passing.

- [ ] **Step 5: Commit the token restoration**

```powershell
git add resources/css/app.css tests/Feature/ViewLifecycleRenderTest.php
git commit -m "fix: restore CRM brand palette"
```

### Task 2: Standardize the shared icon primitive

**Files:**
- Modify: `resources/views/components/icon.blade.php`
- Modify: `resources/views/components/stat-card.blade.php`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/ViewLifecycleRenderTest.php`

**Interfaces:**
- Consumes: existing `<x-icon name="..." class="..." />` call sites.
- Produces: backward-compatible optional icon attributes for size/stroke/label semantics and consistent shared icon usage.

- [ ] **Step 1: Add failing component assertions**

Render an authenticated shell and assert the shared icons remain SVGs with `aria-hidden="true"` when decorative, while a representative icon-only button has an accessible `aria-label`; add a stat-card render assertion for its icon wrapper/state class.

- [ ] **Step 2: Run the focused test to verify it fails**

Run: `php artisan test --compact tests/Feature/ViewLifecycleRenderTest.php`

Expected: the new icon contract assertion fails before the component changes.

- [ ] **Step 3: Implement backward-compatible icon options**

Update the Blade component to accept optional `$size`, `$strokeWidth`, and `$label` values without changing existing callers. Use `$label` to remove decorative-only semantics when a meaningful standalone icon is intentionally rendered, keep `aria-hidden="true"` by default, and preserve Tailwind class merging.

- [ ] **Step 4: Apply consistent shared icon treatment**

Use semantic icon sizing/color classes in the shared header, sidebar, and stat-card surfaces. Keep button labels and state attributes on the containing control; do not rely on icon color alone.

- [ ] **Step 5: Run the focused test to verify it passes**

Run: `php artisan test --compact tests/Feature/ViewLifecycleRenderTest.php`

Expected: PASS with no changed route visibility or navigation behavior.

- [ ] **Step 6: Commit the icon work**

```powershell
git add resources/views/components/icon.blade.php resources/views/components/stat-card.blade.php resources/views/layouts/app.blade.php resources/views/layouts/sidebar.blade.php tests/Feature/ViewLifecycleRenderTest.php
git commit -m "feat: standardize CRM icon presentation"
```

### Task 3: Add the shared ApexCharts theme contract

**Files:**
- Modify: `resources/js/app.js`
- Modify: `resources/css/app.css`
- Test: `tests/JavaScript/chart-theme.test.js`

**Interfaces:**
- Consumes: `getComputedStyle(document.documentElement)` CSS variables and `prefers-reduced-motion` state.
- Produces: `window.crmChartTheme(overrides = {})` returning ApexCharts option fragments with `colors`, `chart`, `grid`, `xaxis`, `yaxis`, `tooltip`, `legend`, and `responsive` keys.

- [ ] **Step 1: Write the failing Node test**

Create a Node test that reads the shared helper contract from a small extracted utility or browser-safe module and asserts dark/light semantic colors, font family, and disabled animation when reduced motion is requested.

- [ ] **Step 2: Run it to verify it fails**

Run: `node --test tests/JavaScript/chart-theme.test.js`

Expected: FAIL because the shared chart theme contract does not yet exist.

- [ ] **Step 3: Implement the token-driven helper**

Add a pure helper in `resources/js/app.js` (or an imported utility if the existing module shape requires it) that reads CSS variables, maps primary/status/neutral roles, sets ApexCharts typography/grid/tooltip defaults, uses responsive height rules, and sets `animations.enabled` to false when reduced motion is preferred. Allow page-level series-specific overrides.

- [ ] **Step 4: Add stable chart container styles**

Update `.chart-container` and a shared chart host class with `min-width:0`, bounded overflow, reserved minimum height, responsive padding, readable title hierarchy, and reduced-motion-safe transitions.

- [ ] **Step 5: Run the Node test to verify it passes**

Run: `node --test tests/JavaScript/chart-theme.test.js`

Expected: PASS for dark/light/reduced-motion cases.

- [ ] **Step 6: Commit the shared chart contract**

```powershell
git add resources/js/app.js resources/css/app.css tests/JavaScript/chart-theme.test.js
git commit -m "feat: add shared ApexCharts theme contract"
```

### Task 4: Migrate chart-bearing pages

**Files:**
- Modify: `resources/views/dashboard.blade.php`
- Modify: `resources/views/admin/dashboard.blade.php`
- Modify: `resources/views/reports/index.blade.php`
- Modify: `resources/views/admin/supervisor.blade.php`
- Test: existing related feature/render tests as needed

**Interfaces:**
- Consumes: `window.crmChartTheme()` and existing page data/configuration.
- Produces: themed chart instances with unchanged series labels, values, filters, and lifecycle cleanup.

- [ ] **Step 1: Inventory each page renderer before editing**

Record each chart element, series meaning, existing color role, destroy hook, and render trigger. Do not change data transformation code.

- [ ] **Step 2: Migrate Dashboard charts**

Spread the shared theme into daily/weekly/monthly activity chart options and keep existing `destroyChart`/soft-navigation guards.

- [ ] **Step 3: Migrate Admin Dashboard charts**

Apply the shared theme to submission activity and top-agent charts, preserving existing series names, categories, and empty-state behavior.

- [ ] **Step 4: Migrate Reports charts**

Apply shared axes/tooltips/legend options and retain explicit semantic status/disposition colors where the data meaning requires them.

- [ ] **Step 5: Migrate Supervisor charts**

Apply the shared theme to performance, hourly, and realtime wallboard charts while preserving live refresh and tab-specific mounting.

- [ ] **Step 6: Run focused render/JavaScript checks**

Run: `php artisan test --compact tests/Feature/ViewLifecycleRenderTest.php tests/Feature/Admin/ActivityLogTest.php` and `node --test tests/JavaScript/chart-theme.test.js tests/JavaScript/activity-log.test.js`

Expected: PASS with no chart-specific lifecycle regression.

- [ ] **Step 7: Commit the page migrations**

```powershell
git add resources/views/dashboard.blade.php resources/views/admin/dashboard.blade.php resources/views/reports/index.blade.php resources/views/admin/supervisor.blade.php
git commit -m "feat: unify dashboard chart visuals"
```

### Task 5: Verify and hand off

**Files:**
- Modify: `openspec/changes/restore-brand-icons-charts/tasks.md`
- Verify: all changed source/test files and browser pages

**Interfaces:**
- Consumes: completed token, icon, chart-helper, and page migration tasks.
- Produces: verified implementation and archived OpenSpec change.

- [ ] **Step 1: Run the full quality checks**

Run:

```powershell
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
git diff --check
```

Expected: all commands exit successfully.

- [ ] **Step 2: Verify representative browser states**

Use Playwright on Dashboard, Admin Dashboard, Reports, and Supervisor at 375px and 1440px. Toggle light/dark themes, confirm chart SVGs render, confirm no document-level horizontal overflow, and check that console output contains no new errors beyond known telephony/Reverb environment failures.

- [ ] **Step 3: Synchronize and archive OpenSpec**

Mark all completed tasks, run the OpenSpec sync/archive workflow, and verify the active change is removed from `openspec list` while the main specs contain the new capabilities.

- [ ] **Step 4: Review and commit final state**

Inspect `git status --short`, confirm unrelated `.gitignore` changes remain unstaged, then commit the final implementation with:

```powershell
git add openspec docs/superpowers resources/css/app.css resources/js/app.js resources/views tests
git commit -m "feat: restore brand icons and chart system"
```
