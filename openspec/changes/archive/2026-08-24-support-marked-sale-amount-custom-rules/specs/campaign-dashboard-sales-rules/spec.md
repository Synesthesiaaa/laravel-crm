## MODIFIED Requirements

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
