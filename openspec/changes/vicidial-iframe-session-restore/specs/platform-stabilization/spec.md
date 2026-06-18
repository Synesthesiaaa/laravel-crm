## ADDED Requirements

### Requirement: Vicidial iframe restores for active sessions after reload
The system SHALL restore the embedded Vicidial iframe after a browser reload or soft-navigation return when the local telephony session is `login_pending`, `ready`, `paused`, or `in_call`. The system SHALL use the saved iframe URL when present and SHALL rebuild the URL from the active session when the cached URL is missing. The system SHALL keep the widget's visible state aligned with the restored session and SHALL clear the iframe when the session is logged out.

#### Scenario: Reload during an active ready session
- **WHEN** the user reloads the page while the local Vicidial session is `ready`
- **THEN** the widget SHALL restore the Vicidial iframe and SHALL keep the session controls available

#### Scenario: Reload while the session is pending login
- **WHEN** the user reloads the page while the local Vicidial session is `login_pending`
- **THEN** the widget SHALL restore the iframe and resume the login verification flow

#### Scenario: Logged out sessions remain blank
- **WHEN** the local Vicidial session is `logged_out`
- **THEN** the widget SHALL keep the iframe blank and SHALL present the idle state
