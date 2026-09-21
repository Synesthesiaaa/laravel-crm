# Form Data Retention Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a Super Admin workflow for configuring explicit per-form cutoff dates and automatically permanently deleting complete form records at or before those dates.

**Architecture:** Store one policy per active `Form` in a relational `data_retention_policies` table. A `DataRetentionService` resolves each configured table only through the related `Form`, validates its schema, deletes matching rows with the query builder, and records run metadata. A dedicated admin controller owns policy writes while the existing Configuration page renders the retention tab; a scheduled Artisan command invokes the service daily.

**Tech Stack:** Laravel 12.53, PHP 8.5, MySQL, Eloquent, query builder, Laravel scheduler, Blade, Alpine.js, PHPUnit 11, Laravel Pint, Playwright.

## Global Constraints

- Retention deletes complete records from selected form tables; it does not delete individual fields.
- The administrator chooses an explicit `Y-m-d` cutoff date; no relative retention-period mode is added.
- Only active, database-backed forms can receive policies.
- Table names are never accepted from request input; they are resolved from `Form` and checked with `Schema` before querying.
- Only Super Admin users can view or mutate retention configuration.
- The scheduled command must continue after a missing/malformed table or a per-policy failure.
- Every new production behavior must have a PHPUnit test written and observed failing before implementation.
- Run `vendor/bin/pint --dirty --format agent` after modifying PHP files.

---

### Task 1: Add the retention policy persistence contract

**Files:**
- Create: `database/migrations/2026_08_03_090341_create_data_retention_policies_table.php`
- Create: `app/Models/DataRetentionPolicy.php`
- Modify: `app/Models/Form.php`
- Test: `tests/Unit/Models/DataRetentionPolicyTest.php`

**Interfaces:**
- Produces `DataRetentionPolicy` with `form()`, `cutoff_date`, `is_active`, `last_run_at`, and `last_deleted_count`.
- Produces `Form::retentionPolicy(): HasOne`.

- [ ] **Step 1: Write the failing model/relationship test**

Create a PHPUnit test using `RefreshDatabase` that creates an active `Form`, creates a policy with `cutoff_date = '2026-01-31'`, and asserts:

```php
$this->assertSame($form->id, $policy->form->id);
$this->assertSame('2026-01-31', $policy->cutoff_date->format('Y-m-d'));
$this->assertTrue($policy->is_active);
```

Also assert that the `form_id` database index rejects a second policy for the same form.

- [ ] **Step 2: Run the test and verify the expected RED failure**

Run:

```powershell
php artisan test --compact tests/Unit/Models/DataRetentionPolicyTest.php
```

Expected: the test fails because the policy table/model and relationship do not exist.

- [ ] **Step 3: Implement the migration, model, and relationship**

The migration must create:

```php
$table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
$table->date('cutoff_date');
$table->boolean('is_active')->default(true);
$table->timestamp('last_run_at')->nullable();
$table->unsignedInteger('last_deleted_count')->default(0);
$table->timestamps();
$table->unique('form_id');
```

Use `protected $fillable` for `form_id`, `cutoff_date`, `is_active`, `last_run_at`, and `last_deleted_count`; cast the date, boolean, and timestamp values. Add the `hasOne` relationship on `Form` with the correct return type.

- [ ] **Step 4: Run the focused test and verify GREEN**

Run the same PHPUnit command and confirm it passes with zero failures.

- [ ] **Step 5: Commit the persistence unit**

```powershell
git add database/migrations/2026_08_03_090341_create_data_retention_policies_table.php app/Models/DataRetentionPolicy.php app/Models/Form.php tests/Unit/Models/DataRetentionPolicyTest.php
git commit -m "feat: add data retention policy persistence"
```

### Task 2: Implement and test scheduled destruction

**Files:**
- Create: `app/Services/DataRetentionService.php`
- Create: `app/Console/Commands/RunDataRetention.php`
- Modify: `routes/console.php`
- Test: `tests/Unit/Services/DataRetentionServiceTest.php`
- Test: `tests/Feature/DataRetentionCommandTest.php`

**Interfaces:**
- `DataRetentionService::run(): array{processed:int, deleted:int, skipped:int}`.
- `RunDataRetention::handle(DataRetentionService $retentionService): int` invokes the service and writes per-policy/summary output.
- Artisan command name: `data-retention:run`.

- [ ] **Step 1: Write the failing service test for cutoff deletion and isolation**

Create a real temporary form storage table with `date`, `request_id`, and timestamps. Create two registered forms, policies only for the first form, and rows dated before, exactly on, and after its cutoff. Assert the service result and database state:

