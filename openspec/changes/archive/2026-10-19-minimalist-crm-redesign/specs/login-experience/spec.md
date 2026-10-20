## MODIFIED Requirements

### Requirement: Palette-aligned login composition

The guest login page SHALL present a focused, brand-led authentication composition within the CRM's minimalist visual system. It SHALL keep the configured brand lockup visible above a single auth sheet, use shared neutral surface, text, border, elevation, and primary-control tokens, and avoid redundant context content or unverified marketing claims. New sessions SHALL initialize in light mode unless a valid dark preference is saved.

#### Scenario: Dark theme entry

- **WHEN** a guest opens the login route with a saved dark preference
- **THEN** the page renders a calm charcoal single-column entry with the configured brand above the auth sheet, readable primary and muted text, and a high-contrast neutral submit action

#### Scenario: Light theme entry

- **WHEN** a guest opens sign-in without a valid saved preference or switches to light mode
- **THEN** the page uses the shared light-theme neutral surfaces, crisp borders, readable text, and charcoal primary action
- **AND** switching themes preserves the selected preference for authenticated screens

#### Scenario: Configured brand remains visible

- **WHEN** the login page receives a configured company name and logo or the safe fallback brand
- **THEN** the compact brand lockup remains visible above the auth sheet, preserves its accessible alternative text, and long names wrap within the bounded column without creating horizontal overflow
