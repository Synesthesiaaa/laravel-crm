## MODIFIED Requirements

### Requirement: Login task hierarchy and form semantics

The login page SHALL make the credential task, optional campaign context, feedback state, and primary submit action visually and semantically distinct without changing the existing login request fields or route.

#### Scenario: Credential form is ready for use

- **WHEN** a guest opens the login page
- **THEN** the page identifies the CRM as the destination, instructs the guest to use their username and password, exposes visible labels for both credentials, provides an optional campaign selector when campaigns exist, and presents one full-width sign-in action in logical tab order

#### Scenario: Campaign selection explains its consequence

- **WHEN** the application provides one or more campaigns
- **THEN** the page labels the control as a campaign choice, explains that the selected campaign is the guest's starting work area after sign-in, displays the current/old selection using the existing campaign values, and keeps the control native and keyboard- and touch-operable

#### Scenario: Invalid credentials provide recovery guidance

- **WHEN** a guest submits credentials that do not authenticate
- **THEN** the page announces a privacy-safe message that the sign-in failed, tells the guest to check the username and password, and invites them to try again without identifying which credential was incorrect

#### Scenario: Theme control names its next action

- **WHEN** the login page loads with the dark theme active
- **THEN** the theme control has the accessible name and title “Switch to light mode,” and the existing theme script updates that name and title when the theme changes
