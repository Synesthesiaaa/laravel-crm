## ADDED Requirements

### Requirement: Task-oriented role-aware navigation
The system SHALL group existing destinations by operational responsibility while preserving backend authorization, named routes, campaign form links and the Agent Screen feature gate.

#### Scenario: Agent navigation
- **WHEN** an Agent opens the workspace
- **THEN** daily work and enabled campaign forms are available and restricted management links are absent

#### Scenario: Management navigation
- **WHEN** an authorized manager opens a management screen
- **THEN** its destination and containing group are active and all formerly available destinations remain reachable

### Requirement: Record-first management
Management lists SHALL place long creation forms in labelled disclosures that retain their DOM and reopen after relevant validation failures.

#### Scenario: Failed creation
- **WHEN** creation validation fails
- **THEN** the creation panel is open with old nonsecret input and visible errors

#### Scenario: Editing a row
- **WHEN** a row edit fails
- **THEN** its edit feedback remains visible without automatically opening an unrelated creation panel

### Requirement: Explicit email workspaces
Email Campaigns SHALL separate preparation, reusable templates and delivery history through accessible links and show review progress without weakening preflight or delivery authorization.

#### Scenario: Existing template
- **WHEN** a user opens Email Campaigns with a saved template
- **THEN** preparing a recipient campaign is the primary task and templates and delivery history remain directly reachable

#### Scenario: Synthetic test failure
- **WHEN** a synthetic SMTP test fails on review
- **THEN** the named synthetic error is shown without invalidating the campaign's mapping or draft

### Requirement: Accessible consistent presentation
Shared tables SHALL provide named keyboard-scroll regions. Forms, dialogs, dashboards, settings and agent surfaces SHALL use the incumbent dual-theme tokens, visible focus and responsive containment.

#### Scenario: Narrow report
- **WHEN** a table exceeds the viewport width
- **THEN** its region scrolls horizontally without widening the document and its controls remain reachable

### Requirement: Operational state preservation
Presentation transitions SHALL retain persistent phone and quick-form component roots, campaign context, call handlers and existing real-time subscriptions.

#### Scenario: Content navigation
- **WHEN** soft navigation replaces page content
- **THEN** persistent telephony roots are not recreated and operational controls retain their existing lifecycle

#### Scenario: Default floating controls
- **WHEN** no custom widget layout is saved
- **THEN** launchers occupy a bounded corner stack with bottom scrolling space, while saved custom layouts remain respected
