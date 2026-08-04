# Retention Policy Execution Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add immediate, scheduled one-time, and recurring execution controls to data retention policies while making policy deletion separate from data destruction.

**Architecture:** Extend each policy with execution configuration and calculated next-run metadata. A due-policy retention service will execute only active policies whose next run is due, while a policy-specific method powers the Super Admin Run Now action. The scheduler will poll the due-policy command every minute, and existing policies will be backfilled to daily execution at 03:00.

**Tech Stack:** Laravel 12, PHP 8.5, Eloquent, MySQL/SQLite test database, Blade, Alpine.js, PHPUnit 11, Laravel Pint, Playwright MCP.

## Global Constraints

- Delete removes only the retention policy configuration and must not mutate form records.
- Run Now is an explicit destructive action and must be protected by a confirmation prompt.
- One-time policies deactivate only after successful execution; failed or skipped one-time policies remain visible and require administrator action.
- Recurring policies support daily, weekly, and monthly schedules and remain active after Run Now.
- Existing From/To date-range filtering, whole-record deletion, selected-field clearing, and Super Admin authorization must remain unchanged.
- No new dependencies, queues, or external scheduler services may be added.
- Every changed PHP file must be formatted with `vendor/bin/pint --dirty --format agent`.
- Every behavior change must have a PHPUnit test observed failing before its production implementation.

---

### Task 1: Add execution fields and migrate existing policies

**Files:**
- Create: `database/migrations/2026_08_04_092511_add_execution_settings_to_data_retention_policies_table.php`
- Modify: `app/Models/DataRetentionPolicy.php`
- Test: `tests/Unit/Models/DataRetentionPolicyTest.php`

**Interfaces:**
- `DataRetentionPolicy` exposes fillable execution settings: `run_mode`, `run_at`, `recurrence`, `run_time`, `run_day_of_week`, `run_day_of_month`, `next_run_at`, `last_run_status`, and `last_error`.
- The migration backfills existing rows as recurring daily policies at 03:00 with the next daily occurrence.

- [x] **Step 1: Write the failing model and migration assertions**

Add a policy fixture with recurring settings and assert the date/time casts, defaults, and nullable execution metadata. Add a one-time fixture asserting `run_at` casts to a datetime and add assertions for the execution columns in the schema.

- [x] **Step 2: Run the model test to verify it fails**

Run: `php artisan test --compact tests/Unit/Models/DataRetentionPolicyTest.php`

Expected: FAIL because the execution columns and model casts do not yet exist.

- [x] **Step 3: Generate and implement the migration and model fields**

Create the migration with Laravel Artisan, then add the columns and backfill existing rows:

```php
$table->string('run_mode', 16)->default('recurring')->after('is_active');
$table->dateTime('run_at')->nullable()->after('run_mode');
$table->string('recurrence', 16)->nullable()->after('run_at');
$table->time('run_time')->nullable()->after('recurrence');
$table->unsignedTinyInteger('run_day_of_week')->nullable()->after('run_time');
$table->unsignedTinyInteger('run_day_of_month')->nullable()->after('run_day_of_week');
$table->dateTime('next_run_at')->nullable()->after('run_day_of_month');
$table->string('last_run_status', 16)->nullable()->after('last_deleted_count');
$table->text('last_error')->nullable()->after('last_run_status');
```

Use `now()->setTime(3, 0)` and advance one day when the 03:00 occurrence has passed before updating existing rows with recurring daily values. Add the reverse operations in `down()`.

- [x] **Step 4: Run the model test to verify it passes**

Run: `php artisan test --compact tests/Unit/Models/DataRetentionPolicyTest.php`

Expected: PASS with execution fields, casts, and migration defaults covered.

- [x] **Step 5: Commit the schema contract**

```bash
git add database/migrations app/Models/DataRetentionPolicy.php tests/Unit/Models/DataRetentionPolicyTest.php
git commit -m "feat: add retention execution settings"
```

### Task 2: Add schedule calculation and conditional validation

**Files:**
- Create: `app/Services/DataRetentionScheduleService.php`
- Modify: `app/Http/Requests/Admin/UpsertDataRetentionPolicyRequest.php`
- Test: `tests/Unit/Services/DataRetentionScheduleServiceTest.php`
- Test: `tests/Feature/Admin/DataRetentionAdminTest.php`

**Interfaces:**
- `DataRetentionScheduleService::nextRunAt(DataRetentionPolicy $policy, CarbonImmutable $from): CarbonImmutable` returns the next due occurrence.
- The request validates one-time fields only in `once` mode and recurring fields only in `recurring` mode.

- [x] **Step 1: Write failing schedule and validation tests**

Create tests for next daily, weekly, and monthly occurrences, including a monthly day of 31 clamped to February's last day. Add admin requests that reject missing or invalid one-time and recurring settings and accept valid schedules.

