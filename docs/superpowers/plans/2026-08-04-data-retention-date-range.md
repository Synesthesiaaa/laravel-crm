# Data Retention Date Range Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the retention policy's single cutoff input with an inclusive From/To date range while preserving legacy cutoff-only policies.

**Architecture:** Rename the policy's `cutoff_date` column to `to_date` and add nullable `from_date`. The service will compose one conditional lower-bound and mandatory upper-bound query for both deletion modes. Existing Super Admin retention UI, form-specific field filtering, selected-field type safety, and scheduler remain in place.

**Tech Stack:** Laravel 12, PHP 8.5, Eloquent, MySQL/SQLite test database, Blade, Alpine.js, PHPUnit 11, Laravel Pint, Playwright MCP.

## Global Constraints

- The range is inclusive: matching records satisfy `date >= from_date` when present and `date <= to_date`.
- Existing policies retain a null lower bound and their previous upper-cutoff behavior.
- New or edited policies require valid `Y-m-d` From and To dates with From not later than To.
- Selected-field clearing must preserve the record and every unselected field and use the existing type-safe clearing values.
- No dependencies may be added.
- Every changed PHP file must be formatted with `vendor/bin/pint --dirty --format agent`.
- Every behavior change must have a PHPUnit test that was observed failing before the production implementation.

---

### Task 1: Add the date-range schema and model contract

**Files:**
- Create: `database/migrations/2026_08_04_013537_add_date_range_to_data_retention_policies_table.php`
- Modify: `app/Models/DataRetentionPolicy.php`
- Test: `tests/Unit/Models/DataRetentionPolicyTest.php`

**Interfaces:**
- Produces `DataRetentionPolicy::$fillable` and `casts()` entries for `from_date` and `to_date`.
- Produces a migration that renames `cutoff_date` to `to_date` and adds nullable `from_date`.

- [x] **Step 1: Write the failing model test**

Update the model tests to create a policy with `from_date` and `to_date`, then assert both cast to dates. Replace the old `cutoff_date` assertions with `to_date` and add a legacy assertion that a null `from_date` is allowed.

- [x] **Step 2: Run the model test to verify it fails**

Run: `php artisan test --compact tests/Unit/Models/DataRetentionPolicyTest.php`

Expected: FAIL because the current table and model do not accept the new column names.

- [x] **Step 3: Write the migration and model changes**

Create a migration using Laravel schema operations:

```php
Schema::table('data_retention_policies', function (Blueprint $table): void {
    $table->renameColumn('cutoff_date', 'to_date');
    $table->date('from_date')->nullable()->after('to_date');
});
```

Update the model fillable list and casts to use `from_date` and `to_date`.

- [x] **Step 4: Run the model test to verify it passes**

Run: `php artisan test --compact tests/Unit/Models/DataRetentionPolicyTest.php`

Expected: PASS with the new date casts and legacy null lower bound covered.

- [x] **Step 5: Commit the schema and model contract**

```bash
git add database/migrations app/Models/DataRetentionPolicy.php tests/Unit/Models/DataRetentionPolicyTest.php
git commit -m "feat: add retention date range fields"
```

### Task 2: Apply the inclusive range in the cleanup service

**Files:**
- Modify: `app/Services/DataRetentionService.php`
- Test: `tests/Unit/Services/DataRetentionServiceTest.php`
- Test: `tests/Feature/DataRetentionCommandTest.php`

**Interfaces:**
- `DataRetentionService::run(): array` continues returning `processed`, `deleted`, and `skipped` counts.
- The service applies a nullable lower bound and mandatory `to_date` to both update and delete queries.

- [x] **Step 1: Write the failing range service tests**

Change policy fixtures to use `to_date`. Add a whole-record test with records before From, on From, inside, on To, and after To; assert only the three in-range records are deleted. Add a legacy test with `from_date => null` that deletes records through To. Update the selected-field test to include records before From, on the boundaries, inside, and after To, asserting only in-range selected fields are cleared.

- [x] **Step 2: Run the service tests to verify they fail**

Run: `php artisan test --compact tests/Unit/Services/DataRetentionServiceTest.php tests/Feature/DataRetentionCommandTest.php`

Expected: FAIL because the service still reads `cutoff_date` and has no lower-bound predicate.

- [x] **Step 3: Implement the shared date predicates**

In both selected-field update and whole-record delete branches, build the table query with a conditional lower bound and mandatory upper bound:

```php
$query = DB::table($tableName)
    ->when($policy->from_date !== null, fn ($query) => $query->whereDate('date', '>=', $policy->from_date->format('Y-m-d')))
    ->whereDate('date', '<=', $policy->to_date->format('Y-m-d'));
```

Use `update($updates)` or `delete()` on that query. Keep all existing storage, field, and metadata safeguards.

- [x] **Step 4: Run the service tests to verify they pass**

Run: `php artisan test --compact tests/Unit/Services/DataRetentionServiceTest.php tests/Feature/DataRetentionCommandTest.php`

Expected: PASS, including range boundaries, legacy behavior, selected-field preservation, and the unchanged daily schedule assertion.

- [x] **Step 5: Commit the cleanup behavior**

```bash
git add app/Services/DataRetentionService.php tests/Unit/Services/DataRetentionServiceTest.php tests/Feature/DataRetentionCommandTest.php
git commit -m "feat: apply retention date ranges"
```

