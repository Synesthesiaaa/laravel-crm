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

The system SHALL omit Agent Screen navigation, dashboard cards, and global-search links for all users when Agent Screen access is disabled.

#### Scenario: Authenticated user views navigation while disabled

- **WHEN** an authenticated user views an authenticated page while Agent Screen access is disabled
- **THEN** the page does not render the Agent Screen navigation link

#### Scenario: Authenticated user searches while disabled

- **WHEN** an authenticated user requests global search results while Agent Screen access is disabled
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

### Requirement: Agent sees a focused primary workspace

The Agent Screen SHALL keep lead identity, campaign, call state, duration, and the primary dial or hangup action visible without requiring an advanced tool selection. Advanced telephony tools SHALL be presented through a single navigator with no more than one tool panel expanded at a time.

#### Scenario: Agent opens the screen while idle

- **WHEN** an agent opens the Agent Screen with no active call
- **THEN** lead information and the Ready call state are visible
- **AND** advanced tool tabs are visible
- **AND** no advanced tool panel is expanded by default

#### Scenario: Agent selects an advanced tool

- **WHEN** an agent activates an available tool tab
- **THEN** the selected tab exposes its existing tool controls in a related panel
- **AND** other advanced tool panels are hidden
- **AND** the selected tab exposes its selected state to assistive technology

### Requirement: Tool availability follows telephony feature gates

The Agent Screen SHALL render a tool tab only when its corresponding telephony feature is enabled, and SHALL preserve the existing feature guard in the action methods.

#### Scenario: Feature is disabled

- **WHEN** a telephony feature is disabled for the campaign
- **THEN** its tool tab and panel are not rendered
- **AND** direct action methods continue to reject the disabled operation

#### Scenario: Feature is enabled

- **WHEN** a telephony feature is enabled for the campaign
- **THEN** its tool tab can reveal the existing panel without changing its endpoint behavior

### Requirement: Telephony shortcuts reveal their target tool

The Agent Screen transfer shortcut SHALL activate the transfer tool before bringing the tool navigator into view.

#### Scenario: Agent uses the transfer shortcut

- **WHEN** the transfer shortcut event is dispatched
- **THEN** the transfer tab becomes selected
- **AND** the transfer panel is visible
- **AND** the navigator is brought into view

### Requirement: Tool navigation is responsive and keyboard operable

The tool navigator SHALL remain usable at mobile and desktop widths, provide visible focus treatment, use tab and tabpanel semantics, and preserve at least a 44 by 44 CSS pixel target for each tab.

#### Scenario: Agent navigates tools by keyboard

- **WHEN** an agent tabs to a tool tab and activates it with Enter or Space
- **THEN** the selected tool panel changes without page navigation
- **AND** focus remains on the activated tab

#### Scenario: Agent uses a narrow viewport

- **WHEN** the Agent Screen is viewed at a narrow mobile viewport
- **THEN** the navigator can scroll within its own row without causing page-level horizontal overflow
- **AND** the primary call controls remain visible before advanced tool content
