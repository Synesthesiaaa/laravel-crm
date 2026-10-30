## Why

Creating CRM accounts individually does not scale to large staff directories. Operators need a repeatable CSV script that preserves account validation and credential handling without overwriting existing users or provisioning external telephony systems.

## What Changes

- Add `users:import` with streaming CSV input, bounded batches, dry-run validation, duplicate skipping, and a credential-free error report.
- Reuse the user creation service with an explicit option to suppress VICIdial provisioning; existing callers retain their behavior.
- Include an example CSV, operator instructions, and automated command and service tests.

## Capabilities

### New Capabilities

- `bulk-user-import`: Safe, bounded-memory creation of CRM users from local CSV files.

### Modified Capabilities

None.

## Impact

Console command, user creation service, tests, and documentation. No HTTP route, UI, migration, or new Composer dependency. A temporary PDO SQLite identity index provides bounded-memory duplicate detection, including during dry runs. No live user import is performed as part of implementation.
