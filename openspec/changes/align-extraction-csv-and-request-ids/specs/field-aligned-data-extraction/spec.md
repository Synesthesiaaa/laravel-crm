## ADDED Requirements

### Requirement: Field Logic-aligned CSV exports
The system SHALL export each selected form table using a deterministic column layout. It SHALL place present `id`, `date`, `request_id`, and `agent` columns first; then present configured Form Field columns in ascending `field_order` and ID order; then present unconfigured non-system columns; and finally present `created_at` and `updated_at` columns.

#### Scenario: Export a configured form table
- **WHEN** an authorized user exports a form table containing configured and legacy columns
- **THEN** the CSV header and every row use the canonical layout with configured fields ordered by Field Logic

#### Scenario: Export includes database identifiers and timestamps
- **WHEN** a selected table contains `id`, `created_at`, and `updated_at`
- **THEN** all three values are included in the CSV, with `id` in the leading system-column group and timestamps at the end

#### Scenario: Export a table with stale Field Logic metadata
- **WHEN** a configured field no longer exists in the selected table
- **THEN** the CSV omits that absent field without failing the export

#### Scenario: Export a table with legacy unconfigured data
- **WHEN** a selected table contains a non-system column that has no Form Field configuration
- **THEN** the CSV includes that column after configured fields and before timestamps

#### Scenario: Export an empty table
- **WHEN** an authorized user exports a selected existing table with no rows in the requested date range
- **THEN** the CSV writes the canonical header and completes successfully

### Requirement: Existing extraction value formatting is retained
The system SHALL retain percentage suffix formatting for configured percentage fields after applying the canonical CSV layout.

#### Scenario: Export a percentage field
- **WHEN** a selected row contains a configured percentage field with a numeric value
- **THEN** the CSV emits that value with the percentage suffix in its Field Logic-defined column
