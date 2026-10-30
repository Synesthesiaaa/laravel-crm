## ADDED Requirements

### Requirement: Stream validated CRM accounts
The system SHALL provide `users:import` for local UTF-8 CSV files with required username, full_name, and password headers, optional existing account fields, bounded batches, admin-form password strength, and Agent as the default role. Non-Agent roles SHALL require an explicit command option.

#### Scenario: Valid accounts and credentials
- **WHEN** an operator imports valid rows
- **THEN** CRM accounts are created with hashed passwords, encrypted telephony secrets, activity events, and no external provisioning requests

#### Scenario: Invalid input
- **WHEN** headers, options, rows, passwords, or roles are invalid
- **THEN** the importer reports safe diagnostics, rejects invalid rows, and returns a nonzero exit code

### Requirement: Preserve existing identities
The importer SHALL skip existing usernames including soft-deleted accounts, reject conflicting emails, and detect repeated valid usernames/emails across input batches without retaining the whole file in PHP memory. It SHALL never update or restore existing accounts.

#### Scenario: Repeat an interrupted import
- **WHEN** the same CSV is imported again
- **THEN** completed usernames are skipped and remaining valid accounts can be created

### Requirement: Preview and report safely
The importer SHALL support a dry run that persists no users or activity records and sends no external requests. It SHALL report processed, created or eligible, skipped, and rejected counts, limit console row diagnostics, and optionally create an exclusive credential-free row-error CSV. Fatal failures SHALL stop processing and describe partial completion without exposing credentials.

#### Scenario: Preview duplicates across batches
- **WHEN** a dry run sees the same valid username or email in different batches
- **THEN** it counts each eligible identity once and leaves persisted CRM state unchanged

#### Scenario: Safe report output
- **WHEN** rows fail validation or uniqueness checks
- **THEN** only record numbers and static error messages appear in diagnostics and reports
