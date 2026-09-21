# Leaderboard Sales Amount Ranking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every dashboard agent leaderboard rank by total sales amount first, with sales count and agent name as deterministic tie-breakers.

**Architecture:** Preserve the existing aggregation and return shapes in `DashboardStatsService`. Change the sort comparator in both the selected-range leaderboard and the legacy month-to-date leaderboard, then align dashboard copy and add focused regression coverage.

**Tech Stack:** Laravel 12, PHP 8.5, PHPUnit 11, Blade, Laravel Pint.

## Global Constraints

- Rank by `sales_amount` descending, then `sales_count` descending, then `agent` ascending.
- Do not change aggregation, date ranges, cache keys, limits, return shapes, or displayed columns.
- Use PHPUnit classes and run the smallest affected tests.
- Run `vendor/bin/pint --dirty --format agent` for modified PHP files.

---

### Task 1: Add regression coverage for amount-first ranking

**Files:**
- Modify: `tests/Feature/DashboardSalesRangeTest.php` (selected-range leaderboard ordering test)
- Modify: `tests/Unit/Services/DashboardStatsServiceTest.php` (legacy leaderboard ordering test)

**Interfaces:**
- Consumes: `DashboardStatsService::getSalesKpisForCampaign()` and `DashboardStatsService::getAgentLeaderboard()`.
- Produces: Failing assertions that lock the amount-first, count-second, name-third order.

- [ ] **Step 1: Update the selected-range fixture and expected order**

  In `test_sales_kpis_return_a_selected_range_leaderboard_sorted_by_sales_then_amount_then_name`, keep Alice at two sales totaling `125.00`, keep Bob at one sale totaling `200.00`, and change Carl's sale to `250.00`. Assert the order is Carl, Bob, Alice, proving amount outranks count. Add equal-amount rows for `Aaron` (two sales totaling `300.00`) and `Zed` (one sale totaling `300.00`) and assert Aaron precedes Zed because count breaks the amount tie. Add `Amy` (one sale totaling `300.00`) and assert Amy precedes Zed because the final tie is alphabetical.

  Use the existing `registerSalesForm()` and `insertSale()` helpers; keep the selected range at `06:00` through `18:00` so all new rows are included.

- [ ] **Step 2: Run the feature test to verify it fails against count-first ordering**

  Run:

  ```text
  php artisan test --compact tests/Feature/DashboardSalesRangeTest.php --filter=test_sales_kpis_return_a_selected_range_leaderboard_sorted_by_sales_then_amount_then_name
  ```

  Expected: FAIL because the current implementation puts Alice first by sales count and does not apply amount-first ordering.

- [ ] **Step 3: Update the legacy leaderboard fixture and expected order**

  In `test_get_agent_leaderboard_sorts_by_submissions_then_sales`, retain the existing submission/sale setup and add enough sale disposition records to make Bob's total amount exceed Alice's while Bob has fewer submissions and sales. Add equal-amount agents whose sales counts differ and whose counts match but names differ. Change the assertions to verify total amount is the primary order, then sales count, then name; retain assertions for the existing row fields (`submissions`, `sales_count`, and `sales_amount`). Rename the test to describe amount-first ordering.

- [ ] **Step 4: Run the unit test to verify it fails against the old comparator**

  Run:

  ```text
  php artisan test --compact tests/Unit/Services/DashboardStatsServiceTest.php --filter=test_get_agent_leaderboard_sorts_by_sales_amount_then_sales_count_then_name
  ```

  Expected: FAIL until both service comparators are changed.

### Task 2: Implement amount-first comparators and dashboard copy

**Files:**
- Modify: `app/Services/DashboardStatsService.php:209-220,956-968` (both leaderboard sort callbacks)
- Modify: `resources/views/dashboard.blade.php:167,218` (ranking descriptions)

**Interfaces:**
- Consumes: The existing `$ranked` and `$leaderboard` rows with `sales_amount`, `sales_count`, `submissions`, and `agent` fields.
- Produces: Identically shaped arrays ordered by amount, count, and name.

- [ ] **Step 1: Change the legacy comparator**

  In `getAgentLeaderboard()`, replace the current `submissions`-first comparison with:

  ```php
  usort($ranked, static function (array $a, array $b): int {
      if ($a['sales_amount'] != $b['sales_amount']) {
          return $b['sales_amount'] <=> $a['sales_amount'];
      }
      if ($a['sales_count'] !== $b['sales_count']) {
          return $b['sales_count'] <=> $a['sales_count'];
      }

      return strcmp($a['agent'], $b['agent']);
  });
  ```

  Leave the `submissions` value in each row unchanged.

- [ ] **Step 2: Change the selected-range comparator**

  In `buildSalesLeaderboard()`, reorder the existing comparisons to amount descending, count descending, and agent name ascending:

  ```php
  usort($leaderboard, static function (array $a, array $b): int {
      if ($a['sales_amount'] != $b['sales_amount']) {
          return $b['sales_amount'] <=> $a['sales_amount'];
      }
      if ($a['sales_count'] !== $b['sales_count']) {
          return $b['sales_count'] <=> $a['sales_count'];
      }

      return strcmp($a['agent'], $b['agent']);
  });
  ```

- [ ] **Step 3: Update dashboard ranking descriptions**

  Change both occurrences of “Ranked by qualifying form sales, then total marked-form sale amount.” to “Ranked by total sale amount, then qualifying sales count and agent name.” Keep table columns and values unchanged.

- [ ] **Step 4: Format modified PHP files**

  Run:

  ```text
  vendor/bin/pint --dirty --format agent
  ```

  Expected: Pint completes successfully and leaves the edited PHP files formatted.

### Task 3: Run focused validation

**Files:**
- Test: `tests/Feature/DashboardSalesRangeTest.php`
- Test: `tests/Unit/Services/DashboardStatsServiceTest.php`

**Interfaces:**
- Consumes: The updated service comparators, tests, and dashboard view.
- Produces: Passing regression tests and a clean working tree aside from intentional implementation changes.

- [ ] **Step 1: Run the selected-range regression test**

  ```text
  php artisan test --compact tests/Feature/DashboardSalesRangeTest.php --filter=test_sales_kpis_return_a_selected_range_leaderboard_sorted_by_sales_amount_then_sales_count_then_name
  ```

  Expected: PASS.

- [ ] **Step 2: Run the legacy leaderboard regression test**

  ```text
  php artisan test --compact tests/Unit/Services/DashboardStatsServiceTest.php --filter=test_get_agent_leaderboard_sorts_by_sales_amount_then_sales_count_then_name
  ```

  Expected: PASS.

- [ ] **Step 3: Run the full affected test files**

  ```text
  php artisan test --compact tests/Feature/DashboardSalesRangeTest.php tests/Unit/Services/DashboardStatsServiceTest.php
  ```

  Expected: PASS with no regressions in dashboard sales aggregation or legacy KPI behavior.

- [ ] **Step 4: Inspect the diff and status**

  ```text
  git diff --check
  git status --short
  ```

  Expected: no whitespace errors; only the intended service, view, and test changes remain (the design/plan docs are already committed).
