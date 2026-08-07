## MODIFIED Requirements

### Requirement: All-user history is filterable and realtime

The system SHALL expose request activities through the existing Super Admin Activity Log history endpoint and private realtime channel. The normalized entry SHALL show the request method as its action and include request path, route, status, actor details, and structured before/after changes when activity properties contain them. The actor filter SHALL include every current user, not only users with an administrative role.

#### Scenario: Super Admin sees requests from different roles

- **WHEN** a Super Admin opens Activity Log after an Agent, Team Leader, Admin, or Super Admin has made requests
- **THEN** the stream includes those request entries with the correct actor labels and expandable actor metadata

#### Scenario: Request entries can be filtered by actor and action

- **WHEN** a Super Admin filters history by actor or the `request` event
- **THEN** only matching request activities are returned within the server-side limit and each matching entry retains its detailed request metadata

#### Scenario: New request activity is delivered without reload

- **WHEN** an authenticated user makes a request while a Super Admin Activity Log is connected
- **THEN** the new normalized request entry is appended to the terminal through the existing realtime/polling mechanism with the same structured details as the initial history response
