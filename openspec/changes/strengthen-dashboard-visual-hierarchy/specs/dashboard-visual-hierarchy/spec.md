## ADDED Requirements

### Requirement: Operational first-viewport hierarchy
The dashboard SHALL present campaign context and the primary current performance signal before supporting and retrospective metrics, with a visually distinct primary, secondary, and analytical reading order.

#### Scenario: User scans the dashboard opening
- **WHEN** an authenticated user opens the dashboard with the welcome and KPI sections visible
- **THEN** campaign context and the primary sales result are more visually prominent than supporting context metrics
- **AND** monthly comparison content is grouped as a separate analytical region

### Requirement: Brand-consistent character
The dashboard SHALL derive its visual character from the existing design tokens, including charcoal tonal surfaces, Signal Magenta, semantic status colors, DM Sans typography, and the established signal-edge motif.

#### Scenario: Dashboard renders in a supported theme
- **WHEN** the dashboard is displayed in dark or light theme
- **THEN** all new hierarchy styling uses semantic CSS variables rather than fixed theme colors
- **AND** magenta remains reserved for action, focus, active state, or meaningful emphasis

### Requirement: Responsive semantic order
The dashboard SHALL preserve a logical DOM, focus, and assistive-technology order while adapting the emphasized layout across viewport widths.

#### Scenario: Dashboard is viewed on a narrow screen
- **WHEN** the viewport cannot support the desktop KPI composition
- **THEN** the dashboard collapses to a single-column sequence without horizontal page overflow
- **AND** the lead KPI precedes its supporting context cards

### Requirement: Existing dashboard behavior is preserved
The hierarchy change SHALL preserve existing dashboard values, campaign and role visibility, modal actions, section configuration, chart behavior, and accessible control names.

#### Scenario: User activates an interactive KPI
- **WHEN** the user activates the sales or top-agent KPI using pointer or keyboard input
- **THEN** the existing corresponding modal opens
- **AND** the control retains a visible focus treatment and an accessible dialog relationship
