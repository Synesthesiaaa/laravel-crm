# Agent Workspace Focus

## Purpose

Give agents a focused call-handling workspace while keeping advanced telephony tools available when needed.

## ADDED Requirements

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
