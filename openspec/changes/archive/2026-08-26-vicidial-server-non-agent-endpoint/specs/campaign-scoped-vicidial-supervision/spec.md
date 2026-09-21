## MODIFIED Requirements

### Requirement: Campaign-scoped VICIdial reporting calls

The system SHALL route every Supervisor reporting request through the VICIdial server mapped to the selected CRM campaign. For each request, it SHALL use the mapped server's explicitly configured Non-Agent API URL when present. When no explicit Non-Agent API URL is configured, it SHALL retain the existing derived Non-Agent endpoint behavior from that server's Agent API URL.

#### Scenario: Reporting uses the mapped server's explicit Non-Agent API URL

- **WHEN** a supervisor requests metrics for a CRM campaign whose mapped server has a `non_agent_api_url`
- **THEN** the system sends the VICIdial reporting request to that URL
- **AND** it does not send the reporting request to the mapped server's Agent API URL

#### Scenario: Existing mapping omits a Non-Agent API URL

- **WHEN** a supervisor requests metrics for a CRM campaign whose mapped server has no `non_agent_api_url`
- **THEN** the system derives the Non-Agent endpoint from that server's Agent API URL using the existing compatibility behavior

### Requirement: Per-server Non-Agent API endpoint configuration

The system SHALL allow an administrator to optionally configure a valid complete Non-Agent API URL for each VICIdial server mapping. The configuration interface SHALL identify the field as the endpoint used by Supervisor reports and explain the derived-endpoint fallback when it is left blank.

#### Scenario: Administrator saves a dedicated endpoint

- **WHEN** an authorized administrator creates or updates a VICIdial server mapping with a valid `non_agent_api_url`
- **THEN** the system persists that endpoint with the server mapping

#### Scenario: Administrator enters an invalid endpoint

- **WHEN** an authorized administrator submits a malformed Non-Agent API URL
- **THEN** the system rejects the request with validation feedback
- **AND** it does not persist the malformed endpoint
