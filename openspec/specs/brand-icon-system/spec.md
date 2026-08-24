# brand-icon-system Specification

## Purpose
TBD - created by archiving change restore-brand-icons-charts. Update Purpose after archive.
## Requirements
### Requirement: Shared icons use a consistent semantic SVG system

The CRM SHALL render shared interface icons through the existing Blade icon component using a consistent outline family, stroke treatment, alignment, and token-based sizing. Decorative icons beside visible labels MUST remain hidden from the accessibility tree, while meaningful standalone icon controls MUST expose an accessible name through their containing control or an explicit label.

#### Scenario: Shared icon renders at a standard size

- **WHEN** a page renders an icon through the shared component without a custom size
- **THEN** the icon uses the component's standard size and stroke treatment without changing the surrounding layout

#### Scenario: Icon-only control exposes meaning

- **WHEN** a user encounters a control whose visible content is only an icon
- **THEN** the control has an accessible name and any applicable expanded, pressed, selected, or disabled state is exposed through native semantics

### Requirement: Icon states remain readable across themes and interactions

The shared icon system SHALL use semantic color roles for primary, muted, success, warning, danger, and informational states, and SHALL preserve at least a visible focus/pressed distinction in both light and dark themes.

#### Scenario: Theme switch preserves icon contrast

- **WHEN** an authenticated user switches between light and dark mode
- **THEN** meaningful icons remain distinguishable from their adjacent surface and their state meaning does not depend on color alone

#### Scenario: Compact controls remain touch accessible

- **WHEN** an icon is used as a button in a header, table, widget, or card
- **THEN** its visual glyph may remain compact but the interactive hit area is at least 44 by 44 CSS pixels unless a documented table-density exception applies
