## ADDED Requirements

### Requirement: Meaningful activity is persisted

The system SHALL persist meaningful state-changing activity using the existing activity-log storage, including configuration model creates, updates, deletes, and restores, plus explicit login/logout, retention execution, and telephony feature configuration actions.

#### Scenario: Configuration model update records actor and changes
- **WHEN** an authenticated administrator updates a logged configuration model
- **THEN** the system stores an activity entry with the action, subject, causer, timestamp, and changed attributes including before and after values where available

#### Scenario: Read-only requests do not create audit noise
- **WHEN** an administrator views a page or performs a read-only request
- **THEN** the system does not create a meaningful-activity audit entry solely for that request

#### Scenario: Explicit security action is recorded
- **WHEN** a user successfully logs in or logs out
- **THEN** the system stores an activity entry identifying the user, action, and non-secret request metadata such as IP address when available

#### Scenario: Retention execution is recorded
- **WHEN** a retention policy is run manually or by the scheduler
- **THEN** the system stores an activity entry identifying the policy, execution status, affected count, and error summary when the run does not succeed

### Requirement: Activity properties are redacted

The system SHALL remove or replace sensitive values before activity properties are persisted or broadcast. Sensitive keys SHALL include password, pass, secret, token, api key, credential, SIP, database credential, and equivalent nested key names.

#### Scenario: Credential changes are safe to inspect
- **WHEN** an activity contains a password, API credential, token, or SIP/database secret in its attributes or old values
- **THEN** the stored and broadcast change payload contains a fixed redaction marker instead of the original value

#### Scenario: Non-sensitive configuration remains visible
- **WHEN** an activity changes a non-sensitive setting such as a label, enabled flag, sort order, or schedule
- **THEN** the normalized activity entry retains the non-sensitive before and after values

### Requirement: Activity history is restricted and filterable

The system SHALL expose a Super Admin-only Activity Log page and an authorized history endpoint. The endpoint SHALL support bounded results and filters for actor, event/action, resource/subject, date range, text search, and entries newer than a supplied activity ID.

#### Scenario: Super Admin opens Activity Log
- **WHEN** an authenticated Super Admin requests the Activity Log page
- **THEN** the system returns the terminal-style viewer and a bounded recent activity window

#### Scenario: Other roles are denied
- **WHEN** a guest or non-Super Admin requests the Activity Log page or history endpoint
- **THEN** the system returns the normal authentication redirect for guests or HTTP 403 for authenticated non-Super Admins

#### Scenario: Filters constrain returned history
- **WHEN** a Super Admin submits valid actor, event, resource, date, search, or since-ID filters
- **THEN** the history response contains only matching entries and never exceeds the server-side result limit

### Requirement: New activity is delivered in realtime

The system SHALL broadcast each persisted activity entry on a private `activity-log` channel authorized only for Super Admins. The broadcast payload SHALL use the normalized redacted entry shape and SHALL be emitted after the activity record is committed.

#### Scenario: Connected viewer receives a new entry
- **WHEN** a new activity entry is persisted while a Super Admin Activity Log viewer has an active authorized Echo connection
- **THEN** the viewer appends the normalized entry without a full page reload

#### Scenario: Unauthorized client cannot subscribe
- **WHEN** a non-Super Admin attempts to authorize the private `activity-log` channel
- **THEN** channel authorization fails

#### Scenario: Realtime transport is unavailable
- **WHEN** Reverb/Echo is disabled, disconnected, or unavailable
- **THEN** the viewer shows a disconnected state and polls the authorized history endpoint for entries newer than the last visible ID

### Requirement: Activity Log has terminal-style controls

The system SHALL render the Activity Log as a dark monospace terminal stream with timestamped entries, action/status colors, expandable redacted changes, filters, connection status, pause control, and follow/auto-scroll behavior.

#### Scenario: Newest entries follow the terminal cursor
- **WHEN** follow mode is enabled and a new entry arrives
- **THEN** the entry is appended at the bottom and the output scrolls to keep it visible

#### Scenario: Operator pauses the stream
- **WHEN** a Super Admin pauses the stream
- **THEN** new entries are retained for later display but do not force scrolling or disrupt the operator’s current position

#### Scenario: Entry details are expanded
- **WHEN** a Super Admin expands an activity entry
- **THEN** the UI displays its actor, resource, description, and redacted before/after properties without exposing secrets

#### Scenario: Clear does not destroy audit history
- **WHEN** a Super Admin clears the visible terminal buffer
- **THEN** only the browser buffer is cleared and no database activity row is deleted or modified
