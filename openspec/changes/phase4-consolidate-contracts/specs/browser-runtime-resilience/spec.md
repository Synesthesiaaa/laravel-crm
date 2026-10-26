## ADDED Requirements

### Requirement: Optional browser storage cannot prevent core startup
The system SHALL guard browser storage access and validate preference payload shapes before applying them. If storage is denied or malformed, Alpine SHALL still initialize and essential form and call controls SHALL remain available.

#### Scenario: Recent-search storage is malformed or unavailable
- **WHEN** recent-search JSON is invalid or localStorage access throws
- **THEN** the application starts with an empty recent-search list and continues to initialize Alpine

### Requirement: Repeated form controls have unique accessible identifiers
Shared form controls SHALL accept an explicit identifier, and repeated user forms SHALL render unique control IDs with matching labels and error/help references.

#### Scenario: Create and inline edit forms coexist
- **WHEN** a user administration page renders create and inline edit forms with matching input names
- **THEN** their control IDs are unique and each label targets its own control

### Requirement: Realtime transport follows the configured broadcaster
The browser SHALL select Reverb or Pusher from an explicit driver setting and SHALL use that driver's own key and connection options. A missing key or unsupported driver SHALL disable Echo initialization without selecting another broadcaster implicitly.

#### Scenario: Reverb is configured
- **WHEN** the configured driver is Reverb and its key is present
- **THEN** Echo initializes with Reverb host, port, scheme, and transports

#### Scenario: Pusher is configured
- **WHEN** the configured driver is Pusher and its key is present
- **THEN** Echo initializes with the Pusher key and cluster

#### Scenario: Configured key is missing
- **WHEN** the selected driver's key is empty
- **THEN** Echo remains disabled and no alternate driver is selected

### Requirement: User edits submit an explicit editing marker
The user edit form SHALL include its `_editing` value in ordinary form markup so the marker is present without JavaScript DOM mutation.

#### Scenario: User edit is submitted
- **WHEN** an administrator submits an inline user edit
- **THEN** the form includes the current user's `_editing` identifier and no submit-time JavaScript exception is raised
