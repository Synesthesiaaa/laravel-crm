## ADDED Requirements

### Requirement: Regular CRM forms require review before submission

The regular CRM form flow SHALL open a review modal after a valid `Save Record` submission event and SHALL NOT send the form payload until the user activates the modal's explicit `Confirm & Save` action.

#### Scenario: Valid form opens review without a request

- **WHEN** an authenticated user fills a regular CRM form and activates `Save Record` with valid values
- **THEN** the form shows the review modal and no submission request is sent

#### Scenario: User cancels review

- **WHEN** the review modal is open and the user activates `Back to Form` or the close control
- **THEN** the modal closes, the entered values remain unchanged, and no submission request is sent

#### Scenario: User confirms review

- **WHEN** the review modal is open and the user activates `Confirm & Save`
- **THEN** the form submits the reviewed payload through the existing regular CRM submission flow

### Requirement: Review content reflects submitted CRM fields

The review modal SHALL show one readable row for each currently visible and enabled logical CRM field that participates in submission. It SHALL omit CSRF, routing/context metadata, request identifiers, hidden fields, disabled conditional fields, and other system-only controls. Displayed values SHALL use human-readable option labels for select controls, selected option labels for multiselect controls, Yes/No for boolean checkboxes, and an explicit empty marker for blank values.

#### Scenario: Review displays entered field values

- **WHEN** a valid form contains text, date, percentage, textarea, select, multiselect, and checkbox values
- **THEN** the review modal displays each logical field once with its field label and human-readable value

#### Scenario: Hidden conditional fields are excluded

- **WHEN** a conditional field is not visible and its control is disabled
- **THEN** that field does not appear in the review modal

#### Scenario: Internal metadata is excluded

- **WHEN** the form contains CSRF, campaign, form type, lead ID, phone number, or request ID controls
- **THEN** those controls do not appear in the review modal

### Requirement: Review preserves validation and submission feedback

The review flow SHALL preserve native required-field validation and SHALL keep the user on the form when validation or submission fails. A successful confirmation SHALL retain the existing draft-clearing, reset, success-feedback, and toast behavior.

#### Scenario: Invalid form does not open review

- **WHEN** a user activates `Save Record` while a required visible field is invalid or empty
- **THEN** native validation is shown, the review modal remains closed, and no request is sent

#### Scenario: Server validation failure returns to form

- **WHEN** a user confirms a review and the server returns validation errors
- **THEN** the review modal closes, the entered values remain available for correction, and the existing error feedback is shown

#### Scenario: Successful confirmation preserves existing success behavior

- **WHEN** a user confirms a valid review and the server saves the record
- **THEN** the existing success feedback, toast, draft clearing, and form reset behavior occur

### Requirement: Review modal is accessible on every regular CRM form surface

The review modal SHALL render within the shared regular CRM form partial so it works in both full-page and widget form views. It SHALL provide a dialog label, modal semantics, keyboard focus trapping, and usable close/confirm controls.

#### Scenario: Full-page regular CRM form renders review controls

- **WHEN** a user opens a regular CRM form page
- **THEN** the page includes the review interaction and dialog markup without requiring a separate layout change

#### Scenario: Widget regular CRM form renders review controls

- **WHEN** a user opens a regular CRM form in the Quick Form widget
- **THEN** the widget includes the same review interaction and dialog behavior
