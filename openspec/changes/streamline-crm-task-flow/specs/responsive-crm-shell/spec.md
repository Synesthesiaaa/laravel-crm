## MODIFIED Requirements

### Requirement: Shared navigation is responsive and role-aware

The shared navigation SHALL preserve existing route and role visibility rules while presenting grouped destinations with a clear active state. At desktop widths it SHALL support collapsed and expanded sidebar modes; at mobile widths it SHALL open as an accessible off-canvas drawer with a visible close action and no page-level horizontal overflow. Role-scoped navigation groups SHALL be individually collapsible, SHALL expose their expanded state to assistive technology, and SHALL automatically expand the group containing the current route.

#### Scenario: Mobile user opens navigation

- **WHEN** a user activates the navigation button at a viewport narrower than the desktop breakpoint
- **THEN** the drawer opens above the page with an accessible label, the background is dismissible, the close action is reachable, and activating a destination closes the drawer

#### Scenario: Current route is identifiable

- **WHEN** a user visits a route visible in the sidebar
- **THEN** the matching destination has a persistent visual active state and an equivalent accessible state without relying on color alone

#### Scenario: User collapses a secondary navigation group

- **WHEN** a user activates a navigation group toggle
- **THEN** the group's destinations are hidden or shown as one unit
- **AND** the toggle exposes the current state through `aria-expanded`
- **AND** the active route's group remains expanded when the page loads

#### Scenario: Collapsed desktop sidebar preserves group access

- **WHEN** a user collapses the desktop sidebar
- **THEN** group controls retain an accessible name and visible focus treatment
- **AND** destination links remain reachable without depending only on hover
