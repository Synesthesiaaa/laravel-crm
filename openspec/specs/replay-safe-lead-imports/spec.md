# replay-safe-lead-imports Specification

## Purpose
TBD - created by archiving change phase2-restore-call-form-runtime-correctness. Update Purpose after archive.
## Requirements
### Requirement: Each import row has a stable replay receipt

Every CSV data row processed from a tracked `LeadImport` SHALL have one receipt keyed by the import ID and stable CSV row number. The receipt SHALL store the generated request ID, outcome, and target form record ID without copying customer field values.

#### Scenario: Row commits successfully
- **WHEN** a CSV row is accepted
- **THEN** its form record, call-history record, and completed receipt commit atomically

#### Scenario: Row fails business validation
- **WHEN** a CSV row fails deterministic form validation
- **THEN** its failed receipt and safe row error summary are recorded once
- **AND** replay SHALL NOT count or submit the same row again

### Requirement: Queue retry does not duplicate committed rows

The import job SHALL skip rows with a committed completed or failed receipt and SHALL retry rows whose transaction rolled back. It SHALL recompute final imported and failed counts from receipts rather than from one worker attempt's local counters.

#### Scenario: Worker stops after a committed row
- **WHEN** the queue worker stops after committing some rows and the job is retried
- **THEN** committed rows are skipped
- **AND** every accepted CSV row has at most one form record from that import

#### Scenario: Infrastructure failure rolls back the current row
- **WHEN** database or call-history persistence fails during one row transaction
- **THEN** that row's receipt and form changes roll back together
- **AND** the exception remains eligible for the configured queue retry

### Requirement: Imports require their tracking record

The import job SHALL verify its `LeadImport` tracking record before processing rows and SHALL stop safely when that record is missing.

#### Scenario: Tracking record is missing
- **WHEN** an import job is invoked without a valid `LeadImport`
- **THEN** it stops before submitting CSV rows
- **AND** it reports a safe failure without exposing exception details