```php
$summary = app(DataRetentionService::class)->run();

$this->assertSame(2, $summary['deleted']);
$this->assertDatabaseMissing($tableName, ['request_id' => 'before-cutoff']);
$this->assertDatabaseMissing($tableName, ['request_id' => 'on-cutoff']);
$this->assertDatabaseHas($tableName, ['request_id' => 'after-cutoff']);
$this->assertDatabaseCount($otherTableName, 1);
```

- [ ] **Step 2: Run the service test and verify RED**

```powershell
php artisan test --compact tests/Unit/Services/DataRetentionServiceTest.php
```

Expected: failure because `DataRetentionService` does not exist.

- [ ] **Step 3: Implement the minimal retention service**

Load active policies with their related active `Form`. For each policy:

1. Skip and log when there is no active form.
2. Reject table names that do not match `^[A-Za-z0-9_]+$`.
3. Skip and log when `Schema::hasTable($tableName)` or `Schema::hasColumn($tableName, 'date')` is false.
4. Delete with `DB::table($tableName)->whereDate('date', '<=', $policy->cutoff_date->format('Y-m-d'))->delete()`.
5. Update `last_run_at` and `last_deleted_count` only after deletion succeeds.
6. Catch `Throwable`, log the form/policy context, increment `skipped`, and continue.

Use `Log` for operational failures and return integer summary fields. Do not accept a table name or cutoff from the command arguments.

- [ ] **Step 4: Run the service test and verify GREEN**

Run the focused service test again and confirm the cutoff, form isolation, and metadata assertions pass.

- [ ] **Step 5: Add failing command/schedule coverage**

Add a feature test that calls `Artisan::call('data-retention:run')`, asserts a zero exit code, and checks command output includes the total deleted count. Add an assertion that `collect(Schedule::events())->pluck('command')` contains `data-retention:run` with the expected daily event configuration.

- [ ] **Step 6: Implement the command and scheduler registration**

Create an Artisan command with signature `data-retention:run` and description explaining permanent form-record cleanup. It must call the service, print processed/skipped/deleted totals, and return `Command::SUCCESS`. Register it once daily at `03:00`, with `withoutOverlapping(60)`, `runInBackground()`, and `appendOutputTo(storage_path('logs/scheduler.log'))` in `routes/console.php`.

- [ ] **Step 7: Run command/schedule tests and verify GREEN**

```powershell
php artisan test --compact tests/Feature/DataRetentionCommandTest.php
```

Confirm the command executes successfully and the scheduled event is present.

- [ ] **Step 8: Commit the cleanup unit**

```powershell
git add app/Services/DataRetentionService.php app/Console/Commands/RunDataRetention.php routes/console.php tests/Unit/Services/DataRetentionServiceTest.php tests/Feature/DataRetentionCommandTest.php
git commit -m "feat: schedule form data retention cleanup"
```

### Task 3: Add Super Admin policy management

**Files:**
- Create: `app/Http/Requests/Admin/UpsertDataRetentionPolicyRequest.php`
- Create: `app/Http/Controllers/Admin/DataRetentionController.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Admin/ConfigurationController.php`
- Modify: `resources/views/admin/configuration.blade.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Admin/DataRetentionAdminTest.php`

**Interfaces:**
- `DataRetentionController::store(UpsertDataRetentionPolicyRequest $request): RedirectResponse` upserts by `form_id`.
- `DataRetentionController::deactivate(Request $request, DataRetentionPolicy $policy): RedirectResponse` sets `is_active` false.
- Named routes: `admin.configuration.retention.store` and `admin.configuration.retention.deactivate`.
- Configuration view receives `retentionForms`, `retentionPolicies`, and `selectedRetentionFormId`.

- [ ] **Step 1: Write failing admin tests for authorization and rendering**

Add tests that:

```php
$this->actingAs(User::factory()->create(['role' => User::ROLE_AGENT]))
    ->get(route('admin.configuration', ['tab' => 'retention']))
    ->assertForbidden();

$this->actingAs($superAdmin)
    ->get(route('admin.configuration', ['tab' => 'retention']))
    ->assertOk()
    ->assertSee('Data Retention', false)
    ->assertSee('permanently deletes complete records', false);
```

Also assert active form names and an existing policy’s cutoff appear in the response.

- [ ] **Step 2: Run the admin test and verify RED**

```powershell
php artisan test --compact tests/Feature/Admin/DataRetentionAdminTest.php
```

Expected: failure because the retention tab data, routes, and UI do not exist.

- [ ] **Step 3: Implement request validation and controller writes**

Authorize only `User::isSuperAdmin()`. Validate `form_id` with an active-form `Rule::exists` constraint, `cutoff_date` with `date_format:Y-m-d`, and `is_active` as nullable boolean. Upsert with `DataRetentionPolicy::updateOrCreate(['form_id' => ...], [...])`. Deactivation must use route model binding and update only `is_active`.

