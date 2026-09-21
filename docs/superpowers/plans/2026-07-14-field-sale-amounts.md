# Field Sale Amounts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task with verification checkpoints.

**Goal:** Let administrators mark numeric form fields as sale amounts and have qualifying submissions count as one sale and contribute their marked values to dashboard totals.

**Architecture:** Persist `is_sale_amount` on `form_fields`. Field Logic owns configuration and enforces numeric-only marking. `DashboardStatsService` resolves marked fields against registered form tables, aggregates one sale per qualifying row, and uses the existing disposition logic only when no marked fields exist for the campaign.

**Tech Stack:** Laravel 12, PHP 8.5, MySQL/SQLite test database, Blade, Alpine.js, PHPUnit 11, Laravel Pint.

## Global Constraints

- Keep the change additive and compatible with existing campaigns.
- Do not add dependencies or public API routes.
- Use PHPUnit feature/unit tests and run `vendor/bin/pint --dirty --format agent` for modified PHP files.
- A marked field must have `field_type === 'number'`; a submission row counts at most once.

---

### Task 1: Persist sale-amount field configuration

**Files:**
- Create: `database/migrations/2026_07_14_000001_add_sale_amount_to_form_fields_table.php`
- Modify: `app/Models/FormField.php`
- Test: `tests/Feature/Admin/FieldLogicAdminTest.php`

**Interfaces:**
- Produces a boolean `FormField::$is_sale_amount` cast and fillable field for later controller and dashboard code.

- [ ] **Step 1: Write the failing persistence test**

Add a PHPUnit feature test that posts a numeric Field Logic field with `is_sale_amount => '1'`, then asserts the saved row has `is_sale_amount === true`. Add a second assertion path that submits a text field with the flag and expects the persisted value to be false.

- [ ] **Step 2: Run the focused test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/FieldLogicAdminTest.php --filter=sale_amount`

Expected: FAIL because the schema, request handling, and controller do not yet persist the new flag.

- [ ] **Step 3: Add the additive migration and model metadata**

Add `$table->boolean('is_sale_amount')->default(false)->after('is_required');`, add `is_sale_amount` to `$fillable`, and add `'is_sale_amount' => 'boolean'` to `casts()`.

- [ ] **Step 4: Run the focused persistence test**

Run: `php artisan test --compact tests/Feature/Admin/FieldLogicAdminTest.php --filter=sale_amount`

Expected: The test still fails only until the request/controller/UI path is completed in Task 2; the migration must run successfully.

### Task 2: Add Field Logic controls and enforce numeric-only marking

**Files:**
- Modify: `app/Http/Requests/Admin/StoreFieldLogicRequest.php`
- Modify: `app/Http/Requests/Admin/UpdateFieldLogicRequest.php`
- Modify: `app/Http/Controllers/Admin/FieldLogicController.php`
- Modify: `resources/views/admin/field_logic.blade.php`
- Modify: `resources/views/admin/field_logic_edit.blade.php`
- Test: `tests/Feature/Admin/FieldLogicAdminTest.php`

**Interfaces:**
- Consumes: `FormField::$is_sale_amount` from Task 1.
- Produces: `is_sale_amount` checkbox controls and a visible sale marker in the Field Logic list.

- [ ] **Step 1: Add failing render and update assertions**

Extend the existing edit/index tests to assert the `Is sale amount` label is rendered, and add an update test that marks a numeric field and verifies its fresh model is true. Assert a non-numeric update stores false.

- [ ] **Step 2: Run the focused admin test file**

Run: `php artisan test --compact tests/Feature/Admin/FieldLogicAdminTest.php`

Expected: FAIL on missing validation/field persistence or missing rendered label.

- [ ] **Step 3: Implement request, controller, and Blade changes**

Validate `is_sale_amount` as nullable boolean. In store/update, persist `$request->boolean('is_sale_amount') && $effectiveType === 'number'`. Add Alpine state so the checkbox is visible/enabled for `number`, preserve old/model state, and add a `Sale amount` table column.

- [ ] **Step 4: Run the focused admin tests**

Run: `php artisan test --compact tests/Feature/Admin/FieldLogicAdminTest.php`

Expected: PASS with no validation errors.

### Task 3: Add failing dashboard aggregation tests

**Files:**
- Modify: `tests/Unit/Services/DashboardStatsServiceTest.php`

**Interfaces:**
- Produces executable expectations for field-driven sales count, amounts, date windows, agent grouping, malformed values, missing columns, and fallback behavior.

- [ ] **Step 1: Write the failing service tests**

Create marked `FormField` rows and physical form-table rows for one agent with one marked value, one row with two marked values, and rows with empty/unmarked values. Assert `getKpisForCampaign()` counts qualifying rows once and `getAgentLeaderboard()` sums only marked values. Add a fallback test that has no marked fields and retains the existing `SALE` disposition amount behavior.

- [ ] **Step 2: Run the focused service tests**

Run: `php artisan test --compact tests/Unit/Services/DashboardStatsServiceTest.php --filter=marked|sale_amount|fallback`

Expected: FAIL because dashboard methods still read disposition records only.

### Task 4: Implement field-driven dashboard sales

**Files:**
- Modify: `app/Services/DashboardStatsService.php`
- Modify: `app/Http/Controllers/Admin/FieldLogicController.php`
- Modify: `tests/Unit/Services/DashboardStatsServiceTest.php`

**Interfaces:**
- Consumes: `FormField` sale metadata and registered campaign form/table configuration.
- Produces: existing KPI and leaderboard return shapes with field-driven `sales`, `sales_count`, and `sales_amount` values.

- [ ] **Step 1: Implement marked-field resolution and row parsing**

Resolve active marked numeric fields per campaign/form, intersect their names with actual table columns, and add private helpers that identify numeric non-empty values and sum them. Query `created_at` for the rolling KPI and `date`/`agent` for the monthly leaderboard. Treat each qualifying row as one sale.

- [ ] **Step 2: Implement fallback and cache invalidation**

Use field-driven totals when marked fields exist; otherwise retain the current sale-disposition and lead JSON fallback. Invalidate the affected campaign’s dashboard cache after Field Logic store, update, and delete operations.

- [ ] **Step 3: Run the focused service and admin tests**

Run: `php artisan test --compact tests/Unit/Services/DashboardStatsServiceTest.php tests/Feature/Admin/FieldLogicAdminTest.php`

Expected: PASS with existing disposition tests and new field-driven tests.

### Task 5: Format, verify, and browser-check the feature

**Files:**
- No new source files; update OpenSpec task checkboxes after verification.

- [ ] **Step 1: Format modified PHP files**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 2: Run the focused regression suite**

Run: `php artisan test --compact tests/Unit/Services/DashboardStatsServiceTest.php tests/Feature/Admin/FieldLogicAdminTest.php`

- [ ] **Step 3: Build frontend assets if required and inspect routes**

Run: `npm run build` and `php artisan route:list --except-vendor --path=admin/field-logic`.

- [ ] **Step 4: Verify the rendered flow in Browser**

Flow: admin login/session → Field Logic → edit numeric field → see the sale-amount checkbox → save → dashboard → confirm the dashboard renders without console errors and displays updated sales data for seeded/test data.

- [ ] **Step 5: Update OpenSpec task checkboxes**

Mark the corresponding `openspec/changes/field-sale-amounts/tasks.md` items complete only after their commands and browser checks pass.
