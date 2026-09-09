## MODIFIED Requirements

### Requirement: Shared navigation is responsive and role-aware

The shared navigation SHALL preserve existing route and role visibility rules while presenting grouped destinations with a clear active state. Its DOM SHALL use an `aside` landmark containing a labeled `nav` landmark without redundant navigation semantics on the `aside`. At desktop widths it SHALL support collapsed and expanded sidebar modes; at mobile widths it SHALL open as an accessible off-canvas drawer with a visible close action and no page-level horizontal overflow. Persistent floating widgets SHALL remain fixed overlays with reserved internal dimensions and SHALL not participate in normal document flow when they open, close, hydrate, or load embedded content.

#### Scenario: Mobile user opens navigation

- **WHEN** a user activates the navigation button at a viewport narrower than the desktop breakpoint
- **THEN** the drawer opens above the page with an accessible label, the background is dismissible, the close action is reachable, and activating a destination closes the drawer

#### Scenario: Current route is identifiable

- **WHEN** a user visits a route visible in the sidebar
- **THEN** the matching destination has a persistent visual active state and an equivalent accessible state without relying on color alone

#### Scenario: Widget content hydrates

- **WHEN** a floating phone or Quick Form widget changes its open state or hydrates persisted dimensions
- **THEN** the document's normal-flow content does not move and the widget retains a usable keyboard/focus target
