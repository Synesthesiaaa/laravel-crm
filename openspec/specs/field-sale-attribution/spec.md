## Purpose

Allow administrators to designate numeric form fields as sale amounts and use marked submission values for dashboard sales metrics.

## Requirements

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
The system SHALL persist capture timestamps for new form submissions and calculate dashboard Sales, Top Agent, per-form breakdown, and campaign leaderboard metrics exclusively from non-empty numeric values in active fields marked as sale amounts. The dashboard SHALL accept a selected date, start time, and end time, defaulting to the current date from `06:00` inclusive through `18:00` exclusive, and SHALL apply that same range to every dashboard sales metric. The dashboard SHALL show the Sales and Top Agent cards, SHALL NOT render a Calls KPI card, and SHALL NOT use campaign disposition records or lead-data amounts as a fallback. If no valid marked sale field exists for the campaign, the dashboard SHALL return zero sales, zero amount, no Top Agent, an empty form breakdown, and an empty leaderboard.

#### Scenario: Sum multiple sale amounts on one submission
- **WHEN** a submission contains `100.00` and `25.50` in two marked fields within the selected range
- **THEN** the submission contributes one sale and `125.50` to its agent's and form's sale amount

#### Scenario: Exclude unmarked fields
- **WHEN** a submission contains values in both marked and unmarked numeric fields
- **THEN** only marked fields contribute to its sale amount

#### Scenario: New marked submission is included in the selected range
- **WHEN** a user saves a form submission with a numeric marked sale amount inside the selected date and time range
- **THEN** the stored row has capture timestamps and its amount contributes to the dashboard sales metrics

#### Scenario: Default business-day range
- **WHEN** an authenticated user opens the dashboard without sales filter parameters
- **THEN** Sales, Top Agent, and the per-form breakdown use the current date from `06:00` inclusive through `18:00` exclusive

#### Scenario: Custom business-day range
- **WHEN** a user submits a valid date, start time, and end time in the sales filter
- **THEN** Sales, Top Agent, and every per-form total use that selected range

#### Scenario: Exclude submissions at the range end
- **WHEN** a qualifying submission has a capture timestamp equal to the selected end time
- **THEN** it is excluded from the selected range

#### Scenario: Sales use no disposition fallback
- **WHEN** a campaign has no valid marked sale-amount field but has sale disposition records
- **THEN** the Sales total is zero and the form breakdown is empty

#### Scenario: Calls card is not rendered
- **WHEN** an authenticated user opens the dashboard
- **THEN** the KPI-card row does not contain a Calls card

#### Scenario: Top Agent reflects qualifying marked sales
- **WHEN** agents have different numbers of qualifying marked sales within the selected range
- **THEN** the Top Agent card identifies the agent with the highest qualifying sale count and shows that agent's qualifying sale count and total value

#### Scenario: Daily campaign leaderboard reflects the selected range
- **WHEN** multiple campaign agents have qualifying marked sales inside and outside the selected date/time range
- **THEN** the leaderboard includes the qualifying agents from the selected range, ranks by sales count descending then total amount descending then agent name, and excludes sales outside the range

#### Scenario: Top Agent leaderboard modal shows all sales amounts
- **WHEN** the user hovers, clicks, or focuses the Top Agent card
- **THEN** the dashboard opens a modal listing the campaign leaderboard from rank 1 through the last qualifying agent with each agent's sales count and total marked-form sale amount

#### Scenario: Sales breakdown modal
- **WHEN** the user hovers, clicks, or focuses the Sales card
- **THEN** the dashboard opens a modal showing the selected range's overall amount, date/time filter controls, and per-form sales counts and amounts

#### Scenario: Sales modal closes outside its hover boundary
- **WHEN** a user moves the pointer away from both the Sales card and the Sales modal box
- **THEN** the modal closes after a brief delay with a smooth leave transition

#### Scenario: Sales modal hover remains stable while opening
- **WHEN** the Sales modal appears while the pointer is still over the Sales card area
- **THEN** the backdrop does not steal the pointer target and the modal does not repeatedly open and close

### Requirement: Dashboard data remains safe across dynamic form schemas
The system SHALL only query marked field columns that exist on the registered form table and SHALL ignore null, empty, or non-numeric values without failing the dashboard request.

#### Scenario: A configured column is absent
- **WHEN** a marked field has no corresponding physical column on its form table
- **THEN** the dashboard ignores that field and continues aggregating other valid fields

#### Scenario: A marked value is malformed
- **WHEN** a stored marked value is non-numeric
- **THEN** the value contributes neither to the sale count nor sale amount, and the dashboard still renders
