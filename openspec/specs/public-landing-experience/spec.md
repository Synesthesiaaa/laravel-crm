# public-landing-experience Specification

## Purpose

Provide a public, brand-aligned CRM entry page that explains the product's campaign and telephony workflow accurately, remains responsive and accessible, and directs visitors into the existing sign-in flow.

## Requirements
### Requirement: Public product entry

The application SHALL render a public CRM landing page at the root route that explains the product before authentication and provides a clear path into the existing sign-in flow.

#### Scenario: Guest opens the root route

- **WHEN** a guest requests `/`
- **THEN** the application returns a successful HTML response containing the configured brand, a primary product headline, and a prominent link to the named login route

#### Scenario: Existing authentication entry remains available

- **WHEN** a visitor chooses a sign-in call to action from the landing page
- **THEN** the link targets the existing named login route without introducing registration, trial, or alternate authentication behavior

### Requirement: Accurate product positioning

The landing page SHALL describe only capabilities evidenced by the current CRM and SHALL avoid unsupported marketing proof.

#### Scenario: Visitor scans the page

- **WHEN** the landing page is rendered
- **THEN** it may describe campaign-aware workflows, browser calling, VICIdial/Asterisk integration, forms, dispositions, callbacks, attendance, reporting, notifications, activity history, and role-scoped operations
- **AND** it does not claim unverified testimonials, customer counts, pricing, ROI, conversion gains, uptime guarantees, compliance guarantees, or unsupported integrations

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

### Requirement: Text-led product presentation

The landing page SHALL present a concise product headline, ordered call workflow, and role-scoped capabilities without an illustrative Agent Screen workspace, simulated call controls, or fictional customer details.

#### Scenario: Visitor opens the landing page

- **WHEN** the public landing page renders
- **THEN** the hero contains product copy and navigation actions without workspace illustration markup or fictional customer details

### Requirement: Persistent accessible theme selection

The public landing page SHALL retain light or dark theme selection in the existing browser theme preference and provide an accessible theme control.

#### Scenario: Visitor changes theme

- **WHEN** the visitor activates the theme control
- **THEN** the complete page changes theme and the control's accessible label and pressed state reflect the selected theme
- **AND** the preference persists after reload

#### Scenario: Stored theme is unavailable or invalid

- **WHEN** browser storage is unavailable or contains a value other than light or dark
- **THEN** the landing page remains usable with the dark fallback

#### Scenario: Visitor requests reduced motion

- **WHEN** reduced motion is enabled
- **THEN** the page disables smooth scrolling and decorative transitions while all content and navigation remain usable

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
