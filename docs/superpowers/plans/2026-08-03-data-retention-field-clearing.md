# Data Retention Field Clearing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend per-form data retention so Super Admins can either delete complete records or clear selected fields using type-safe values while preserving records and unselected fields.

**Architecture:** Extend the existing `DataRetentionPolicy` row with a deletion mode and JSON field list. The request/controller validate fields against the chosen active form and its storage schema; `DataRetentionService` keeps the current delete branch and adds a validated bulk-update branch. The existing Configuration tab remains the entry point and reloads selected form metadata on form changes.

**Tech Stack:** Laravel 12.53, PHP 8.5, Eloquent, MySQL runtime, SQLite PHPUnit test database, Blade, Alpine.js, PHPUnit 11, Laravel Pint, OpenSpec.

## Global Constraints

- Only Super Admins may create, update, deactivate, or execute retention policy configuration through the admin routes.
- Existing whole-record policies must remain whole-record policies through the migration default `whole_record`.
- Table and column identifiers must come from registered form metadata and live schema checks, never arbitrary request input.
- Selected-field mode must preserve matching records and every unselected field.
- Type-safe clearing uses `NULL` for nullable columns, `''` for non-null strings/text, and `0` for non-null numeric/boolean columns; unsupported non-null date/time or other types skip before mutation.
- Every production change is preceded by a failing PHPUnit test and followed by focused verification.
- Run `vendor/bin/pint --dirty --format agent` before finalizing PHP changes.

---

### Task 1: Add policy mode persistence

**Files:**
- Create: `database/migrations/2026_08_03_140000_add_deletion_mode_and_selected_fields_to_data_retention_policies_table.php`
- Modify: `app/Models/DataRetentionPolicy.php`
- Test: `tests/Unit/Models/DataRetentionPolicyTest.php`

**Interfaces:**
- Consumes: Existing `data_retention_policies` schema and model relationship.
- Produces: `DataRetentionPolicy::$fillable` entries for `deletion_mode` and `selected_fields`; casts for `selected_fields`; backward-compatible `whole_record` default.

- [ ] **Step 1: Write the failing model test**

Add a test that creates a policy with `deletion_mode: 'selected_fields'` and `selected_fields: ['cardholder_name', 'account_number']`, then asserts the model returns the mode string and an array after refresh. Add an assertion that a policy created without the new attributes defaults to `whole_record` and has an empty/null selection.

- [ ] **Step 2: Run the model test to verify it fails**

Run: `php artisan test --compact tests/Unit/Models/DataRetentionPolicyTest.php`

Expected: FAIL because the new columns and model attributes do not exist.

- [ ] **Step 3: Write the migration and minimal model changes**

Create the migration with a string mode defaulting to `whole_record` and nullable JSON `selected_fields`. Add the fields to `$fillable`; cast `selected_fields` to `array`; retain existing date, boolean, datetime, and integer casts. Do not change the one-policy-per-form unique index.

- [ ] **Step 4: Run the model test to verify it passes**

Run: `php artisan test --compact tests/Unit/Models/DataRetentionPolicyTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations app/Models/DataRetentionPolicy.php tests/Unit/Models/DataRetentionPolicyTest.php
git commit -m "feat: store retention deletion modes"
```

### Task 2: Implement selected-field cleanup with TDD

**Files:**
- Modify: `app/Services/DataRetentionService.php`
- Test: `tests/Unit/Services/DataRetentionServiceTest.php`

**Interfaces:**
- Consumes: `DataRetentionPolicy` with `deletion_mode` and `selected_fields`; related `Form`; Laravel `Schema` metadata.
- Produces: `DataRetentionService::run(): array{processed:int, deleted:int, skipped:int}` supporting both policy modes.

- [ ] **Step 1: Write the failing selected-field preservation test**

Create a temporary table containing `date`, a required string field, a nullable string field, a required decimal field, a required boolean/integer field, and an unselected string field. Create a selected-field policy selecting the first four clearable fields. Insert a matching row and a post-cutoff row. Assert after `run()` that the matching row still exists, selected values become `''`, `NULL`, `0`, and `0`, the unselected value remains unchanged, and the post-cutoff row is untouched.

- [ ] **Step 2: Run the service test to verify it fails**

Run: `php artisan test --compact tests/Unit/Services/DataRetentionServiceTest.php --filter=selected_field`

Expected: FAIL because the service currently always deletes matching rows.

- [ ] **Step 3: Write the failing stale/unsupported field test**

Create one valid whole-record policy and one selected-field policy containing a missing or unsupported non-null date/time field. Assert the invalid policy is skipped without modifying its table and the valid policy still processes. This proves the service validates all fields before issuing a bulk update.

- [ ] **Step 4: Run the stale/unsupported test to verify it fails**

Run: `php artisan test --compact tests/Unit/Services/DataRetentionServiceTest.php --filter=unsupported`

Expected: FAIL because no selected-field validation branch exists.

- [ ] **Step 5: Implement the minimal service branch**

Keep the existing table/date checks and whole-record delete path. For selected-field policies, validate identifiers, intersect policy fields with the related form's active `formFields`, retrieve live column metadata with `Schema::getColumns($tableName)`, build a type-safe update map, reject unsupported non-null types, and issue one `whereDate(...)->update($updates)` query. Update run metadata only after success.

