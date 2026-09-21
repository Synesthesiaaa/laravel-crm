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

#### Scenario: Product workflow preview is shown

- **WHEN** the landing page presents the agent-workspace workflow preview
- **THEN** the preview is clearly contextual, remains non-interactive, stays within its responsive container, and describes only product states and controls supported by the current CRM
