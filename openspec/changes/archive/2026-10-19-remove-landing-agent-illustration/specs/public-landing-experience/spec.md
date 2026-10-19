## MODIFIED Requirements

### Requirement: Conversion-focused responsive presentation

The landing page SHALL remain easy to scan and operate on mobile, tablet, and desktop while preserving the CRM's configured branding and visual system.

#### Scenario: Visitor uses a narrow viewport

- **WHEN** the page is viewed at a mobile width
- **THEN** content stacks without page-level horizontal scrolling, primary calls to action remain easy to reach, images stay within their containers, and interactive targets remain at least 44 CSS pixels tall

#### Scenario: Visitor uses keyboard navigation

- **WHEN** the visitor tabs through navigation and calls to action
- **THEN** focus follows a logical order and each interactive control has a visible focus indicator

#### Scenario: Visitor views the hero

- **WHEN** the landing page renders its hero
- **THEN** it presents the product heading, description, and existing sign-in actions in a responsive text-led composition without an illustrative Agent Screen

## ADDED Requirements

### Requirement: Text-led product presentation

The landing page SHALL present a concise product headline, ordered call workflow, and role-scoped capabilities without an illustrative Agent Screen workspace, simulated call controls, or fictional customer details.

#### Scenario: Visitor opens the landing page

- **WHEN** the public landing page renders
- **THEN** the hero contains product copy and navigation actions without workspace illustration markup or fictional customer details

## REMOVED Requirements

### Requirement: Clearly illustrative product presentation

**Reason**: The user requested removal of the illustrative workspace.
**Migration**: Use the text-led hero and existing workflow sections.
