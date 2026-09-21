## ADDED Requirements

### Requirement: Administrators can mark numeric form fields as sale amounts
The system SHALL persist a boolean sale-amount designation for each form field. The Field Logic add and edit forms SHALL expose the designation for numeric fields, and the field list SHALL show whether each field is marked.

#### Scenario: Mark a numeric field while adding it
- **WHEN** an administrator submits a new field with type `number` and the sale-amount checkbox selected
- **THEN** the field is saved with its sale-amount designation enabled

#### Scenario: Mark a numeric field while editing it
- **WHEN** an administrator updates an existing numeric field with the sale-amount checkbox selected
- **THEN** the field is saved with its sale-amount designation enabled

#### Scenario: A non-numeric field cannot be a sale amount
- **WHEN** an administrator submits a non-numeric field type with the sale-amount flag enabled
- **THEN** the field is saved with the sale-amount designation disabled

### Requirement: Marked form submissions count as sales
The system SHALL count a form submission as one sale when at least one active numeric field marked as a sale amount contains a non-null, non-empty numeric value. A submission SHALL count at most once regardless of how many marked fields contain values.

#### Scenario: One marked value creates one sale
- **WHEN** a form submission contains a numeric value in one marked field
- **THEN** the dashboard sales count increases by one

#### Scenario: Multiple marked values still create one sale
- **WHEN** a form submission contains numeric values in multiple marked fields
- **THEN** the dashboard sales count increases by one for that submission

#### Scenario: Empty marked values do not create sales
- **WHEN** every marked field in a submission is null or empty
- **THEN** that submission is excluded from the dashboard sales count

### Requirement: Marked values contribute to dashboard sale amounts
The system SHALL sum all non-empty numeric marked-field values on each qualifying submission and include the sum in the dashboard sale amount for that submission's agent. The rolling KPI SHALL use the configured hour window, and the agent leaderboard SHALL use its month-to-date date range.

#### Scenario: Sum multiple sale amounts on one submission
- **WHEN** a submission contains `100.00` and `25.50` in two marked fields
- **THEN** the submission contributes one sale and `125.50` to its agent's sale amount

#### Scenario: Exclude unmarked fields
- **WHEN** a submission contains values in both marked and unmarked numeric fields
- **THEN** only marked fields contribute to its sale amount

#### Scenario: Campaigns without marked fields use the existing fallback
- **WHEN** a campaign has no active marked sale-amount fields
- **THEN** dashboard sales continue to use the existing sale-disposition and lead-data amount behavior

### Requirement: Dashboard data remains safe across dynamic form schemas
The system SHALL only query marked field columns that exist on the registered form table and SHALL ignore null, empty, or non-numeric values without failing the dashboard request.

#### Scenario: A configured column is absent
- **WHEN** a marked field has no corresponding physical column on its form table
- **THEN** the dashboard ignores that field and continues aggregating other valid fields

#### Scenario: A marked value is malformed
- **WHEN** a stored marked value is non-numeric
- **THEN** the value contributes neither to the sale count nor sale amount, and the dashboard still renders
