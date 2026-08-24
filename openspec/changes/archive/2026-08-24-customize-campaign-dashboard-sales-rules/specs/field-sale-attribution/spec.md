## MODIFIED Requirements

### Requirement: Marked form submissions count as sales
The system SHALL count sales according to the selected campaign's dashboard sales mode. In legacy mode, a form submission SHALL count as one sale when at least one active numeric field marked as a sale amount contains a non-null, non-empty numeric value. In custom mode, a form submission SHALL count as one sale when it matches at least one configured tag condition. A submission SHALL count at most once in either mode.

#### Scenario: One marked value creates one legacy sale
- **WHEN** a legacy-mode form submission contains a numeric value in one marked field
- **THEN** the dashboard sales count increases by one

#### Scenario: Multiple marked values still create one legacy sale
- **WHEN** a legacy-mode form submission contains numeric values in multiple marked fields
- **THEN** the dashboard sales count increases by one for that submission

#### Scenario: Empty marked values do not create legacy sales
- **WHEN** every marked field in a legacy-mode submission is null or empty
- **THEN** that submission is excluded from the dashboard sales count

#### Scenario: Matching tag creates one custom sale
- **WHEN** a custom-mode form submission matches one or more accepted tag values
- **THEN** the dashboard sales count increases by one for that submission

#### Scenario: Marked amount alone does not create a custom sale
- **WHEN** a custom-mode form submission contains a marked numeric amount but matches no configured tag condition
- **THEN** that submission is excluded from the dashboard sales count

### Requirement: Marked values contribute to dashboard sale amounts
The system SHALL persist capture timestamps for new form submissions and calculate Dashboard Sales, Top Agent, per-form breakdown, campaign leaderboard, and sales-derived campaign report metrics from one campaign-scoped attribution mode. Legacy mode SHALL use non-empty numeric values in active fields marked as sale amounts. Custom mode SHALL use only submissions matching configured tag rules and SHALL sum each matching form's optional configured amount field at most once per submission. The dashboard SHALL accept a selected date, start time, and end time, defaulting to the current date from `06:00` inclusive through `18:00` exclusive, and SHALL apply that same range to every selected-range sales metric. The dashboard SHALL show the Sales and Top Agent cards, SHALL NOT render a Calls KPI card, and SHALL NOT use campaign disposition records or lead-data amounts as a fallback.

#### Scenario: Sum multiple marked sale amounts on one legacy submission
- **WHEN** a legacy-mode submission contains `100.00` and `25.50` in two marked fields within the selected range
- **THEN** the submission contributes one sale and `125.50` to its agent's and form's sale amount

#### Scenario: Exclude unmarked fields in legacy mode
- **WHEN** a legacy-mode submission contains values in both marked and unmarked numeric fields
- **THEN** only marked fields contribute to its sale amount

#### Scenario: Sum one optional amount in custom mode
- **WHEN** a custom-mode submission matches several conditions and its configured amount field contains `125.50`
- **THEN** the submission contributes one sale and `125.50` once to its agent's and form's sale amount

#### Scenario: Custom count-only form contributes no amount
- **WHEN** a custom-mode submission qualifies for a form with no configured amount field
- **THEN** the submission contributes one sale and zero sale amount

#### Scenario: New qualifying submission is included in the selected range
- **WHEN** a user saves a qualifying legacy or custom submission inside the selected date and time range
- **THEN** the stored row has capture timestamps and contributes according to the campaign's sales mode

#### Scenario: Default business-day range
- **WHEN** an authenticated user opens the dashboard without sales filter parameters
- **THEN** Sales, Top Agent, leaderboard, and the per-form breakdown use the current date from `06:00` inclusive through `18:00` exclusive

#### Scenario: Custom business-day range
- **WHEN** a user submits a valid date, start time, and end time in the sales filter
- **THEN** Sales, Top Agent, leaderboard, and every per-form total use that selected range

#### Scenario: Exclude submissions at the range end
- **WHEN** a qualifying submission has a capture timestamp equal to the selected end time
- **THEN** it is excluded from the selected range

#### Scenario: Sales use no disposition fallback
- **WHEN** a campaign has no valid qualifying legacy fields or custom rules but has sale disposition records
- **THEN** the Sales total is zero and the form breakdown is empty

#### Scenario: Calls card is not rendered
- **WHEN** an authenticated user opens the dashboard
- **THEN** the KPI-card row does not contain a Calls card

#### Scenario: Top Agent reflects qualifying sales
- **WHEN** agents have different numbers of qualifying sales within the selected range
- **THEN** the Top Agent card identifies the agent with the highest qualifying sale count and shows that agent's qualifying sale count and total value

#### Scenario: Daily campaign leaderboard reflects the selected range
- **WHEN** multiple campaign agents have qualifying sales inside and outside the selected date/time range
- **THEN** the leaderboard includes qualifying agents from the selected range, ranks by sales count descending then total amount descending then agent name, and excludes sales outside the range

#### Scenario: Agent leaderboard totals summarize the selected range
- **WHEN** the dashboard renders qualifying agents for the selected date/time range
- **THEN** both the visible leaderboard and its modal show a `Total` row with the combined sales count and sale amount for the rendered agent rows

#### Scenario: Top Agent leaderboard modal shows all sale amounts
- **WHEN** the user hovers, clicks, or focuses the Top Agent card
- **THEN** the dashboard opens a modal listing the campaign leaderboard from rank 1 through the last qualifying agent with each agent's sales count and total sale amount

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
The system SHALL only query legacy marked fields or custom tag and amount columns that resolve to existing columns on an active registered form table. It SHALL ignore null, empty, malformed, or stale values without failing the dashboard request and SHALL never use submitted identifiers as unvalidated table or column names.

#### Scenario: A configured column is absent
- **WHEN** a legacy marked field or custom rule field has no corresponding physical column on its form table
- **THEN** the dashboard ignores that field or rule and continues aggregating other valid fields

#### Scenario: A legacy marked value is malformed
- **WHEN** a stored marked value is non-numeric
- **THEN** the value contributes neither to the legacy sale count nor sale amount, and the dashboard still renders

#### Scenario: A custom amount value is malformed
- **WHEN** a custom-mode submission matches a tag condition but its optional amount value is empty or non-numeric
- **THEN** the submission contributes one sale, contributes zero amount, and the dashboard still renders

#### Scenario: An untrusted field identifier is submitted
- **WHEN** a dashboard configuration request contains a table or field identifier outside the selected campaign's registered metadata
- **THEN** validation rejects it before any dynamic query can use it
