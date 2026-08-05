## 1. Regression Tests

- [x] 1.1 Add a migration test proving unregistered physical columns are dropped while the six approved system columns remain.
- [x] 1.2 Add a shared-table test proving fields registered by any form using the table are preserved.
- [x] 1.3 Add a Data Master layout test proving unregistered physical columns are excluded.

## 2. Implementation

- [x] 2.1 Create the cleanup migration that gathers registered fields by table and drops only unregistered, non-system columns.
- [x] 2.2 Update `DataMasterService` to return registered fields and approved system columns without appending arbitrary database columns.

## 3. Verification

- [x] 3.1 Run Pint and the affected PHPUnit tests.
- [x] 3.2 Verify migration status and inspect the resulting form table columns locally.
- [x] 3.3 Document the production backup and migration commands for deployment.
