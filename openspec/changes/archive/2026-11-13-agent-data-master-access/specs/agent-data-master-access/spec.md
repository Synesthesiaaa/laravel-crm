## ADDED Requirements

### Requirement: Super Admin controls campaign-wide access
The system SHALL allow only Super Admin to enable agent Data Master, set own-submissions-only, and select allowed active form IDs per campaign. Unconfigured or invalid settings SHALL deny access and default ownership scope to own-only. Enabled access MUST include at least one allowed active form. New forms MUST require explicit selection. Revocation MUST apply on the next request.

#### Scenario: Super Admin grants campaign access
- **WHEN** Super Admin enables a campaign and selects forms
- **THEN** agents using that campaign can open only those forms with the configured ownership scope

#### Scenario: Lower roles attempt permission changes
- **WHEN** Agent, Team Leader, or Admin attempts to save Data Master permissions
- **THEN** the request is forbidden and settings remain unchanged

#### Scenario: Campaign access is disabled or malformed
- **WHEN** an agent requests Data Master for an unconfigured, disabled, or invalidly configured campaign
- **THEN** the page is forbidden and its navigation entry is hidden

### Requirement: Agent records remain read-only and campaign-scoped
The agent endpoint SHALL be GET-only and SHALL NOT expose edit, delete, import, or export actions. It MUST use the current session campaign and reject unpermitted forms. Own-only records MUST have matching campaign/form/record history attributed to the authenticated user ID. Missing attribution SHALL NOT be inferred from display names. Campaign-wide access SHALL show all records in permitted, exclusively mapped form storage; shared tables MUST additionally match campaign/form submission provenance.

#### Scenario: Agent attempts to widen ownership or campaign scope
- **WHEN** an agent supplies another campaign, table, user ID, or ownership flag in query parameters
- **THEN** the server still applies the session campaign and saved permission scope

#### Scenario: Own-only records have identical agent names
- **WHEN** two agents share a display name and own-only is enabled
- **THEN** each sees only records attributed to their user ID

#### Scenario: Campaign-wide visibility is enabled
- **WHEN** own-only is disabled
- **THEN** records from all users are visible within the permitted campaign forms only

#### Scenario: Agent attempts administrative writes
- **WHEN** an agent directly requests administrative Data Master edit, update, or delete routes
- **THEN** access is forbidden and no record changes

### Requirement: Agent records support bounded server filters
The system SHALL provide allowed-form selection, search across visible columns, inclusive submission date range, individual visible-column substring filters, clear filters, and server pagination. Filter combinations SHALL NOT widen permissions. Invalid forms, unknown column filters, oversized values, and reversed date ranges MUST produce validation errors. Pagination MUST preserve filters. Hidden historical columns MUST NOT be selected or searched.

#### Scenario: Agent combines filters
- **WHEN** an agent selects a permitted form, search text, dates, and multiple column values
- **THEN** the paginated results match all filters within the permitted submission scope

#### Scenario: Invalid filter is submitted
- **WHEN** an agent supplies an unknown column or a date range ending before it starts
- **THEN** validation fails without disclosing data

#### Scenario: No permitted records match
- **WHEN** valid filters return no permitted records
- **THEN** the page shows a helpful empty state and a clear-filters action

#### Scenario: The first permitted form has unavailable storage
- **WHEN** an agent opens a permitted form whose storage is missing or unavailable
- **THEN** the system returns a safe unavailable page with a permitted-form selector so the agent can load another healthy form

### Requirement: Agent Data Master follows the existing responsive interface
The system SHALL use existing configuration and table components, visible labels, keyboard-accessible controls, readable scope guidance, and contained table scrolling. It MUST remain usable at 375, 768, 1024, and 1440 CSS pixels without document overflow.

#### Scenario: Agent reviews a wide form on mobile
- **WHEN** the form table is wider than the viewport
- **THEN** controls stack and the table scrolls within its accessible region without overflowing the page
