## MODIFIED Requirements

### Requirement: Shared shell uses semantic theme tokens

The CRM shared shell SHALL use stable semantic color, typography, spacing, border, elevation, and motion tokens for both light and dark themes. The shared shell MUST retain the established magenta/charcoal business palette (`#e91e8c` primary with black/gray surfaces) while preserving readable paired light/dark values. Page components MUST consume the tokens instead of introducing new hard-coded theme colors for shared controls.

#### Scenario: Theme switch preserves shell readability

- **WHEN** an authenticated user switches between light and dark mode
- **THEN** the sidebar, header, content surface, controls, borders, focus rings, status text, and restored magenta accent remain readable and visibly distinguishable without a full page reload

#### Scenario: Existing business palette is preserved

- **WHEN** a user opens an authenticated page after the visual refresh
- **THEN** shared shell surfaces and primary actions use the established black/gray and magenta token values rather than the replacement blue/navy palette