- [x] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Unit/Services/DataRetentionScheduleServiceTest.php tests/Feature/Admin/DataRetentionAdminTest.php`

Expected: FAIL because the schedule service and execution validation do not exist.

- [x] **Step 3: Implement schedule calculation and validation**

Implement `nextRunAt()` with CarbonImmutable. For recurring policies, calculate the next daily, selected weekly, or selected monthly occurrence at `run_time`. For one-time policies, return `run_at`.

Add request rules equivalent to:

```php
'run_mode' => ['required', Rule::in(['once', 'recurring'])],
'run_at' => ['exclude_unless:run_mode,once', 'required', 'date_format:Y-m-d\\TH:i'],
'recurrence' => ['exclude_unless:run_mode,recurring', 'required', Rule::in(['daily', 'weekly', 'monthly'])],
'run_time' => ['exclude_unless:run_mode,recurring', 'required', 'date_format:H:i'],
'run_day_of_week' => ['exclude_unless:recurrence,weekly', 'required', 'integer', 'between:1,7'],
'run_day_of_month' => ['exclude_unless:recurrence,monthly', 'required', 'integer', 'between:1,31'],
```

The request will preserve all existing form, date-range, deletion-mode, and selected-field validation.

- [x] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Unit/Services/DataRetentionScheduleServiceTest.php tests/Feature/Admin/DataRetentionAdminTest.php`

Expected: PASS for calculations, conditional validation, and existing retention behavior.

- [x] **Step 5: Commit schedule validation**

```bash
git add app/Services/DataRetentionScheduleService.php app/Http/Requests/Admin/UpsertDataRetentionPolicyRequest.php tests/Unit/Services/DataRetentionScheduleServiceTest.php tests/Feature/Admin/DataRetentionAdminTest.php
git commit -m "feat: validate retention execution schedules"
```

### Task 3: Refactor retention execution into due and manual policy runs

**Files:**
- Modify: `app/Services/DataRetentionService.php`
- Modify: `app/Http/Controllers/Admin/DataRetentionController.php`
- Modify: `app/Console/Commands/RunDataRetention.php`
- Test: `tests/Unit/Services/DataRetentionServiceTest.php`
- Test: `tests/Feature/DataRetentionCommandTest.php`

**Interfaces:**
- `DataRetentionService::runDue(): array` executes active due policies and returns `processed`, `deleted`, and `skipped` totals.
- `DataRetentionService::runPolicy(DataRetentionPolicy $policy, bool $manual = false): array` executes one policy and returns its status, error, and affected count.
- Manual recurring runs preserve `next_run_at`; scheduled recurring runs advance it.

- [x] **Step 1: Write failing execution lifecycle tests**

Add tests for due versus future policies, immediate Run Now for one-time and recurring policies, one-time deactivation after success, recurring schedule preservation for manual runs, recurring advancement after scheduled runs, and failed one-time policies clearing `next_run_at` while recording `last_error`.

- [x] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Unit/Services/DataRetentionServiceTest.php tests/Feature/DataRetentionCommandTest.php`

Expected: FAIL because the service only runs all active policies and has no execution lifecycle methods.

- [x] **Step 3: Implement policy execution lifecycle**

Extract the existing per-policy destruction logic into `runPolicy()`. Record `last_run_at`, `last_deleted_count`, `last_run_status`, and `last_error`. Implement these transitions:

- Successful one-time run: set `is_active` false and `next_run_at` null.
- Failed or skipped one-time run: keep the policy visible, record the error, and set `next_run_at` null.
- Successful/failed/skipped scheduled recurring run: keep active and set the next occurrence.
- Manual recurring run: execute immediately without changing its existing next occurrence.

Implement `runDue()` to select active policies with `next_run_at <= now()`, initialize missing legacy-compatible schedules, and continue after individual policy failures. Update the command to call `runDue()`.

- [x] **Step 4: Run the execution tests to verify they pass**

Run: `php artisan test --compact tests/Unit/Services/DataRetentionServiceTest.php tests/Feature/DataRetentionCommandTest.php`

Expected: PASS for date-range destruction plus manual, scheduled, recurring, one-time, skipped, and failed transitions.

- [x] **Step 5: Commit execution lifecycle**

```bash
git add app/Services/DataRetentionService.php app/Http/Controllers/Admin/DataRetentionController.php app/Console/Commands/RunDataRetention.php tests/Unit/Services/DataRetentionServiceTest.php tests/Feature/DataRetentionCommandTest.php
git commit -m "feat: run due retention policies"
```

### Task 4: Add policy actions and scheduler registration

**Files:**
- Modify: `routes/web.php`
- Modify: `routes/console.php`
- Modify: `app/Http/Controllers/Admin/DataRetentionController.php`
- Test: `tests/Feature/Admin/DataRetentionAdminTest.php`
- Test: `tests/Feature/DataRetentionCommandTest.php`

**Interfaces:**
- `POST /admin/configuration/retention/{policy}/run` runs one policy immediately.
- `DELETE /admin/configuration/retention/{policy}` deletes only the policy row.

- [x] **Step 1: Write failing route/action tests**

Add Super Admin tests that Run Now changes policy run metadata and data as configured, recurring Run Now remains active, one-time Run Now deactivates, Delete removes the policy but preserves a sample storage record, and non-Super Admins receive the existing authorization failure. Assert the schedule contains the retention command at the new polling frequency.

- [x] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Admin/DataRetentionAdminTest.php tests/Feature/DataRetentionCommandTest.php`

