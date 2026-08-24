# Responsive CRM Shell

## Purpose

Define the accessible, responsive shared shell used by authenticated CRM pages, including semantic theme tokens, navigation, persistent controls, and soft-navigation behavior.

## Requirements

### Requirement: Shared shell uses semantic theme tokens

The CRM shared shell SHALL use stable semantic color, typography, spacing, border, elevation, and motion tokens for both light and dark themes. Page components MUST consume the tokens instead of introducing new hard-coded theme colors for shared controls.

#### Scenario: Theme switch preserves shell readability

- **WHEN** an authenticated user switches between light and dark mode
- **THEN** the sidebar, header, content surface, controls, borders, focus rings, and status text remain readable and visibly distinguishable without a full page reload

### Requirement: Shared navigation is responsive and role-aware

The shared navigation SHALL preserve existing route and role visibility rules while presenting grouped destinations with a clear active state. At desktop widths it SHALL support collapsed and expanded sidebar modes; at mobile widths it SHALL open as an accessible off-canvas drawer with a visible close action and no page-level horizontal overflow.

#### Scenario: Mobile user opens navigation

- **WHEN** a user activates the navigation button at a viewport narrower than the desktop breakpoint
- **THEN** the drawer opens above the page with an accessible label, the background is dismissible, the close action is reachable, and activating a destination closes the drawer

#### Scenario: Current route is identifiable

- **WHEN** a user visits a route visible in the sidebar
- **THEN** the matching destination has a persistent visual active state and an equivalent accessible state without relying on color alone

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
