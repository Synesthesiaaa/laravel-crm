# Agent Screen Access

## Purpose

Control global visibility and endpoint access for the Agent Screen and Agent Capture surfaces through a Super Admin-managed feature flag.

## Requirements

### Requirement: Agent Screen access is disabled by default

The system SHALL persist Agent Screen access through the existing system-settings feature-flag mechanism, and SHALL treat the flag as disabled when no saved value exists.

#### Scenario: No Agent Screen setting exists

- **WHEN** the Agent Screen access setting has never been saved
- **THEN** the feature service reports Agent Screen access as disabled

### Requirement: Super Admin can control Agent Screen access

The Super Admin Telephony Features configuration SHALL provide a labeled Agent Screen access control and SHALL persist the submitted enabled or disabled value through the existing configuration update flow.

#### Scenario: Super Admin enables Agent Screen access

- **WHEN** a Super Admin submits the Telephony Features form with Agent Screen access enabled
- **THEN** the system persists the enabled value
- **AND** subsequent feature checks report Agent Screen access as enabled

#### Scenario: Super Admin disables Agent Screen access

- **WHEN** a Super Admin submits the Telephony Features form with Agent Screen access disabled
- **THEN** the system persists the disabled value
- **AND** subsequent feature checks report Agent Screen access as disabled

### Requirement: Disabled Agent Screen surfaces are hidden

The system SHALL omit Agent Screen navigation and global-search links for non-Super Admin users when Agent Screen access is disabled.

#### Scenario: Regular user views navigation while disabled

- **WHEN** a non-Super Admin user views an authenticated page while Agent Screen access is disabled
- **THEN** the page does not render the Agent Screen navigation link

#### Scenario: Regular user searches while disabled

- **WHEN** a non-Super Admin user requests global search results while Agent Screen access is disabled
- **THEN** the results do not include the Agent Screen entry

### Requirement: Disabled Agent Screen surfaces reject direct access

The system SHALL reject direct non-Super Admin requests to the Agent Screen page, Agent Capture webform page, and Agent Capture submission endpoint while Agent Screen access is disabled.

#### Scenario: Regular user requests the Agent Screen page while disabled

- **WHEN** a non-Super Admin user requests the Agent Screen page while Agent Screen access is disabled
- **THEN** the system returns HTTP 403

#### Scenario: Regular user requests an Agent Capture webform while disabled

- **WHEN** a non-Super Admin user requests an Agent Capture webform while Agent Screen access is disabled
- **THEN** the system returns HTTP 403

#### Scenario: Regular user submits Agent Capture data while disabled

- **WHEN** a non-Super Admin user submits Agent Capture data while Agent Screen access is disabled
- **THEN** the API returns HTTP 403 with the existing feature-disabled JSON shape

### Requirement: Enabled Agent Screen surfaces remain available

The system SHALL preserve the existing Agent Screen navigation, search entry, page, Agent Capture webform, and capture submission behavior when Agent Screen access is enabled.

#### Scenario: Regular user uses Agent Screen while enabled

- **WHEN** a non-Super Admin user views navigation or global search and requests the Agent Screen page while Agent Screen access is enabled
- **THEN** the Agent Screen links are rendered
- **AND** the Agent Screen page returns HTTP 200

#### Scenario: Super Admin manages Agent Screen while disabled

- **WHEN** a Super Admin opens the configuration area while Agent Screen access is disabled
- **THEN** the Super Admin can view the Agent Screen access control
- **AND** the existing Super Admin Agent Screen configuration route remains available
