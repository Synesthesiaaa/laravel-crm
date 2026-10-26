## ADDED Requirements

### Requirement: VICIdial transport operations and credentials are server-controlled
Agent and Non-Agent API transports SHALL derive operation names, source identifiers, credentials, and agent identity from trusted server-side arguments. Caller parameters SHALL NOT override these transport-owned values. Requests containing reserved transport keys SHALL fail before an HTTP request is sent.

#### Scenario: Lead request attempts to change the Non-Agent operation or credentials
- **WHEN** an authenticated lead-tool request includes a reserved operation, source, user, or password parameter
- **THEN** the request is rejected and no VICIdial request is sent

#### Scenario: Agent API fields attempt to change transport identity
- **WHEN** an Agent API operation receives reserved transport keys in its query or extra parameters
- **THEN** the operation fails safely before contacting VICIdial

#### Scenario: Non-Agent batch contains a reserved parameter
- **WHEN** one batch entry contains a reserved transport key alongside valid entries
- **THEN** the invalid entry fails without a remote request while valid independent entries retain their existing result behavior

### Requirement: Agent API failure telemetry excludes credentials
Telephony logging SHALL redact credential values from nested context, messages, and credential-bearing URLs before writing to telephony log channels.

#### Scenario: Agent API connection exception contains credentials in its URL
- **WHEN** the Agent API transport reports a connection exception whose URL contains user or password query parameters
- **THEN** telephony logs contain redacted values and do not contain the supplied credential strings
