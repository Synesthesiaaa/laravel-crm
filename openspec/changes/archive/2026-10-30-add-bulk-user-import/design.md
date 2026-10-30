## Context

The CRM uses a scalar role, unique username/email indexes, soft deletion, Eloquent activity logging, password hashing, and encrypted telephony fields. `UserService::create` also calls VICIdial provisioning. Large imports must not load an entire CSV or identity directory into PHP memory.

## Goals / Non-Goals

**Goals:** Stream UTF-8 CSV in configurable batches; validate accounts consistently with the admin form; preview without creating accounts; skip existing usernames including deleted accounts; reject email collisions; report row numbers without credentials; preserve model casts and activity events.

**Non-goals:** Browser upload, modifying existing accounts, restoring deleted users, campaign lookup/provisioning, invitations, external VICIdial changes, or running a real import during development.

## Decisions

- Use an Artisan command, since the request is for a script and HTTP uploads introduce request timeouts. Use a focused importer service for parsing, validation, batching, and results. Defaults are implementation choices rather than user-supplied requirements.
- Required CSV columns: `username,full_name,password`. Optional columns match user creation fields, plus email and role. Default role is Agent; other roles require `--allow-privileged-roles`. Reject unknown/duplicate headers to catch misspelled credential columns.
- Batch size defaults to 500 (1–1000). Query existing usernames/emails in batches, then create through `UserService` inside per-row transactions so one unique collision cannot roll back other accounts. Preserve model events instead of raw inserts. Hashing remains the intentional primary cost.
- Add optional `syncVicidial=true` to the service; the command passes false. Existing web creation keeps its current behavior.
- Use a temporary disk-backed PDO SQLite index of identity hashes to detect repeated valid candidates across batches, also in dry run. This requires `pdo_sqlite`, bounds PHP memory, and stores no CSV credentials. Remove the index on normal completion or exception. Database uniqueness remains authoritative under concurrency and collation differences.
- Error reports are optional, exclusively created local CSV files containing record number and static field/rule errors only. Console diagnostics show at most ten row errors. A rejected row causes exit 1; invalid input/options cause exit 2; only valid/duplicate rows cause exit 0. Runtime failures stop processing safely and report partial completion without exposing SQL bindings.

## Risks / Trade-offs

- Production hashing is deliberately expensive; benchmark results must identify reduced test hashing cost rather than promise production throughput.
- A stopped import can leave completed accounts; rerun the same input to skip those usernames. No destructive rollback is offered.
- Dry run observes a snapshot over time; concurrent changes or database collation can change final results. It never persists users, activity records, or remote requests.
- CSV files contain plaintext credentials; use a private directory outside `public`, restrict access, and remove them after use. Error reports never copy source values.
- PDO SQLite and writable temporary storage must be available; fail clearly before creating users otherwise.

## Migration Plan

Deploy application files, run the documented dry run, review counts/errors, then execute the import. No schema migration. Reverting code does not delete imported accounts.

## Validation

Write command tests first for real creation, encrypted credentials, activity, no external calls, dry-run duplicates across batches, replay, deleted identities, validation/roles, malformed input, safe reports, race collisions, and multi-batch imports. Run existing user service/controller tests, Pint, and a synthetic scale check. No UI changes require Impeccable or Playwright.
