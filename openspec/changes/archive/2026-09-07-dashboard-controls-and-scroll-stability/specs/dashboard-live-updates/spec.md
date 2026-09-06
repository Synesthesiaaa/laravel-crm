## ADDED Requirements

### Requirement: Background updates preserve interaction stability
Background dashboard refreshes MUST preserve scroll position and defer while a dialog, focused input, or recent scrolling interaction is active. Deferred updates MUST resume after interaction ends. A superseded response MUST NOT overwrite a newer navigation or pagination action. Navigation MUST release outgoing modal state and its scroll lock.

#### Scenario: Idle refresh while scrolled
- **WHEN** a background refresh completes after scrolling has stopped
- **THEN** the page retains its scroll position and remains interactive

#### Scenario: Refresh while dialog is open
- **WHEN** a dashboard update arrives while a dialog is open
- **THEN** refresh waits until the dialog is dismissed

#### Scenario: Pagination races an older refresh
- **WHEN** a pagination navigation supersedes an older background request
- **THEN** the older response cannot replace the requested page
