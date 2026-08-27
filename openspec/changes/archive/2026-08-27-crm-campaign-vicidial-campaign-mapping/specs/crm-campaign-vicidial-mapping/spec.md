## ADDED Requirements

### Requirement: CRM campaigns support normalized multi-campaign mappings
The system SHALL persist each CRM-campaign-to-VICIdial-campaign association as a separate mapping row containing the CRM campaign, VICIdial server, campaign code, enabled flag, and mapping status. The mapping SHALL enforce uniqueness for a CRM campaign, server, and campaign code.

#### Scenario: One CRM campaign maps to several VICIdial campaigns
- **WHEN** an administrator saves CRM campaign `mbsales` with `mbsales`, `mbsales2`, and `cro1`
- **THEN** the system stores three mapping rows for the selected VICIdial server
- **AND** the CRM campaign remains the authoritative business scope

#### Scenario: Duplicate mapping is rejected
- **WHEN** the same campaign code is submitted twice for one CRM campaign and server
- **THEN** validation rejects the duplicate
- **AND** the database cannot store a second identical mapping row

#### Scenario: Existing single-campaign configuration is migrated
- **WHEN** the mapping migration runs against an existing server whose legacy `campaign_code` matches a CRM campaign
- **THEN** one enabled active mapping is created for that code and server
- **AND** the legacy server field remains unchanged

### Requirement: Mapping is isolated to one CRM-owned VICIdial server
The system SHALL allow a mapping only when the selected server belongs to the selected CRM campaign through the existing server ownership configuration. A resolved CRM scope SHALL select one active server using the existing default/priority rules.

#### Scenario: Wrong-server mapping is rejected
- **WHEN** an administrator submits a server owned by CRM campaign `winback` while editing CRM campaign `mbsales`
- **THEN** the request is rejected
- **AND** no mapping row is written

#### Scenario: Multiple server records use existing selection rules
- **WHEN** a CRM campaign has several active server records
- **THEN** the resolver selects the active default server, or the lowest-priority active server when no default exists
- **AND** mapped codes from another server are not included

### Requirement: Administrators can manage mapped campaign choices safely
The administrator interface SHALL load VICIdial campaign choices from the selected server's authorized `campaigns_list` source, support multiple selection, and visibly communicate selected count, search, select all, and clear all actions. The backend SHALL validate server ownership, code syntax, distinct values, and remote campaign membership before saving.

#### Scenario: Selecting a server loads only its campaign choices
- **WHEN** an administrator selects a VICIdial server for CRM campaign `mbsales`
- **THEN** the campaign selector requests choices for that server only
- **AND** the response contains no campaign choices from another server

#### Scenario: Multi-selection feedback is available
- **WHEN** the administrator selects five campaign choices
- **THEN** the interface displays `5 campaigns selected`
- **AND** the selected codes remain visible or operably expandable on narrow screens

#### Scenario: Catalog failure does not accept unverifiable codes
- **WHEN** the selected server cannot return an authorized campaign catalog
- **THEN** the mapping save is rejected with an actionable error
- **AND** no arbitrary submitted code is persisted

#### Scenario: Empty mapping is explicit
- **WHEN** an administrator submits a server with no selected campaign codes
- **THEN** the interface displays a telephony mapping validation error
- **AND** the resolver returns no permitted VICIdial campaign codes rather than all campaigns or the first campaign

### Requirement: Mapping status is retained when VICIdial metadata changes
The system SHALL retain saved mappings when a remote catalog temporarily omits a code and SHALL expose active, disabled, stale, or unavailable status without silently remapping or deleting the row. Enabled stale/unavailable mappings SHALL remain eligible for historical scope; disabled mappings SHALL be excluded from live scope.

#### Scenario: Remote campaign becomes unavailable
- **WHEN** a previously mapped code is not returned by a later catalog refresh
- **THEN** the mapping remains stored and is displayed as unavailable or stale
- **AND** no other campaign code is substituted

#### Scenario: Disabled mapping is excluded from live data
- **WHEN** an administrator disables one mapping while leaving another enabled
- **THEN** live scope includes only the enabled mapping
- **AND** historical scope can still include the enabled historical mapping if it is stale or unavailable

### Requirement: One cached scope resolver serves Supervisor and Reports
The system SHALL resolve a CRM campaign into its CRM identity, one selected VICIdial server, mapped campaign codes, mapping metadata, and safe routing context through one reusable resolver. The resolver SHALL cache infrequently changing mappings and invalidate that cache after mapping updates.

#### Scenario: Supervisor and Reports receive the same scope
- **WHEN** Supervisor and Reports request CRM campaign `mbsales`
- **THEN** both services receive the same server ID and permitted campaign-code set
- **AND** neither controller independently reconstructs the mapping

#### Scenario: Mapping update invalidates cached scope
- **WHEN** an administrator replaces one mapped code with another
- **THEN** the next scope resolution reflects the new set immediately
- **AND** the old code is not returned from stale cache