Expected: FAIL because the run/delete routes and due polling schedule do not exist.

- [x] **Step 3: Implement the routes and controller actions**

Replace the deactivate route with a DELETE destroy route and add the POST run route. `destroy()` will call `$policy->delete()` and redirect with a policy-deleted message. `run()` will call `runPolicy($policy, true)` and redirect with success or failure feedback without running other policies.

Change `routes/console.php` from daily 03:00 to every minute while preserving `withoutOverlapping`, background execution, and scheduler log output.

- [x] **Step 4: Run the route/action tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Admin/DataRetentionAdminTest.php tests/Feature/DataRetentionCommandTest.php`

Expected: PASS for authorization, manual actions, policy-only deletion, and the every-minute scheduled command.

- [x] **Step 5: Commit routes and scheduler**

```bash
git add routes/web.php routes/console.php app/Http/Controllers/Admin/DataRetentionController.php tests/Feature/Admin/DataRetentionAdminTest.php tests/Feature/DataRetentionCommandTest.php
git commit -m "feat: add retention policy actions"
```

### Task 5: Redesign the retention policy interface

**Files:**
- Modify: `resources/views/admin/configuration.blade.php`
- Modify: `tests/Feature/Admin/DataRetentionAdminTest.php`

**Interfaces:**
- The form renders conditional one-time and recurring schedule controls.
- The table renders Edit, Run Now, and Delete actions with confirmation prompts.

- [x] **Step 1: Write failing view assertions**

Assert the page renders Run Once/Recurring controls, scheduled date/time, recurrence controls, next/last-run fields, Run Now, Edit, and Delete. Assert the form hides irrelevant weekly/monthly fields when another recurrence is selected and the table no longer renders Deactivate.

- [x] **Step 2: Run the view tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Admin/DataRetentionAdminTest.php`

Expected: FAIL because the page still has only active/deactivate behavior and no execution settings.

- [x] **Step 3: Implement the Blade and Alpine UI**

Load execution fields from the selected policy and old input. Add conditional controls for one-time and recurring schedules, preserve the existing From/To, destruction mode, and form-specific field list, and render errors next to each schedule field.

Replace the Deactivate form with:

```blade
<form method="POST" action="{{ route('admin.configuration.retention.run', $policy) }}" onsubmit="return confirm('Run this retention policy now? This may permanently delete or clear matching data.')">
    @csrf
    <button type="submit" class="btn-danger">Run Now</button>
</form>
<form method="POST" action="{{ route('admin.configuration.retention.destroy', $policy) }}" onsubmit="return confirm('Delete only this retention policy configuration? Form data will not be changed.')">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn-danger">Delete</button>
</form>
```

Use responsive wrappers so execution metadata and actions remain usable on narrow screens.

- [x] **Step 4: Run the view tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Admin/DataRetentionAdminTest.php`

Expected: PASS for conditional controls, status display, Edit, Run Now, Delete, and preserved field filtering.

- [x] **Step 5: Commit the UI**

```bash
git add resources/views/admin/configuration.blade.php tests/Feature/Admin/DataRetentionAdminTest.php
git commit -m "feat: redesign retention policy controls"
```

### Task 6: Format, verify, and synchronize specifications

**Files:**
- Modify: `openspec/changes/retention-policy-execution/tasks.md`
- Modify: `openspec/specs/data-retention/spec.md` through OpenSpec sync
- Review: all files changed in Tasks 1–5

- [x] **Step 1: Run Laravel Pint**

Run: `vendor/bin/pint --dirty --format agent`

Expected: Pint exits successfully.

- [x] **Step 2: Run focused and complete tests**

Run: `php artisan test --compact tests/Unit/Models/DataRetentionPolicyTest.php tests/Unit/Services/DataRetentionScheduleServiceTest.php tests/Unit/Services/DataRetentionServiceTest.php tests/Feature/DataRetentionCommandTest.php tests/Feature/Admin/DataRetentionAdminTest.php` and then `php artisan test --compact`.

Expected: all focused tests and the complete PHPUnit suite pass with zero failures.

- [x] **Step 3: Verify migration, routes, schedule, and command output**

Run: `php artisan migrate:status; php artisan route:list --path=admin/configuration; php artisan schedule:list; php artisan data-retention:run`

Expected: the execution migration is applied, run/delete routes are present, the due command is scheduled every minute, and the command reports totals without errors.

- [x] **Step 4: Verify the admin flow in Playwright**

Verify the retention tab renders the new controls, changing Run Once/Recurring and recurrence choices changes visible fields, Edit loads stored values, Run Now shows a confirmation prompt, Delete clearly states that only policy configuration is deleted, and the table displays next/last-run status.

- [x] **Step 5: Sync the main OpenSpec capability and review status**

Run: `openspec sync --change "retention-policy-execution"; git diff --check; git status --short; openspec status --change "retention-policy-execution"`

Expected: the main data-retention spec includes execution lifecycle requirements, no whitespace errors exist, and all OpenSpec tasks are complete.
