# Campaign Dashboard Sales Rules Specification

## Purpose

Allow administrators to configure campaign-specific sales attribution rules while preserving safe legacy field-marked behavior.
## Requirements
### Requirement: Administrators can configure campaign sales rules
The system SHALL allow Admin and Super Admin users to configure sales attribution for a selected active campaign using one or more form rule groups. Each group SHALL reference a form belonging to that campaign, one optional numeric amount field, and either one or more tag conditions containing a supported field and at least one accepted value or a numeric field marked as a sale amount with no tag conditions.

#### Scenario: Configure several campaign forms

- **WHEN** an authorized administrator configures tag conditions for multiple forms belonging to the selected campaign
- **THEN** the system saves those form groups in that campaign's dashboard configuration

#### Scenario: Configure count-only sales

- **WHEN** an authorized administrator saves a valid form rule without an amount field and with at least one tag condition
- **THEN** matching submissions contribute to the sales count but contribute zero to the sale amount

#### Scenario: Configure a marked sale-amount trigger

- **WHEN** an authorized administrator selects a numeric field marked `is_sale_amount` and saves the form rule without tag conditions
- **THEN** submissions with a numeric value in that marked field qualify once and the field value contributes to the sale amount

#### Scenario: Reject an unmarked numeric trigger

- **WHEN** an authorized administrator saves a form rule without tag conditions using a numeric field that is not marked `is_sale_amount`
- **THEN** validation fails and the existing dashboard configuration remains unchanged

#### Scenario: Reject a cross-campaign field

- **WHEN** a submitted form, tag field, or amount field does not belong to the selected campaign and form
- **THEN** validation fails and the existing dashboard configuration remains unchanged

#### Scenario: Reject an unsupported amount field

- **WHEN** a submitted amount field is not a numeric field registered for the configured form
- **THEN** validation fails with an error identifying the affected rule

### Requirement: Custom sales rules use normalized OR matching

The system SHALL trim and case-normalize stored and configured tag values for comparison. A form submission SHALL qualify when any configured condition contains its normalized field value, and a submission SHALL count at most once even when multiple conditions match.

#### Scenario: Match values regardless of case and surrounding whitespace

- **WHEN** an accepted value is `Yes` and a stored tag value is ` yes `
- **THEN** the submission qualifies as a sale

#### Scenario: Match any accepted value

- **WHEN** a condition accepts `Yes` and `Approved` and the stored value is `Approved`
- **THEN** the submission qualifies as a sale

#### Scenario: Match any condition without duplicate counting

- **WHEN** two conditions in the same form group both match one submission
- **THEN** the submission contributes one sale and its optional amount once

#### Scenario: Exclude a nonmatching value

- **WHEN** none of a submission's configured tag values match an accepted value
- **THEN** the submission contributes neither a sale nor an amount

### Requirement: Campaign sales mode is explicit and recoverable

The system SHALL use custom tag rules only when a campaign dashboard is explicitly saved in custom sales mode. Campaigns without custom mode SHALL retain marked sale-amount attribution, and administrators SHALL have an explicit reset action that returns a custom campaign to that legacy mode.

#### Scenario: Existing campaign has no custom configuration

- **WHEN** a campaign dashboard document has no custom sales mode
- **THEN** the dashboard continues using active numeric fields marked as sale amounts

#### Scenario: Campaign enters custom mode

- **WHEN** an authorized administrator saves at least one valid custom form rule
- **THEN** only the custom rules determine qualifying sales for that campaign

#### Scenario: Administrator resets custom mode

- **WHEN** an authorized administrator confirms the reset action
- **THEN** the custom sales configuration is removed and the campaign returns to marked sale-amount attribution

#### Scenario: Empty custom configuration is rejected

- **WHEN** an administrator attempts to save custom mode without a complete form rule
- **THEN** validation fails instead of silently activating legacy mode

### Requirement: Stale sales-rule references fail safely

The system SHALL resolve saved form, field, table, and column references against the campaign's active allow-listed metadata before querying. Invalid references SHALL be excluded from aggregation, SHALL NOT make the user dashboard unavailable, and SHALL be reported as actionable warnings on the admin configuration page.

#### Scenario: Saved tag column no longer exists

- **WHEN** a configured tag field has no physical column on its registered form table
- **THEN** the rule is skipped, other valid rules are aggregated, and the admin sees a warning for that field

#### Scenario: Saved form becomes inactive

- **WHEN** a configured form is no longer active for the campaign
- **THEN** the form rule is skipped without querying an arbitrary table and the admin sees a recovery warning

#### Scenario: All custom rules become stale

- **WHEN** no saved custom rule can be safely resolved
- **THEN** sales-derived results return zero and empty collections while the dashboard continues to render
