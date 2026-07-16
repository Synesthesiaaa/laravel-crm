# Daily Campaign Report Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a campaign-aware daily and month-to-date agent report to the authenticated dashboard with the existing system theme.

**Architecture:** `DashboardStatsService` resolves the selected campaign's active form tables and aggregates counts/amounts for today and the current month. `DashboardController` passes the single report payload to the existing Blade dashboard, which renders four responsive table views with dynamic form columns and existing CSS variables.

**Tech Stack:** Laravel 12, PHP 8.5, MySQL, Blade, Tailwind utility classes, PHPUnit.

## Global Constraints

- Use only active, allow-listed campaign form tables and existing schema metadata.
- Keep the report campaign-aware; never hard-code or display the “MPI Cards” label.
- Do not add dependencies, migrations, or routes.
- Use existing dashboard theme classes/variables and empty-state patterns.
- Add/update PHPUnit coverage and run Pint on modified PHP files.

---

### Task 1: Add report aggregation to the dashboard service

**Files:**
- Modify: `app/Services/DashboardStatsService.php`
- Test: `tests/Unit/Services/DashboardStatsServiceTest.php`

**Interfaces:**
- Consumes: `CampaignRepository::getCampaignsWithForms()`, `FormField` metadata, existing form table schema.
- Produces: `getDailyCampaignReport(string $campaignCode, Carbon $businessDate): array` with `forms`, `daily`, `month_to_date`, and `totals` keys.

- [ ] **Step 1: Write failing unit tests** for a campaign with two forms and two agents, asserting daily per-form counts/amounts, MTD counts/amounts, totals, and safe empty output when no forms are valid.
- [ ] **Step 2: Run the focused service tests** with `php artisan test --compact tests/Unit/Services/DashboardStatsServiceTest.php --filter=DailyCampaignReport` and confirm the new tests fail before the method exists.
- [ ] **Step 3: Implement `getDailyCampaignReport`** by resolving the selected campaign forms, validating table/column names with `Schema`, querying only `date`, `agent`, and numeric fields, and merging rows by agent for today and the first day of the current month. Preserve a stable form list and zero values for missing form data.
- [ ] **Step 4: Add a cache key scoped by campaign/date** and clear it from `invalidate()` alongside the existing dashboard cache entries.
- [ ] **Step 5: Re-run the focused unit tests** and confirm the aggregation and empty-state cases pass.

### Task 2: Wire and render the dashboard report

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/views/dashboard.blade.php`
- Test: `tests/Feature/DashboardSalesRangeTest.php`

**Interfaces:**
- Consumes: `DashboardStatsService::getDailyCampaignReport()` output.
- Produces: A dashboard section with daily amounts, daily counts, month-to-date accounts, and month-to-date submitted amounts.

- [ ] **Step 1: Add a controller/view assertion** that the dashboard response contains the four report headings and does not contain “MPI Cards”.
- [ ] **Step 2: Run the focused feature test** with `php artisan test --compact tests/Feature/DashboardSalesRangeTest.php --filter=DailyCampaignReport` and confirm it fails before wiring the view.
- [ ] **Step 3: Call the service once in `DashboardController::index`** using the active campaign and current application date, then pass the result as `dailyCampaignReport`.
- [ ] **Step 4: Add the Blade report section** below the existing KPI/leaderboard content. Use a two-column responsive grid, `md-card`, `md-table-wrap`, `tabular-nums`, and `var(--color-*)` classes. Build columns from `dailyCampaignReport['forms']`; display dashes for missing numeric amounts and zero totals for counts.
- [ ] **Step 5: Re-run the focused feature test** and confirm dynamic headings, campaign scoping, and no legacy label pass.

### Task 3: Format and verify the change

**Files:**
- Modify: `app/Services/DashboardStatsService.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `tests/Unit/Services/DashboardStatsServiceTest.php`
- Modify: `tests/Feature/DashboardSalesRangeTest.php`

- [ ] **Step 1: Run Pint** with `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 2: Run the focused unit and feature tests** with `php artisan test --compact tests/Unit/Services/DashboardStatsServiceTest.php tests/Feature/DashboardSalesRangeTest.php`.
- [ ] **Step 3: Run the existing dashboard lifecycle tests** with `php artisan test --compact tests/Feature/ViewLifecycleRenderTest.php`.
- [ ] **Step 4: Start/use the local app and inspect `/dashboard` in Playwright** at desktop and narrow widths; verify the four tables, horizontal overflow/stacking, no console errors, and no “MPI Cards” text.
- [ ] **Step 5: Mark the OpenSpec tasks complete** after all checks pass.
