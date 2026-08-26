## ADDED Requirements

### Requirement: Supervisor communicates VICIdial reporting health safely
For the VICIdial server mapped to the selected CRM campaign, the Supervisor API SHALL return a non-sensitive reporting state of `live`, `degraded`, `unavailable`, or `not_configured`. The dashboard SHALL present the state and a clear recovery action near the routing context. It MUST retain successful VICIdial metric families and the existing per-metric CRM fallback when another report is unavailable.

#### Scenario: Mapped VICIdial server cannot be reached
- **WHEN** all requested Non-Agent API reports for the selected campaign's mapped server fail because the server cannot be reached
- **THEN** the Supervisor response marks reporting as `unavailable`
- **AND** it returns generic guidance to verify the campaign mapping, network access, API credentials, and report permissions
- **AND** it uses campaign-scoped CRM fallback metrics without exposing the server URL or credentials

#### Scenario: One VICIdial report fails while another succeeds
- **WHEN** one or more Non-Agent API reports fail but at least one report succeeds for the selected campaign's mapped server
- **THEN** the Supervisor response marks reporting as `degraded`
- **AND** the dashboard identifies the degraded report state in text
- **AND** successful metric families retain their VICIdial source identifiers

#### Scenario: VICIdial report response is valid but empty
- **WHEN** requested Non-Agent API reports succeed but report zero currently logged-in agents or zero calls
- **THEN** the Supervisor response marks reporting as `live`
- **AND** the dashboard does not describe the empty result as a connection failure

### Requirement: VICIdial connection telemetry protects credentials
The Non-Agent API client SHALL redact API username and password query parameter values from caught connection-exception text before writing telemetry.

#### Scenario: Connection exception includes the request URL
- **WHEN** an outbound Non-Agent API request throws an exception whose message contains `user` or `pass` query parameters
- **THEN** the logged exception context omits the original parameter values
- **AND** the caller receives the existing generic reachability failure message
