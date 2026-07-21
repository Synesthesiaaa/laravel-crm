## Why

CSV exports currently follow physical database column order, which can differ from the order administrators configure in Field Logic. They also expose inconsistent layouts across dynamic form tables, while newly submitted records use opaque ULIDs instead of the date/time-prefixed numeric request IDs required by downstream extraction workflows.

## What Changes

- Export every selected form table with a predictable CSV column layout: system metadata, configured form fields in Field Logic order, remaining legacy fields, and timestamps.
- Include the database `id` and timestamp metadata in exports while excluding no configured business data.
- Format percentage columns as they are today within the new export layout.
- Generate new submission `request_id` values from a `YYYYMMDDHHMMSS` prefix and six cryptographically secure random digits, retrying when the current form table already contains the candidate value.
- Preserve all existing stored request IDs.

## Capabilities

### New Capabilities

- `field-aligned-data-extraction`: Export dynamic form records in a stable, Field Logic-aware CSV column order while retaining required system and legacy data.
- `numeric-form-request-identifiers`: Assign new form submissions a date/time-prefixed numeric request identifier with per-form collision protection.

### Modified Capabilities

<!-- None. -->

## Impact

- Affects `app/Services/ExtractionService.php`, the extraction CSV feature tests, `app/Services/FormSubmissionService.php`, and form-submission tests.
- Does not add dependencies, alter routes, modify existing records, or require client request-ID changes.