### Task 3: Validate and persist From/To dates in the admin workflow

**Files:**
- Modify: `app/Http/Requests/Admin/UpsertDataRetentionPolicyRequest.php`
- Modify: `app/Http/Controllers/Admin/DataRetentionController.php`
- Test: `tests/Feature/Admin/DataRetentionAdminTest.php`

**Interfaces:**
- The request accepts `from_date` and `to_date` and rejects reversed ranges.
- The controller upserts `from_date`, `to_date`, mode, selected fields, and active state by `form_id`.

- [x] **Step 1: Write the failing request/controller tests**

Replace `cutoff_date` payloads and assertions with `from_date` and `to_date`. Add a reversed-range request that asserts a validation error and zero policy rows. Add a valid policy assertion for both persisted dates and retain the existing update/deactivation and selected-field authorization cases.

- [x] **Step 2: Run the admin tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Admin/DataRetentionAdminTest.php`

Expected: FAIL because the request and controller still require and persist `cutoff_date`.

- [x] **Step 3: Implement date validation and persistence**

Use `from_date` and `to_date` rules with `date_format:Y-m-d`, `required`, and `before_or_equal:to_date` on From. Store both validated values in `updateOrCreate`, leaving selected-field and active-state behavior unchanged.

- [x] **Step 4: Run the admin tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Admin/DataRetentionAdminTest.php`

Expected: PASS for authorization, form validation, date-range persistence, update behavior, deactivation, selected-field validation, and policy uniqueness.

- [x] **Step 5: Commit the admin data flow**

```bash
git add app/Http/Requests/Admin/UpsertDataRetentionPolicyRequest.php app/Http/Controllers/Admin/DataRetentionController.php tests/Feature/Admin/DataRetentionAdminTest.php
git commit -m "feat: validate retention date ranges"
```

### Task 4: Update the Super Admin retention interface

**Files:**
- Modify: `resources/views/admin/configuration.blade.php`
- Modify: `tests/Feature/Admin/DataRetentionAdminTest.php`

**Interfaces:**
- The retention tab renders `From date` and `To date` inputs.
- Legacy policies render `Any date` for a null From value and show To separately in the table.

- [x] **Step 1: Write the failing view assertions**

Assert the retention page contains `name="from_date"`, `name="to_date"`, the From/To labels, and separate policy table headings. Add a policy fixture with a null From date and assert `Any date` is rendered.

- [x] **Step 2: Run the focused admin test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/DataRetentionAdminTest.php --filter="retention"`

Expected: FAIL because the Blade view still renders the cutoff input and cutoff column.

- [x] **Step 3: Implement the Blade changes**

Load old values from `from_date` and `to_date`, render two required date inputs with field-specific errors, replace the cutoff label and input, update table headings/cells, and change the empty-state copy to describe a date range. Keep the existing Alpine mode and form-selection behavior unchanged.

- [x] **Step 4: Run the focused admin test to verify it passes**

Run: `php artisan test --compact tests/Feature/Admin/DataRetentionAdminTest.php --filter="retention"`

Expected: PASS with From/To controls, legacy display, and existing automatic field filtering assertions.

- [x] **Step 5: Commit the interface**

```bash
git add resources/views/admin/configuration.blade.php tests/Feature/Admin/DataRetentionAdminTest.php
git commit -m "feat: show retention date ranges in admin"
```

### Task 5: Format, synchronize specifications, and verify the complete feature

**Files:**
- Modify: `openspec/changes/data-retention-date-range/tasks.md`
- Modify: `openspec/specs/data-retention/spec.md` through OpenSpec sync
- Review: all files changed by Tasks 1–4

- [x] **Step 1: Run Laravel Pint**

Run: `vendor/bin/pint --dirty --format agent`

Expected: Pint exits successfully and applies any required formatting fixes.

- [x] **Step 2: Run the focused automated suite**

Run: `php artisan test --compact tests/Unit/Models/DataRetentionPolicyTest.php tests/Unit/Services/DataRetentionServiceTest.php tests/Feature/DataRetentionCommandTest.php tests/Feature/Admin/DataRetentionAdminTest.php`

Expected: all focused tests pass with zero failures.

- [x] **Step 3: Verify the database and scheduled command**

Run: `php artisan migrate:status; php artisan route:list --path=admin/configuration; php artisan schedule:list`

Expected: the date-range migration is applied, retention routes remain available, and `data-retention:run` remains scheduled daily.

- [x] **Step 4: Verify the UI in Playwright**

Open the Super Admin retention tab and verify the From/To date inputs, reversed-range validation feedback, selected-form field filtering, mode switching, and separate From/To policy columns. Record any unrelated browser console warnings separately from feature failures.

- [x] **Step 5: Run the complete test suite**

Run: `php artisan test --compact`

Expected: the complete PHPUnit suite passes with zero failures.

- [x] **Step 6: Synchronize the main OpenSpec capability**

Run: `openspec sync --change "data-retention-date-range"`

Expected: `openspec/specs/data-retention/spec.md` reflects the inclusive range requirements and legacy behavior.

- [x] **Step 7: Review the final diff and status**

Run: `git diff --check; git status --short; openspec status --change "data-retention-date-range"`

Expected: no whitespace errors, only intended feature/spec files changed, and all OpenSpec tasks are complete.
