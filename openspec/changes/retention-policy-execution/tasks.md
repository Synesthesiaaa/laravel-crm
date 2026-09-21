## 1. Execution Schema and Model

- [x] 1.1 Add execution columns and backfill existing policies to recurring daily 03:00 schedules.
- [x] 1.2 Add execution fields and datetime casts to `DataRetentionPolicy`.
- [x] 1.3 Add model and migration coverage for defaults, casts, and legacy backfill.

## 2. Schedule Calculation and Validation

- [x] 2.1 Add `DataRetentionScheduleService` for one-time, daily, weekly, and monthly next-run calculations.
- [x] 2.2 Validate execution settings conditionally in `UpsertDataRetentionPolicyRequest`.
- [x] 2.3 Add schedule calculation and invalid-combination tests.

## 3. Due and Manual Execution

- [x] 3.1 Refactor `DataRetentionService` into `runDue()` and policy-specific `runPolicy()` execution.
- [x] 3.2 Record success, failed, and skipped status, errors, affected counts, and next runs.
- [x] 3.3 Implement one-time deactivation, recurring advancement, and manual recurring schedule preservation.
- [x] 3.4 Add lifecycle tests for due, future, manual, scheduled, failed, and skipped policies.

## 4. Routes and Scheduler

- [x] 4.1 Add Super Admin Run Now and policy-only DELETE routes and controller actions.
- [x] 4.2 Change the retention scheduler to poll due policies every minute with overlap protection.
- [x] 4.3 Add route, authorization, action, and scheduler tests.

## 5. Admin Interface

- [x] 5.1 Add Run Once/Recurring execution controls with conditional schedule fields.
- [x] 5.2 Replace Deactivate with Edit, Run Now, and Delete actions with confirmation prompts.
- [x] 5.3 Display next run, last run, status, error, and affected count responsively.
- [x] 5.4 Add feature assertions for conditional controls and policy lifecycle actions.

## 6. Verification and Specification Sync

- [x] 6.1 Format changed PHP files with Laravel Pint.
- [x] 6.2 Run focused retention tests and the complete PHPUnit suite.
- [x] 6.3 Verify migration status, routes, scheduler registration, command output, and browser flow.
- [x] 6.4 Sync the completed delta into the main `data-retention` OpenSpec specification.