Add the two POST routes inside the existing `auth`, `campaign`, `admin`, and `role:Super Admin` groups. Extend `ConfigurationController::index()` only for retention-tab data: load active forms ordered by campaign/display order, policies with `form.campaign`, and select a form from `retention_form` or the first active form.

- [ ] **Step 4: Implement the retention tab UI**

Add `retention` to the configuration tab state list and create a tab link. Render a form with CSRF, active-form select, date input, active checkbox, and a clear warning that cleanup permanently deletes complete records. Render a policy table with campaign, form, storage table, cutoff date, status, last run, deleted count, and a deactivation form using the named route. Provide an edit link that returns to `?tab=retention&retention_form=<id>` so the main form loads the selected rule’s cutoff.

Do not render arbitrary table names as form inputs. Keep all labels and copy concise and consistent with existing components.

- [ ] **Step 5: Add the Super Admin sidebar entry if needed by the existing navigation pattern**

Keep Configuration as the navigation entry point and ensure its active state remains correct for `admin.configuration` and the new retention mutation redirects. Do not expose a new retention link to non-Super Admin users.

- [ ] **Step 6: Run admin tests and verify GREEN**

```powershell
php artisan test --compact tests/Feature/Admin/DataRetentionAdminTest.php
```

Confirm authorization, rendering, invalid date/inactive form validation, create/update, and deactivation assertions pass.

- [ ] **Step 7: Commit the admin unit**

```powershell
git add app/Http/Requests/Admin/UpsertDataRetentionPolicyRequest.php app/Http/Controllers/Admin/DataRetentionController.php app/Http/Controllers/Admin/ConfigurationController.php routes/web.php resources/views/admin/configuration.blade.php resources/views/layouts/sidebar.blade.php tests/Feature/Admin/DataRetentionAdminTest.php
git commit -m "feat: add super admin data retention settings"
```

### Task 4: Make Field Logic form filtering automatic

**Files:**
- Modify: `resources/views/admin/field_logic.blade.php`
- Test: `tests/Feature/Admin/FieldLogicAdminTest.php`

- [ ] **Step 1: Add the failing view assertion**

Extend the existing index-page test to assert the form select contains a change handler that submits its GET form, for example `@change="$el.form.submit()"`, while still showing fields for the selected form only.

- [ ] **Step 2: Run the existing focused test and verify RED**

```powershell
php artisan test --compact tests/Feature/Admin/FieldLogicAdminTest.php
```

Expected: failure because the current selector requires the separate Load button.

- [ ] **Step 3: Implement automatic selector submission**

Add the Alpine change handler to the existing form select. Remove only the now-redundant Load action if the surrounding layout remains usable; keep the server-side GET route and controller filtering unchanged.

- [ ] **Step 4: Run Field Logic tests and verify GREEN**

Run the same command and confirm all existing tests plus the new assertion pass.

- [ ] **Step 5: Run Pint on modified PHP files**

```powershell
vendor/bin/pint --dirty --format agent
```

Re-run the focused PHP tests after any formatting changes.

### Task 5: Verify, sync, and archive

**Files:**
- Modify: `openspec/changes/data-retention-form-records/tasks.md`
- Modify: `openspec/changes/data-retention-form-records/specs/data-retention/spec.md` only if implementation reveals a documented behavior difference.

- [ ] **Step 1: Run the complete affected test set**

```powershell
php artisan test --compact tests/Unit/Models/DataRetentionPolicyTest.php tests/Unit/Services/DataRetentionServiceTest.php tests/Feature/DataRetentionCommandTest.php tests/Feature/Admin/DataRetentionAdminTest.php tests/Feature/Admin/FieldLogicAdminTest.php
```

- [ ] **Step 2: Verify routes, schedule, and migration state**

```powershell
php artisan route:list --path=admin/configuration
php artisan schedule:list
php artisan migrate:status
```

Confirm the retention routes are Super Admin protected, the daily command is listed, and the migration is applied in the test/local database.

- [ ] **Step 3: Run browser verification**

Start the local app using the project’s existing dev command if needed. With Playwright, log in as Super Admin, open Configuration → Data Retention, verify the tab/form/table layout, and select a policy edit link. Open Field Logic, change the form select, verify navigation occurs and only the selected form’s fields render, and inspect browser console messages for errors.

- [ ] **Step 4: Check the final diff and update OpenSpec task checkboxes**

Run `git diff --check` and `git status --short`. Mark each completed task in `openspec/changes/data-retention-form-records/tasks.md`, then run:

```powershell
openspec status --change "data-retention-form-records"
openspec verify --change "data-retention-form-records"
```

- [ ] **Step 5: Sync and archive the completed OpenSpec change**

Use the project’s OpenSpec sync/archive workflow only after all tests and browser checks pass. Confirm the archived change and final working tree state in the handoff.
