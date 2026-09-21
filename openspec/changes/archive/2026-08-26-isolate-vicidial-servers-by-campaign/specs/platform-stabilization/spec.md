## ADDED Requirements

### Requirement: VICIdial requests require a campaign-specific server
The system SHALL resolve VICIdial requests only against active servers whose `campaign_code` exactly matches the requested campaign. Default and priority ordering MUST operate only inside that campaign's server set.

#### Scenario: Campaign has multiple active servers
- **WHEN** a campaign has multiple active VICIdial servers
- **THEN** resolution selects its default server first, or its lowest-priority stable record when no default is marked
- **AND** no server from another campaign is considered

#### Scenario: Campaign has no active server
- **WHEN** the requested campaign has no active VICIdial server
- **THEN** the request fails with an actionable campaign-specific configuration error
- **AND** the system does not send the request to another campaign's server

## REMOVED Requirements

### Requirement: Vicidial session requests fall back to an active server
**Reason**: Cross-campaign fallback can route agent and Supervisor actions to the wrong VICIdial instance in a multi-server deployment.

**Migration**: Create at least one active `vicidial_servers` record for every telephony-enabled CRM campaign before deploying the strict resolver.

