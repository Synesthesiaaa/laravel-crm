## 1. Persistence and retention service

- [x] 1.1 Add the `data_retention_policies` migration, `DataRetentionPolicy` model, and `Form::retentionPolicy()` relationship.
- [x] 1.2 Add `DataRetentionService` to validate registered storage tables, delete rows at or before each active policy cutoff, update run metadata, and continue safely after per-policy failures.
- [x] 1.3 Add the `data-retention:run` Artisan command and register it as a daily non-overlapping scheduled task.

## 2. Super Admin configuration

- [x] 2.1 Add the Super Admin retention routes, request validation, and controller actions for rendering, upserting, and deactivating policies.
- [x] 2.2 Add the Data Retention tab UI with active-form selection, cutoff date input, destructive warning, policy table, and status metadata.
- [x] 2.3 Make the Field Logic form selector submit automatically on change and preserve server-side form-scoped field filtering.

## 3. Automated coverage

- [x] 3.1 Add PHPUnit coverage for policy authorization, validation, creation/update/deactivation, and retention tab rendering.
- [x] 3.2 Add service/command coverage for cutoff deletion, form isolation, run metadata, and invalid-table continuation.
- [x] 3.3 Add coverage for automatic Field Logic selector filtering, then run focused tests and Pint.

## 4. Verification and handoff

- [x] 4.1 Run the affected PHPUnit files and inspect route/schedule registration.
- [x] 4.2 Run browser verification for the Super Admin Data Retention tab and Field Logic form switching, including console errors.
- [x] 4.3 Sync the implemented behavior into OpenSpec, verify all tasks, and archive the completed change.
