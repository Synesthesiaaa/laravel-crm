# Responsive Activity Log Experience

## Purpose

Define the responsive, accessible presentation and lifecycle behavior of the Super Admin Activity Log without changing its existing realtime, polling, filtering, redaction, or authorization behavior.

## Requirements

### Requirement: Activity Log filters reflow for narrow screens

The Activity Log SHALL present visible labels for search, actor, action, and date filters, reflow the filter controls to the available width, and keep primary filter actions reachable without horizontal scrolling at a 375 CSS pixel viewport.

#### Scenario: Super Admin filters on a phone

- **WHEN** a Super Admin opens Activity Log at a 375px viewport
- **THEN** all filter labels and controls fit the viewport or wrap vertically, the Apply and Reset actions remain visible, and submitting filters uses the existing history request behavior

### Requirement: Activity stream remains scannable across breakpoints

The Activity Log SHALL retain timestamp, action, description, actor, severity, connection status, and expandable details while switching from aligned desktop rows to stacked narrow-screen rows. Long descriptions, identifiers, user agents, and sanitized JSON MUST wrap or scroll within their bounded detail region without causing document-level horizontal overflow.

#### Scenario: User scans activity at desktop width

- **WHEN** a Super Admin views the stream at a desktop viewport
- **THEN** timestamps, actions, descriptions, and actors align into stable columns and severity is conveyed by text plus a visible status treatment

#### Scenario: User scans activity at phone width

- **WHEN** a Super Admin views the stream at a 375px viewport
- **THEN** each entry exposes its timestamp and action before the description, actor metadata remains available, and the page does not require horizontal scrolling

### Requirement: Activity controls remain accessible and stateful

The Activity Log SHALL expose connection state as text and status, and its Follow, Pause/Resume, Clear, and expandable-entry controls SHALL expose their current state through native button semantics and accessible names.

#### Scenario: Operator pauses a live stream

- **WHEN** a Super Admin activates Pause
- **THEN** the control announces the paused state, newly received entries remain retained, and the operator's scroll position is not forced to the newest entry until following is resumed

### Requirement: Existing realtime and polling behavior is preserved

The Activity Log presentation change SHALL not alter the existing history endpoint, bounded filtering, Echo subscription, five-second polling fallback, redaction, buffer clear semantics, or Alpine cleanup on soft navigation.

#### Scenario: Viewer returns through soft navigation

- **WHEN** a Super Admin leaves Activity Log and returns using the shared soft-navigation flow
- **THEN** the viewer initializes once, polls or subscribes according to the current transport state, and does not accumulate duplicate timers or subscriptions
