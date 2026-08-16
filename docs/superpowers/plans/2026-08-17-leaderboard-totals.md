# Agent Leaderboard Totals Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a total sales-count and sale-amount footer to both dashboard agent leaderboard tables.

**Architecture:** Keep the existing sales aggregation and `$agentLeaderboard` data contract unchanged. Sum the rendered leaderboard rows once in the Blade view, then reuse those totals in semantic `<tfoot>` rows for the modal and visible leaderboard.

**Tech Stack:** Laravel 12, PHPUnit feature tests, Blade, Tailwind utility classes, Playwright MCP.

## Global Constraints

- Use the existing selected date/time range and marked sale-amount data; do not change sales aggregation.
- Preserve the existing ranking, per-agent values, empty state, routes, APIs, schema, and dependencies.
- Every production change must have a failing regression test observed before implementation.
- Run `vendor/bin/pint --dirty --format agent` for modified PHP files before finalizing.

---

### Task 1: Add the failing leaderboard-total regression test

**Files:**
- Modify: `tests/Feature/DashboardSalesRangeTest.php` in `test_dashboard_renders_selected_range_agent_leaderboard_amounts`

**Interfaces:**
- Consumes: Existing selected-range test data for Alice (`100.00`) and Bob (`250.00`).
- Produces: A regression expectation that the rendered dashboard contains two `Total` footer rows, each showing `2` sales and `350.00` total amount.

- [x] **Step 1: Add assertions after the existing leaderboard assertions**

```php
$content = $response->getContent();

$this->assertSame(2, substr_count($content, '>Total</td>'));
$this->assertSame(2, substr_count($content, '>2</td>'));
$this->assertSame(2, substr_count($content, '>350.00</td>'));
```

- [x] **Step 2: Run the focused test and verify it fails for the missing footer**

Run:

```powershell
php artisan test --compact tests/Feature/DashboardSalesRangeTest.php --filter=test_dashboard_renders_selected_range_agent_leaderboard_amounts
```

Expected: the test fails because the dashboard currently has zero `Total` cells and no aggregate `350.00` footer values.

### Task 2: Render aggregate totals in both leaderboard tables

**Files:**
- Modify: `resources/views/dashboard.blade.php` near the existing leaderboard view setup and both leaderboard `<table>` blocks

**Interfaces:**
- Consumes: `$agentLeaderboard` rows shaped as `agent`, `sales_count`, and `sales_amount`.
- Produces: `$leaderboardTotalSales` and `$leaderboardTotalAmount` reused by both leaderboard footers.

- [x] **Step 1: Calculate totals from the existing leaderboard rows**

Add to the view's initial `@php` block:

```php
$leaderboardTotalSales = collect($agentLeaderboard ?? [])->sum('sales_count');
$leaderboardTotalAmount = collect($agentLeaderboard ?? [])->sum('sales_amount');
```

- [x] **Step 2: Add the modal footer after its existing `<tbody>`**

```blade
<tfoot>
    <tr>
        <td colspan="2" class="font-semibold text-[var(--color-on-surface)]">Total</td>
        <td class="text-right font-semibold tabular-nums">{{ number_format($leaderboardTotalSales) }}</td>
        <td class="text-right font-semibold tabular-nums">{{ number_format($leaderboardTotalAmount, 2) }}</td>
    </tr>
</tfoot>
```

- [x] **Step 3: Add the same footer after the visible leaderboard's existing `<tbody>`**

Use the same four-column footer markup so both surfaces present identical totals and formatting.

- [x] **Step 4: Run the focused test and verify it passes**

Run:

```powershell
php artisan test --compact tests/Feature/DashboardSalesRangeTest.php --filter=test_dashboard_renders_selected_range_agent_leaderboard_amounts
```

Expected: PASS, including the two modal/page footer rows with `2` and `350.00`.

### Task 3: Format and validate the completed change

**Files:**
- Verify: `resources/views/dashboard.blade.php`
- Verify: `tests/Feature/DashboardSalesRangeTest.php`
- Verify: OpenSpec artifacts under `openspec/changes/add-leaderboard-totals/`

**Interfaces:**
- Consumes: The implemented Blade footer and regression test.
- Produces: Formatted, browser-verified dashboard behavior and completed OpenSpec task status.

- [x] **Step 1: Run Pint and rerun the focused feature test**

Run:

```powershell
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/DashboardSalesRangeTest.php
```

Expected: Pint reports no remaining formatting changes and the dashboard sales-range feature file passes.

- [ ] **Step 2: Verify the dashboard in Playwright**

Open the local dashboard with the authenticated test/development session, inspect the visible leaderboard and open the Top Agent card/modal. Confirm both tables show `Total`, `2`, and `350.00`, and inspect browser console messages for errors.

- [x] **Step 3: Update OpenSpec task checkboxes and sync the modified capability spec**

Mark completed tasks in `openspec/changes/add-leaderboard-totals/tasks.md`, run `openspec status --change "add-leaderboard-totals" --json`, and sync the `field-sale-attribution` delta into `openspec/specs/field-sale-attribution/spec.md` before archive.
