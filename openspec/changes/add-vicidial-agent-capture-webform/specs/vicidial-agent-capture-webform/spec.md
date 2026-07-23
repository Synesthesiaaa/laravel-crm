## ADDED Requirements

### Requirement: Campaign webform configuration

The system SHALL allow a super administrator to select one active CRM Form belonging to a campaign as that campaign's VICIdial webform configuration. The selection SHALL be nullable, and the system SHALL reject inactive or cross-campaign forms.

#### Scenario: Administrator selects a valid campaign form

- **WHEN** a super administrator submits a campaign code and an active Form belonging to that campaign
- **THEN** the system SHALL persist the Form ID on the campaign and expose the campaign's generated VICIdial URL

#### Scenario: Administrator submits a form from another campaign

- **WHEN** a super administrator submits a Form ID whose `campaign_code` differs from the submitted campaign
- **THEN** the system SHALL reject the request and SHALL NOT change the campaign's selected webform

#### Scenario: Selected form becomes inactive

- **WHEN** the selected Form is deactivated or soft-deleted
- **THEN** the campaign webform SHALL be treated as unconfigured and SHALL NOT render a submittable capture form

### Requirement: VICIdial URL generation and call prefill

The system SHALL generate a `VAR`-prefixed URL for each configured campaign using VICIdial `--A--field--B--` substitutions. The URL SHALL always include `lead_id` and `phone_number`, and SHALL include each distinct Agent Screen `vici_field` whose direction is `get` or `both`. The webform SHALL map incoming `get`/`both` values to Agent Screen field keys before rendering.

#### Scenario: Generated URL contains mapped call variables

- **WHEN** a campaign has ordered Agent Screen Fields mapped to `first_name` (`get`), `email` (`both`), and `comments` (`post`)
- **THEN** the generated URL SHALL contain one placeholder each for `lead_id`, `phone_number`, `first_name`, and `email`, and SHALL omit the `comments` placeholder

#### Scenario: Call loads mapped values

- **WHEN** VICIdial requests the webform with `lead_id=123`, `phone_number=15551234567`, and `first_name=Ada`
- **THEN** the response SHALL retain the lead metadata and SHALL prefill the Agent Screen field mapped to `first_name` with `Ada`

#### Scenario: Query values cannot select another campaign

- **WHEN** the route path is `/agent-webforms/mbsales` and the query string includes `campaign=othercamp`
- **THEN** the rendered form SHALL remain scoped to `mbsales` and SHALL ignore the query campaign value for configuration and attribution

### Requirement: Authenticated frame-safe capture form

The system SHALL provide `GET /agent-webforms/{campaign}` behind CRM authentication. A configured response SHALL render the selected Form name and only the ordered Agent Screen Fields, including existing field types, widths, required flags, options, and visibility rules. The page SHALL not render the CRM navigation, softphone controls, or telephony polling.

#### Scenario: Authenticated agent opens a configured webform

- **WHEN** an authenticated Agent requests a campaign with a valid selected form and configured Agent Screen Fields
- **THEN** the response SHALL render a compact capture form with the selected Form name and mapped field values

#### Scenario: Unauthenticated user opens a webform

- **WHEN** a browser without a CRM session requests the webform
- **THEN** the system SHALL follow the existing CRM login flow and SHALL NOT render a submittable capture form anonymously

#### Scenario: Campaign has no valid configuration

- **WHEN** an authenticated Agent requests a campaign with no selected active Form or with no configured fields
- **THEN** the response SHALL show a clear no-configuration state and SHALL omit the submit action

### Requirement: Agent Capture Record submission

The webform SHALL submit through the existing authenticated Agent Capture API. The payload SHALL identify the server-side route campaign, `lead_id`, `phone_number`, capture data, and visible fields. The system SHALL preserve existing visible-required validation, Agent Capture Record persistence, percentage normalization, optional call-session ownership checks, and `post`/`both` writeback to writable VICIdial fields.

#### Scenario: Agent saves a valid capture

- **WHEN** an authenticated Agent submits visible valid capture values from the webform
- **THEN** the system SHALL create an Agent Capture Record attributed to the authenticated user and SHALL return success feedback in the frame

#### Scenario: Visible required field is empty

- **WHEN** an authenticated Agent submits a visible required Agent Screen Field with an empty value
- **THEN** the existing capture validation SHALL reject the request, retain entered values, and show a field-level error

#### Scenario: Hidden required field is omitted

- **WHEN** a required Agent Screen Field is hidden by its visibility rule and is not included in `visible_fields`
- **THEN** the existing capture validation SHALL allow the submission without requiring that hidden field

#### Scenario: Writable BOTH field is saved

- **WHEN** an Agent submits a field mapped to a writable VICIdial field with direction `both`
- **THEN** the system SHALL persist the capture record and SHALL include that mapped value in the existing VICIdial `update_fields` writeback payload