- [ ] **Step 6: Run focused service tests to verify green**

Run: `php artisan test --compact tests/Unit/Services/DataRetentionServiceTest.php`

Expected: PASS, including the pre-existing whole-record and missing-storage tests.

- [ ] **Step 7: Commit**

```bash
git add app/Services/DataRetentionService.php tests/Unit/Services/DataRetentionServiceTest.php
git commit -m "feat: clear selected retention fields safely"
```

### Task 3: Validate and persist policies

**Files:**
- Modify: `app/Http/Requests/Admin/UpsertDataRetentionPolicyRequest.php`
- Modify: `app/Http/Controllers/Admin/DataRetentionController.php`
- Test: `tests/Feature/Admin/DataRetentionAdminTest.php`

**Interfaces:**
- Consumes: Request fields `form_id`, `cutoff_date`, `deletion_mode`, `selected_fields[]`, and optional `is_active`.
- Produces: One validated policy per form; whole-record saves clear `selected_fields`; selected-field saves persist only eligible fields.

- [ ] **Step 1: Write failing admin tests**

Add tests for selected-field policy creation, mode update, rejection of empty selected fields, rejection of fields from another form, rejection of system fields, and Super Admin-only access. Assert persisted mode and JSON array values.

- [ ] **Step 2: Run the admin tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Admin/DataRetentionAdminTest.php`

Expected: FAIL because the request ignores/does not validate the new mode and fields.

- [ ] **Step 3: Implement request and controller changes**

Validate the mode with `in:whole_record,selected_fields`. Require a non-empty array only in selected-field mode. Resolve the submitted active form, filter fields through its `formFields` relationship and schema eligibility, and return validation errors for cross-form/system/unsupported selections. Upsert mode and selected fields; store `null` for whole-record mode.

- [ ] **Step 4: Run admin tests to verify green**

Run: `php artisan test --compact tests/Feature/Admin/DataRetentionAdminTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/Admin/UpsertDataRetentionPolicyRequest.php app/Http/Controllers/Admin/DataRetentionController.php tests/Feature/Admin/DataRetentionAdminTest.php
git commit -m "feat: validate retention field selections"
```

### Task 4: Update configuration data and Blade UI

**Files:**
- Modify: `app/Http/Controllers/Admin/ConfigurationController.php`
- Modify: `resources/views/admin/configuration.blade.php`
- Test: `tests/Feature/Admin/DataRetentionAdminTest.php`

**Interfaces:**
- Consumes: Active forms with `retentionPolicy` and `formFields`, selected-form query parameter, saved policy mode/fields.
- Produces: Mode-specific retention form, automatically filtered checkbox list, and policy summary table.

- [ ] **Step 1: Extend failing view assertions**

Assert the retention page renders both mode labels, eligible field labels for the selected form, excludes a field belonging only to another form, and shows saved selected fields in the policy table.

- [ ] **Step 2: Run the view tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Admin/DataRetentionAdminTest.php --filter=retention`

Expected: FAIL because the controller and view currently expose only cutoff and active state.

- [ ] **Step 3: Implement controller loading**

Eager-load ordered `formFields` with each active retention form and retain the existing selected-form fallback. Keep the existing policy query and relationships.

- [ ] **Step 4: Implement the mode-aware Blade form**

Add radio/select mode controls, Alpine state for showing the selected-field panel, checkbox inputs named `selected_fields[]`, labels/database names, mode-specific warning copy, preserved old input, and a table summary of mode/fields. Keep automatic GET submission when the form selector changes.

- [ ] **Step 5: Run the view tests to verify green**

Run: `php artisan test --compact tests/Feature/Admin/DataRetentionAdminTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/ConfigurationController.php resources/views/admin/configuration.blade.php tests/Feature/Admin/DataRetentionAdminTest.php
git commit -m "feat: add retention field selection UI"
```

### Task 5: Verify and align the change

**Files:**
- Modify: `openspec/changes/data-retention-field-clearing/tasks.md`
- Modify: `openspec/specs/data-retention/spec.md`
- Modify: `openspec/changes/data-retention-field-clearing/specs/data-retention/spec.md`

- [ ] **Step 1: Run formatting and focused tests**

Run: `vendor/bin/pint --dirty --format agent` and then the focused model, service, command, and admin tests.

Expected: Pint reports no remaining changes and all focused tests pass.

- [ ] **Step 2: Run application checks**

Run: `php artisan route:list --path=admin/configuration`, `php artisan schedule:list`, and `php artisan test --compact`.

Expected: retention routes/schedule are present and the full suite passes.

- [ ] **Step 3: Verify the browser flow**

Use Playwright to open the Super Admin Data Retention tab, switch forms, choose selected-field mode, verify the checkbox list changes to the selected form, select fields, and confirm the saved policy summary. Check console messages for feature-specific errors.

- [ ] **Step 4: Sync and archive OpenSpec**

Copy the completed delta requirements into the main `openspec/specs/data-retention/spec.md`, mark all task checkboxes complete, run the available OpenSpec status/verification commands, and archive the change after implementation and validation pass.

- [ ] **Step 5: Commit final documentation alignment**

```bash
git add openspec docs/superpowers/plans
git commit -m "docs: archive retention field clearing change"
```
