## ADDED Requirements

### Requirement: Consistent landing alignment

The landing page SHALL use consistent content gutters and shared column alignment for its hero and role overview, with a centered desktop navigation and responsive stacked layouts.

#### Scenario: Visitor views the desktop composition

- **WHEN** the landing page is viewed at a desktop width
- **THEN** the hero and role overview share the same left and right column starting positions
- **AND** corresponding text blocks align to their top edges
- **AND** header navigation is centered independently of the brand and action widths

#### Scenario: Visitor views a narrow composition

- **WHEN** the viewport cannot accommodate both columns
- **THEN** content stacks in reading order with consistent gutters and no horizontal overflow

### Requirement: Concise landing narrative

The landing page SHALL present one ordered call workflow and one role overview, without repeating the workflow in capability strips, separate calling timelines or checklists, or a closing sign-in section.

#### Scenario: Visitor scans product information

- **WHEN** the public page is rendered
- **THEN** the four-step workflow explains leads, browser calling, campaign forms, results, and follow-ups once
- **AND** a compact integration note retains VICIdial/Asterisk context and campaign/permission constraints
- **AND** the role overview retains operational reporting context without secondary marketing headings per role

#### Scenario: Visitor follows navigation

- **WHEN** the visitor chooses a navigation or sign-in link
- **THEN** section links target existing content and sign-in remains available in the header and hero through the named login route
