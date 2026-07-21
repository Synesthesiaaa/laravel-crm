## 1. Lock down the new extraction behavior with tests

- [x] 1.1 Extend `ExtractionExportTest` with a table containing out-of-order configured fields, a legacy column, and all system metadata; assert the exact CSV header and row order.
- [x] 1.2 Add coverage that exports an empty selected table with its canonical header and that ignores stale Field Logic columns not present in the physical table.
- [x] 1.3 Retain and run percentage-formatting assertions against the newly ordered CSV output.

## 2. Implement Field Logic-aligned CSV exports

- [x] 2.1 Add a per-table column-layout resolver in `ExtractionService` that intersects actual schema columns with registered form metadata and ordered `FormField` records.
- [x] 2.2 Stream explicit column selections, canonical headers, and ordered values using the layout: `id`, `date`, `request_id`, `agent`, configured fields, legacy fields, `created_at`, `updated_at`.
- [x] 2.3 Preserve date filtering, missing-table safeguards, percentage formatting, and bounded-memory streaming.

## 3. Lock down numeric form request IDs with tests

- [x] 3.1 Update submission feature tests to assert that new records ignore client request IDs and persist a 20-digit date/time-prefixed numeric request ID instead of a ULID.
- [x] 3.2 Add unit coverage for candidate generation and collision retry behavior, including the failure path after the configured retry limit.
- [x] 3.3 Confirm historical request IDs are not modified by the new submission path.

## 4. Implement numeric request IDs for new submissions

- [x] 4.1 Replace the ULID assignment in `FormSubmissionService` with a bounded generator using `YYYYMMDDHHMMSS` plus six digits from `random_int()`.
- [x] 4.2 Check each candidate against the target form table before insertion, retry on a collision, and surface a safe failure when all attempts are exhausted.
- [x] 4.3 Keep request-ID storage and all non-submission workflows backward compatible with existing identifier values.

## 5. Verify and align the change artifacts

- [x] 5.1 Run `vendor/bin/pint --dirty --format agent` after modifying PHP files.
- [x] 5.2 Run `php artisan test --compact tests/Feature/Admin/ExtractionExportTest.php tests/Feature/FormSubmissionTest.php tests/Unit/Services/FormSubmissionServiceTest.php`.
- [x] 5.3 Run `openspec validate align-extraction-csv-and-request-ids --strict` and mark completed tasks only after the associated verification passes.
