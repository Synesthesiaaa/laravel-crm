## ADDED Requirements

### Requirement: VICIdial endpoint resolution is server-scoped
The system SHALL resolve Non-Agent API and report endpoint URLs from the selected CRM campaign's mapped active VICIdial server. An explicit server URL SHALL take precedence over global defaults, and the resolver SHALL preserve the server's application path when deriving sibling endpoints.

#### Scenario: Server has an explicit Non-Agent URL
- **WHEN** a CRM campaign maps to a server with an explicit Non-Agent API URL
- **THEN** all Non-Agent requests use that URL
- **AND** a global Non-Agent URL does not replace it

#### Scenario: Server has only an Agent API URL
- **WHEN** the server's Agent API URL contains an application path such as `/vicidial/agc/api.php`
- **THEN** the derived Non-Agent URL uses the same application path, such as `/vicidial/non_agent_api.php`
- **AND** no unrelated campaign server is consulted

### Requirement: VICIdial responses have safe failure classifications
The system SHALL classify a VICIdial request as `AUTHENTICATION_FAILED`, `PERMISSION_DENIED`, `REPORT_EMPTY`, `REPORT_HTML_CHANGED`, `NETWORK_TIMEOUT`, `CONNECTION_REFUSED`, `SSL_ERROR`, `SERVER_ERROR`, or `PARSE_ERROR` when the corresponding evidence is present. HTTP 200 SHALL NOT by itself indicate success.

#### Scenario: Login error is returned with HTTP 200
- **WHEN** VICIdial returns a login page or login-error body with HTTP 200
- **THEN** the operation fails with `AUTHENTICATION_FAILED`
- **AND** the report parser is not invoked as if the body were data

#### Scenario: Permission page is returned
- **WHEN** the response indicates that the API user cannot view the requested report
- **THEN** the operation fails with `PERMISSION_DENIED`
- **AND** the user-facing message contains remediation guidance without credentials or raw response content

#### Scenario: Empty data is returned
- **WHEN** the endpoint returns a valid success response with no data rows
- **THEN** the operation is classified as `REPORT_EMPTY`
- **AND** consumers may render an explicit empty state rather than zeroing unavailable metrics

### Requirement: Transport diagnostics are structured and redacted
The system SHALL log and return safe diagnostics containing server identity, CRM campaign, endpoint category, duration, HTTP status, content type, response size, parser outcome, parsed row count, and classification. It MUST NOT log or return passwords, API secrets, session cookies, authentication URLs containing credentials, or unmasked customer data.

#### Scenario: Request succeeds with parseable rows
- **WHEN** a Non-Agent request returns valid delimited rows
- **THEN** diagnostics record a successful parser outcome and parsed row count
- **AND** the response data contains the normalized rows without secret query parameters

#### Scenario: Connection exception includes query credentials
- **WHEN** the HTTP client throws an exception whose message contains `user` or `pass` query values
- **THEN** telemetry redacts those values before logging
- **AND** the operation classification identifies the network failure category
