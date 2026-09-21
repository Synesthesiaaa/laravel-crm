## ADDED Requirements

### Requirement: Desktop Data Master table fits the viewport

The desktop Data Master page MUST render the complete table within the available content width without horizontal overflow caused by long headers, values, or action controls.

#### Scenario: Long values remain inside the desktop table

- **WHEN** a Data Master record contains a long unbroken value
- **THEN** the value wraps within its table cell and the table remains no wider than its containing viewport

#### Scenario: All Data Master columns remain visible

- **WHEN** the Data Master table is rendered at the desktop breakpoint
- **THEN** all configured data columns and the actions column remain rendered within the table viewport without requiring horizontal scrolling

### Requirement: Desktop Data Master records scroll vertically

The desktop Data Master table MUST provide a bounded vertical scroll region for records while keeping the existing pagination and table contents intact.

#### Scenario: Many records exceed the table viewport

- **WHEN** the rendered Data Master records are taller than the bounded table region
- **THEN** the table region scrolls vertically and the page does not gain a horizontal scrollbar for that table

#### Scenario: Header remains available while records scroll

- **WHEN** a user scrolls down within the desktop Data Master table region
- **THEN** the table header remains visible at the top of that region

### Requirement: Mobile Data Master behavior remains unchanged

The desktop-only Data Master layout rules MUST NOT change the existing mobile table presentation.

#### Scenario: Data Master is rendered below the desktop breakpoint

- **WHEN** the Data Master page is viewed on a mobile-width viewport
- **THEN** the desktop fixed-layout, sticky-header, and bounded-scroll rules are not applied
