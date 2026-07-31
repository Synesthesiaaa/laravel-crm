# Widget Split Workspace Specification

## Purpose

Provide a shared desktop workspace for the persistent Vicidial and Quick Form widgets.

## Requirements

### Requirement: Users can toggle a shared Vicidial and Quick Form split view

The authenticated widget shell MUST provide a split-view option that opens the Vicidial and Quick Form widgets as two desktop panels while retaining their existing controls and iframe content.

#### Scenario: User enables split view on desktop

- **WHEN** an authenticated user activates Split view from either widget
- **THEN** both widgets open, occupy separate bounded panels, and show an Exit split view control

#### Scenario: User exits split view

- **WHEN** the user activates Exit split view
- **THEN** both widgets return to their normal independent floating layout without logging out of Vicidial

#### Scenario: Split view is used on a narrow viewport

- **WHEN** the viewport is below the desktop split breakpoint
- **THEN** the application keeps the normal floating widget behavior and does not force two cramped panels

### Requirement: Split-view preference persists per user

The split-view preference MUST be stored through the authenticated widget-layout persistence API and restored on a later page load when the saved value is valid.

#### Scenario: User reloads after enabling split view

- **WHEN** the user opens another page or reloads the browser after enabling split view
- **THEN** the workspace restores split view without requiring the user to toggle it again

#### Scenario: Workspace persistence is unavailable

- **WHEN** the workspace layout request fails or contains invalid data
- **THEN** the widgets use their normal local defaults and the Vicidial session controls remain usable
