## Purpose

Provide Super Admins with a complete, redacted audit timeline of authenticated user activity across the web and API surfaces.

## Requirements

### Requirement: Every authenticated request is audited

The system SHALL create an activity-log entry for every authenticated web and API request, including read-only page visits, polling requests, successful actions, redirects, validation failures, and authorization failures. Each entry SHALL identify the authenticated causer when available and SHALL record the HTTP method, path, route name when available, response status, IP address, user agent, and sanitized query metadata.

#### Scenario: Authenticated read-only page visit is recorded

- **WHEN** an authenticated user requests a normal web page with `GET`
- **THEN** the system stores a request activity with the user as causer, the page path, the route name, and the returned status code

#### Scenario: Authenticated polling request is recorded

- **WHEN** an authenticated browser performs a polling or JSON `GET` request
- **THEN** the system stores a request activity containing the endpoint and response status without storing the request body

#### Scenario: Authenticated state-changing request is recorded

- **WHEN** an authenticated user submits a `POST`, `PUT`, `PATCH`, or `DELETE` request
- **THEN** the system stores a request activity identifying the method, endpoint, user, and response status in addition to any existing model/domain activity

#### Scenario: Logout retains the actor

- **WHEN** an authenticated user submits the logout request and authentication is cleared during route handling
- **THEN** the request activity still identifies the user who initiated logout

#### Scenario: Unauthenticated request is not assigned to a user activity

- **WHEN** a guest requests a public page or an unauthenticated endpoint
- **THEN** the request middleware does not create a user-caused request activity

### Requirement: Request metadata is safe to inspect

The system SHALL omit request bodies and sensitive headers from request activities and SHALL recursively redact sensitive query values using the existing activity sanitizer. Sensitive values SHALL never appear in persisted or broadcast request metadata.

#### Scenario: Sensitive query metadata is redacted

- **WHEN** an authenticated request contains password, token, API key, credential, authorization, or equivalent query keys
- **THEN** the activity properties contain the fixed redaction marker instead of the original values

#### Scenario: Non-sensitive query metadata remains visible

- **WHEN** an authenticated request contains ordinary filters or identifiers in its query string
- **THEN** the normalized activity entry retains those non-sensitive query values

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
