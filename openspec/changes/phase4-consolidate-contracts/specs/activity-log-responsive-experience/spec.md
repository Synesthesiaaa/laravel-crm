## ADDED Requirements

### Requirement: Activity Log styling survives soft navigation
The Activity Log SHALL load its scoped styles through the versioned frontend asset pipeline so the page has the same layout and overflow behavior on direct navigation and when inserted by soft navigation.

#### Scenario: Activity Log is opened through soft navigation
- **WHEN** a Super Admin navigates to Activity Log through the shared soft-navigation flow
- **THEN** its responsive styles are available without requiring a full document reload
