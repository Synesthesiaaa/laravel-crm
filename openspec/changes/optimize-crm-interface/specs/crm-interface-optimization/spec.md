## ADDED Requirements

### Requirement: Shared controls expose accessible relationships by default

Shared form controls SHALL associate visible helper or error text with the relevant input using stable IDs and `aria-describedby`. Ordinary data tables SHALL retain native table semantics unless a true interactive grid keyboard model is implemented.

#### Scenario: A field has helper text

- **WHEN** a shared input, select, or textarea renders helper text
- **THEN** the helper text has a stable ID and the control references it with `aria-describedby`

#### Scenario: A shared data table renders

- **WHEN** the shared table component is used for ordinary tabular data
- **THEN** it renders native table semantics without forcing `role="grid"`

### Requirement: Operational controls remain usable across viewport sizes

Primary buttons, icon buttons, tab controls, and navigation controls SHALL keep visible focus treatment and a minimum 44px interaction target. Dense tab rows SHALL allow horizontal scrolling on narrow screens without page-level horizontal overflow.

#### Scenario: Supervisor tabs render on a narrow viewport

- **WHEN** the available width cannot contain every tab
- **THEN** the tab strip scrolls horizontally and each tab remains reachable by keyboard

### Requirement: Dense filters use progressive disclosure

Reports and Call History SHALL keep common date/search controls visible and SHALL provide an accessible disclosure for secondary filters without changing the underlying filter request contract.

#### Scenario: User needs an uncommon filter

- **WHEN** the user activates `More filters`
- **THEN** the secondary filter group is revealed and current filter values are preserved

### Requirement: Operational views preserve semantic identity and relationships

Agent telephony controls SHALL have explicit accessible names, dynamic Agent capture fields SHALL bind labels to controls, Supervisor tabpanels SHALL reference their controlling tabs, and deep Records pages SHALL use the shared page-orientation pattern.

### Requirement: Wide dynamic schemas remain readable

Data Master SHALL preserve sticky desktop headers while allowing horizontal scrolling when the selected form has more columns than can be read comfortably at the available width.
