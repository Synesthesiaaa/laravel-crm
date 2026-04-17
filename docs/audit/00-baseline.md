# Phase 0 - Baseline snapshot

Captured at the start of the audit so later phases can measure improvement.

## Environment

- Laravel 12 (`composer.json`)
- PHP 8.2+ runtime, PHPUnit 11.5
- Node tooling: Vite 7, Tailwind 4, Alpine 3.15, SIP.js 0.21
- Stack: XAMPP/Windows dev, MySQL 8 (CI), SQLite in-memory (tests)

## Code style (Pint)

Command: `vendor/bin/pint --test`

- **Files scanned:** 283
- **Files with style issues:** 163
- **Notable clusters:**
  - Migrations under `database/migrations/2025_*` and `2026_02_*` trigger `class_definition`, `braces_position`, `not_operator_with_successor_space`.
  - Tests under `tests/Unit` and `tests/Feature` heavily trigger `binary_operator_spaces` and `trailing_comma_in_multiline`.
  - Reference snippets under `docs/vicidial/*.php` (`front.php`, `crm_example.php`, `crm_settings.php`) trigger many issues but are **third-party integration examples**; recommend excluding from Pint.

Recommendation: exclude `docs/` from Pint in [pint.json](../../pint.json) before running the style sweep so external reference code is not rewritten.

## Dependency audit

Command: `composer audit`

- **league/commonmark** advisories (medium):
  - `GHSA-hh8v-hgvp-g3f5` (embed extension allowed_domains bypass) - affects <=2.8.1
  - `GHSA-4v6x-c7xx-hw9f` (DisallowedRawHtml extension whitespace bypass) - affects <=2.8.0

Action: bump `league/commonmark` when a safe minor is available (transitive via Laravel; usually fixed by `composer update` on a later Laravel patch release).

`npm audit` - not captured in this pass; run in Phase 6 alongside `composer audit`.

## Test baseline

Command: `php artisan test`

- **PASS** `Tests\Unit\ExampleTest::that true is true`
- **FAIL** `Tests\Unit\Services\AuthServiceTest::attempt returns null for invalid credentials`
  - Root cause: migration [`2026_04_15_100002_extend_attendance_logs_event_type_length.php`](../../database/migrations/2026_04_15_100002_extend_attendance_logs_event_type_length.php) uses **MySQL-only** `ALTER TABLE ... MODIFY`. SQLite (used by PHPUnit per [phpunit.xml](../../phpunit.xml)) rejects the syntax and **every subsequent migration/test halts**.
  - This single migration currently gates the entire test suite on Windows/SQLite. **P1 bug**, fixed in Phase 5.

The remaining 128 tests are reported as "pending" because the suite halted on the first failure.

## Build

Command: `npm run build`

- Last successful build (per `terminals/19.txt`):
  - `app.css` 93.09 kB gzip 18.40 kB
  - `app.js` 436.05 kB gzip 120.21 kB
  - `apexcharts.js` 475.90 kB gzip 130.42 kB
  - `login-page.js` 3.80 kB gzip 1.67 kB

Notable: ApexCharts is a large chunk. It is only required on a handful of admin/dashboard/reports pages. Candidate for dynamic import in a later phase (P3).

## Tool adoption decision (recommended)

- **PHPStan / Larastan**: *not added yet* - adopting mid-audit risks false noise. Revisit after Phase 4 Pint sweep and failing-test fixes. Documented but skipped.
- **ESLint / Prettier**: *not added yet* - JS surface is small (11 modules), current style is consistent. Revisit after Phase 2.
- **Playwright**: *not added yet* - manual repro steps documented per fix is sufficient for now.

These are intentionally deferred so the audit does not become a tooling migration.
