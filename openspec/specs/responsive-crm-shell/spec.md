# Responsive CRM Shell

## Purpose

Provide a consistent, accessible, responsive shared shell for the CRM while preserving existing route visibility, role checks, theme persistence, and soft-navigation behavior.

## Requirements

### Requirement: Shared shell uses semantic theme tokens

The CRM shared shell SHALL use stable semantic color, typography, spacing, border, elevation, and motion tokens for both light and dark themes. Page components MUST consume the tokens instead of introducing new hard-coded theme colors for shared controls.

#### Scenario: Theme switch preserves shell readability

- **WHEN** an authenticated user switches between light and dark mode
- **THEN** the sidebar, header, content surface, controls, borders, focus rings, and status text remain readable and visibly distinguishable without a full page reload

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

### Requirement: Shared controls support keyboard and touch operation

The shared shell controls SHALL provide visible `:focus-visible` treatment, semantic accessible names, pressed/expanded/disabled state where applicable, and a minimum 44 by 44 CSS pixel interactive target for icon controls.

#### Scenario: Keyboard user traverses the shell

- **WHEN** a keyboard user tabs through the sidebar, header, and page content
- **THEN** focus indicators remain visible, the focus order follows the visual order, and no control is reachable only by hover

### Requirement: Shared layout respects reduced motion and content priority

The shared shell SHALL respect `prefers-reduced-motion: reduce`, reserve space for sticky chrome, and use mobile-first responsive gutters so primary content remains visible before secondary content.

#### Scenario: Reduced-motion user loads a page

- **WHEN** the browser prefers reduced motion
- **THEN** shell transitions and decorative entrance animations are disabled or reduced while navigation and content remain fully usable

### Requirement: Soft navigation keeps persistent chrome functional

The shared shell SHALL remain functional after a soft-navigation swap of `#main-layout`, including theme toggling, sidebar controls, global search, notifications, user menu, and telephony indicators.

#### Scenario: User returns to a page through soft navigation

- **WHEN** a user navigates from a page to another page and back without a hard reload
- **THEN** persistent controls respond on their first interaction and no duplicate page lifecycle behavior is introduced

### Requirement: The shared notification center is responsive and lifecycle-safe

The shared CRM shell SHALL render the notification control without horizontal viewport overflow, SHALL keep the bell and panel controls at least 44 by 44 CSS pixels where practical, SHALL present a desktop popover and a small-screen viewport-safe panel, and SHALL preserve notification state and operability across supported 375px, 768px, 1024px, and 1440px viewport widths. The shell SHALL destroy the previous notification component cleanly before soft-navigation replacement.

#### Scenario: Notification panel opens at 375px

- **WHEN** an authenticated user opens notifications at a 375px viewport width
- **THEN** all notification copy and controls remain within the viewport without horizontal scrolling
- **AND** the details modal has a visible close control and unobscured focused elements

#### Scenario: Notification panel opens on desktop

- **WHEN** an authenticated user opens notifications at a 1440px viewport width
- **THEN** the panel is aligned to the bell, uses the existing shell design tokens, and does not obscure unrelated persistent navigation unnecessarily

#### Scenario: Reduced motion is requested

- **WHEN** the user has enabled reduced motion
- **THEN** notification panel and modal transitions are removed or reduced without changing functionality

#### Scenario: Shell is replaced by soft navigation

- **WHEN** the current `#main-layout` is replaced
- **THEN** notification timers, requests, focus state, and realtime subscriptions from the old shell instance are cleaned up before the new instance initializes
