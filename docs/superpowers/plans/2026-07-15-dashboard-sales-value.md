# Dashboard Sales Value Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show the existing rolling Sales KPI count together with the total sale value for the same configured window.

**Architecture:** `DashboardStatsService::getKpisForCampaign` remains the source of KPI data and gains a `sales_amount` return key. Field-driven rows and disposition fallback rows each produce count and amount in the service, while the shared stat-card component gains an optional secondary display line used only by the dashboard Sales card.

**Tech Stack:** Laravel 12, PHP 8.5, PHPUnit 11, Blade, Tailwind CSS 4, Vite, Playwright.

## Global Constraints

- Preserve the existing rolling window configured by `dashboard.kpi_window_hours`.
- Preserve field-driven sales precedence and disposition/lead JSON fallback behavior.
- Preserve one-sale-per-submission semantics and ignore null, empty, malformed, and missing values.
- Do not add dependencies or database schema changes.
- Use two-decimal numeric formatting consistent with the existing leaderboard; do not invent currency conversion.

---

### Task 1: Add failing coverage for rolling sales amounts

**Files:**
- Modify: `tests/Unit/Services/DashboardStatsServiceTest.php`
- Modify: `tests/Feature/ViewLifecycleRenderTest.php`

**Interfaces:**
- Consumes: Existing `DashboardStatsService::getKpisForCampaign` return array and dashboard route rendering.
- Produces: Assertions requiring `sales_amount` and `Total value:` output.

- [ ] **Step 1: Extend marked-field KPI coverage**

In `test_get_kpis_counts_submissions_with_marked_amounts_once`, after the existing sales assertion, add:

```php
$this->assertSame(125.5, $kpis['sales_amount']);
```

- [ ] **Step 2: Extend fallback KPI coverage**

Add `lead_data_json => ['ezycash_amount' => 125.50]` to the in-window `SALE` fixture in `test_get_kpis_counts_calls_and_sales_inside_window`, then assert:

```php
$this->assertSame(125.5, $kpis['sales_amount']);
```

- [ ] **Step 3: Add the dashboard copy assertion**

In `test_dashboard_renders_soft_nav_chart_lifecycle_hooks`, add:

```php
$response->assertSee('Total value:', false);
```

- [ ] **Step 4: Run the focused tests and verify RED**

Run:

```bash
php artisan test --compact tests/Unit/Services/DashboardStatsServiceTest.php tests/Feature/ViewLifecycleRenderTest.php
```

Expected: the new `sales_amount` assertions and/or `Total value:` assertion fail because the service and component do not yet expose the new value.

### Task 2: Implement rolling sales amount aggregation

**Files:**
- Modify: `app/Services/DashboardStatsService.php`

**Interfaces:**
- Consumes: Existing `resolveMarkedSaleFields`, `sumMarkedSaleValues`, `sumSaleAmountFromLeadJson`, and configured KPI window.
- Produces: `getKpisForCampaign` return shape `array{calls: int, sales: int, sales_amount: float, top_agent: string|null, top_agent_calls: int}`.

- [ ] **Step 1: Expand the empty KPI contract**

Add `'sales_amount' => 0.0` to the empty result and update the method PHPDoc return shape.

- [ ] **Step 2: Replace count-only field-driven aggregation**

Change the rolling helper to return `array{count: int, amount: float}`. For each chunked row, call `sumMarkedSaleValues`; when non-null, increment the count and add the returned amount. Keep selecting only `id` plus marked columns and preserve the `created_at >= $since` filter.

- [ ] **Step 3: Aggregate fallback disposition amounts**

Initialize `$salesAmount = 0.0`. In the existing fallback sale-row query, add each row's amount with `$salesAmount += $this->sumSaleAmountFromLeadJson($row->lead_data_json);` while incrementing the existing count.

- [ ] **Step 4: Return a rounded amount**

Return `'sales_amount' => round($salesAmount, 2)` alongside the existing KPI keys. For field-driven mode, use the count and amount returned by the helper.

- [ ] **Step 5: Run service tests and verify GREEN**

Run:

```bash
php artisan test --compact tests/Unit/Services/DashboardStatsServiceTest.php
```

Expected: all service tests pass, including the new marked-field and fallback amount assertions.

### Task 3: Add the secondary stat-card metric

**Files:**
- Modify: `resources/views/components/stat-card.blade.php`
- Modify: `resources/css/app.css`
- Modify: `resources/views/dashboard.blade.php`

**Interfaces:**
- Consumes: Optional `secondary` string prop on `x-stat-card`.
- Produces: Existing stat cards unchanged; Sales card displays a formatted total-value line below its primary count.

- [ ] **Step 1: Add the optional component prop**

Add `'secondary' => null` to the component props and render it only when non-null, below `.stat-card-value`, using a dedicated `.stat-card-secondary` class.

- [ ] **Step 2: Style the secondary line**

Add a compact, muted style in `resources/css/app.css`:

```css
.stat-card-secondary { margin-top: .5rem; font-size: .75rem; color: var(--color-on-surface-dim); }
```

- [ ] **Step 3: Pass the dashboard total**

Change the Sales card to retain `number_format($kpis['sales'] ?? 0)` as `:value` and pass:

```blade
secondary="Total value: {{ number_format($kpis['sales_amount'] ?? 0, 2) }}"
```

- [ ] **Step 4: Run view test and build**

Run:

```bash
php artisan test --compact tests/Feature/ViewLifecycleRenderTest.php
npm run build
```

Expected: the view test passes and Vite completes successfully.

### Task 4: Final verification and OpenSpec completion

**Files:**
- Modify: `openspec/changes/dashboard-sales-value/tasks.md`

- [ ] **Step 1: Format PHP**

Run `vendor/bin/pint --dirty --format agent` and confirm it passes.

- [ ] **Step 2: Run focused regression tests**

Run:

```bash
php artisan test --compact tests/Unit/Services/DashboardStatsServiceTest.php tests/Feature/ViewLifecycleRenderTest.php
```

- [ ] **Step 3: Verify in Playwright**

Open the authenticated dashboard at desktop and mobile widths. Confirm the Sales card contains both the sales count and `Total value: 0.00`-formatted text, and inspect console messages for unrelated telephony errors.

- [ ] **Step 4: Validate and archive OpenSpec**

Run `openspec validate dashboard-sales-value --strict`, synchronize the delta into `openspec/specs/field-sale-attribution/spec.md`, then archive the change after all tasks are checked.
