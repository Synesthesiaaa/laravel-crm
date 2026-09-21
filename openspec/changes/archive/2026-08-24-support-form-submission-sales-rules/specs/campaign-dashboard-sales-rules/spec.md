## MODIFIED Requirements

### Requirement: Administrators can configure campaign sales rules
The system SHALL allow Admin and Super Admin users to configure sales attribution for a selected active campaign using one or more form rule groups. Each group SHALL reference a form belonging to that campaign, choose one trigger (`form`, `tag`, or `marked_amount`), optionally reference a numeric amount field, and satisfy the trigger-specific requirements.

#### Scenario: Configure a form-submission trigger

- **WHEN** an authorized administrator selects the form trigger for an active campaign form
- **THEN** every valid submission of that form qualifies as one sale, even when no numeric field is configured

#### Scenario: Configure a Yes/No tag trigger

- **WHEN** an authorized administrator selects the tag trigger and configures a text/select field with accepted values such as `Yes` or `No`
- **THEN** only submissions whose configured tag values match qualify as sales

#### Scenario: Configure a marked sale-amount trigger

- **WHEN** an authorized administrator selects the marked-amount trigger and a numeric field marked `is_sale_amount`
- **THEN** submissions with a numeric value in that marked field qualify once and the field value contributes to the sale amount

#### Scenario: Configure an optional amount field

- **WHEN** an authorized administrator configures a form or tag trigger with a registered numeric amount field
- **THEN** matching submissions contribute the numeric field value to sale amount, while attribution still follows the selected form or tag trigger

#### Scenario: Reject an invalid trigger configuration

- **WHEN** a submitted trigger is unknown, a form trigger contains tag conditions, a tag trigger has no complete condition, or a marked-amount trigger uses an unmarked field
- **THEN** validation fails and the existing dashboard configuration remains unchanged

#### Scenario: Reject a cross-campaign field

- **WHEN** a submitted form, tag field, or amount field does not belong to the selected campaign and form
- **THEN** validation fails and the existing dashboard configuration remains unchanged

### Requirement: Custom sales rules use normalized OR matching
The system SHALL trim and case-normalize configured tag values for comparison when a rule uses the tag trigger. A tag-triggered form submission SHALL qualify when any configured condition contains its normalized field value, and a submission SHALL count at most once even when multiple conditions match. Form-triggered submissions SHALL qualify without evaluating tag values.

#### Scenario: Match values regardless of case and surrounding whitespace

- **WHEN** an accepted value is `Yes` and a stored tag value is ` yes `
- **THEN** the tag-triggered submission qualifies as a sale

#### Scenario: Match any accepted value

- **WHEN** a tag condition accepts `Yes` and `Approved` and the stored value is `Approved`
- **THEN** the tag-triggered submission qualifies as a sale

#### Scenario: Match any condition without duplicate counting

- **WHEN** two tag conditions in the same form group both match one submission
- **THEN** the submission contributes one sale and its optional amount once

#### Scenario: Count a form submission without a tag value

- **WHEN** a form-triggered rule has no tag conditions and a submission contains no numeric amount
- **THEN** the submission still contributes one sale and zero sale amount

#### Scenario: Exclude a nonmatching tag value

- **WHEN** none of a tag-triggered submission's configured tag values match an accepted value
- **THEN** the submission contributes neither a sale nor an amount
