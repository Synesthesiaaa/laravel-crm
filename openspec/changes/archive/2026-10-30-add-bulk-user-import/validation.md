# Validation

- `php artisan test --no-coverage --compact --filter='ImportUsersCommandTest|UserServiceTest|UsersControllerTest'`: 22 passed, 101 assertions. The initial command tests failed because `users:import` was absent; an additional fatal-error test failed before safe input exceptions were separated from arbitrary application exceptions.
- `php -d memory_limit=1G artisan test --no-coverage`: **831 passed, 4,518 assertions**, 124.68 seconds. Coverage was disabled because no coverage driver is available locally.
- Focused tests created 525 synthetic users over six batches with real hashing (test bcrypt rounds), encryption, and activity records. This is not a production throughput benchmark.
- `php vendor/bin/pint --test app/Console/Commands/ImportUsers.php app/Services/Imports app/Services/UserService.php tests/Feature/ImportUsersCommandTest.php`: six files passed.
- `git diff --check`: passed.
- `php artisan users:import --help`: command/options registered correctly.
- Isolated synthetic dry-run probe used an in-memory SQLite CRM identity table, streamed CSVs, chunk size 500, and a 256 MiB PHP memory limit. It did not use the configured live CRM database.
  - 5,000 records: all eligible, zero skips/rejections, zero persisted users, 7.07 seconds, 8 MiB peak additional PHP allocation.
  - 100,000 records: all eligible, zero skips/rejections, zero persisted users, 176.29 seconds, 6 MiB peak additional PHP allocation above the warmed-process baseline. The earlier 5,000-row probe ran in the same process. These figures exclude database server memory and are not total process RSS.
- The importer was verified to send no HTTP requests even with a configured synthetic active VICIdial server and imported credentials. Existing web user tests passed with the service's default behavior retained.
- `openspec validate add-bulk-user-import --strict` and `openspec validate bulk-user-import --type spec --strict`: passed. The new main spec was synchronized directly from the implemented capability delta.
- No UI/HTTP flow changed, so Impeccable, Playwright, frontend builds, and responsive checks were not applicable.
- No real CSV/accounts, external telephony provisioning, production throughput, or live MySQL concurrency/collation acceptance was tested. Database unique indexes remain authoritative; deployment-specific limitations are documented in `docs/bulk-user-import.md`.
