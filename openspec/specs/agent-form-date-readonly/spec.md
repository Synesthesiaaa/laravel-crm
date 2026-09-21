# Purpose

Define role-specific date editability and server-side date integrity for regular CRM form submissions.

## Requirements

### Requirement: Agent date field is visible and read-only on regular CRM forms

The regular CRM form date field SHALL remain visible and populated with the current date for authenticated users with the Agent role, and it SHALL be rendered with the `readonly` attribute for those users.

#### Scenario: Agent opens a regular CRM form

- **WHEN** an authenticated user with role `Agent` requests a regular CRM form
- **THEN** the response SHALL contain a visible date input with the current date and the `readonly` attribute

### Requirement: Elevated roles retain editable dates

The regular CRM form date field SHALL remain editable for Team Leaders, Admins, and Super Admins.

#### Scenario: Administrator opens a regular CRM form

- **WHEN** an authenticated user with role `Admin`, `Super Admin`, or `Team Leader` requests a regular CRM form
- **THEN** the response SHALL contain the date input without the `readonly` attribute

### Requirement: Agent submissions use the server date

The regular CRM form submission endpoint SHALL replace the submitted date with the current server date for Agent users before validation and persistence.

#### Scenario: Agent submits a forged date

- **WHEN** an authenticated Agent submits a regular CRM form with a date different from the current server date
- **THEN** the stored record SHALL use the current server date

### Requirement: Agent Capture webforms remain unchanged

The Agent Capture webform SHALL not inherit the regular CRM form date restriction from this capability.

#### Scenario: Agent opens an Agent Capture webform

- **WHEN** an authenticated Agent requests an Agent Capture webform
- **THEN** the response and capture submission behavior SHALL remain governed by the existing Agent Capture implementation
