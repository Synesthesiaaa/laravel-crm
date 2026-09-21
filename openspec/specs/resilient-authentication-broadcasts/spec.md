# Resilient Authentication Broadcasts

## Purpose

Keep authentication and security activity logging reliable when realtime broadcasting is optional or temporarily unavailable, while starting the configured local realtime service in the composite development workflow.

## Requirements

### Requirement: Authentication is resilient to realtime broadcast outages

The system SHALL complete login and logout, including their security activity and session transitions, when the optional activity-log realtime broadcaster is unavailable, while reporting the broadcast exception for diagnosis.

#### Scenario: Login succeeds while the broadcaster is unavailable

- **WHEN** valid credentials are submitted and the activity-log broadcaster cannot be resolved or reached
- **THEN** the user is authenticated and redirected to the dashboard, the login attendance/activity records remain persisted, and the broadcast failure is not returned as a 500 response

#### Scenario: Logout succeeds while the broadcaster is unavailable

- **WHEN** an authenticated user logs out and the activity-log broadcaster cannot be resolved or reached
- **THEN** the user is logged out and redirected to the login page, the logout attendance/activity records remain persisted, and the broadcast failure is not returned as a 500 response

#### Scenario: Realtime activity remains available when the broadcaster is healthy

- **WHEN** an activity is created and the configured realtime broadcaster is available
- **THEN** the system dispatches the normalized activity event after the database commit on the existing authorized private channel with its existing payload shape

### Requirement: Local all-in-one development starts realtime services

The documented all-in-one local development command SHALL start the Reverb server alongside the application server, queue worker, log viewer, and Vite process using the existing Reverb environment configuration.

#### Scenario: Composite development command starts Reverb

- **WHEN** a developer runs `composer dev` with Reverb configured
- **THEN** the concurrent process group includes `php artisan reverb:start` and names the process distinctly as Reverb
