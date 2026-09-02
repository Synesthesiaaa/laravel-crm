## ADDED Requirements

### Requirement: Authoritative scoped historical source

The system SHALL retrieve Call History from row-level VICIdial historical log data and SHALL NOT use CRM-created call sessions, form submissions, or aggregate report rows as the primary source for historical call rows.

#### Scenario: Calls are read from both historical log tables
- **WHEN** a permitted user opens Call History for a CRM campaign with a configured VICIdial server
- **THEN** the provider reads outbound rows from `vicidial_log` and inbound/closer rows from `vicidial_closer_log` and combines them into one result set

#### Scenario: CRM campaign scope includes all mapped campaigns
- **WHEN** the selected CRM campaign maps to multiple enabled VICIdial campaigns
- **THEN** the remote query includes every enabled historical mapping on the selected server
- **AND** unrelated campaigns and campaigns on another server are excluded before rows reach the response

#### Scenario: Invalid secondary campaign scope is denied
- **WHEN** a request selects a VICIdial campaign that is not mapped to the selected CRM campaign
- **THEN** the system returns no authorized calls and does not issue an unrestricted remote query

### Requirement: Normalized historical call contract

The system SHALL normalize each valid source row into a stable historical call record containing the source identifier, CRM campaign, VICIdial campaign/list, lead ID, VICIdial user, CRM user association when available, agent name, phone number, call timestamps, direction, raw status, CRM disposition mapping, duration seconds, and supported wait seconds.

#### Scenario: Outbound row maps to the normalized contract
- **WHEN** an outbound `vicidial_log` row contains its documented call fields
- **THEN** the normalized record preserves its `uniqueid`, campaign, list, lead, user, phone number, `call_date`, `start_epoch`, `end_epoch`, `length_in_sec`, status, and termination reason
- **AND** its direction is `OUTBOUND`

#### Scenario: Inbound closer row maps to the normalized contract
- **WHEN** an inbound `vicidial_closer_log` row contains its documented call fields
- **THEN** the normalized record preserves its `uniqueid`, campaign, list, lead, user, phone number, `call_date`, epochs, `length_in_sec`, status, `queue_seconds`, and termination reason
- **AND** its direction is `INBOUND`

#### Scenario: Unsupported values remain unavailable
- **WHEN** a source table does not expose an independent talk-time value or an optional field is absent
- **THEN** the corresponding normalized value is `null` and the system does not calculate or fabricate a replacement

### Requirement: Stable CRM/VICIdial user association

The system SHALL associate historical calls to CRM users by the stable `User.vici_user` login and SHALL retain calls when the VICIdial user is unknown, disabled, or deleted in CRM.

#### Scenario: Known VICIdial login maps to a CRM user
- **WHEN** a historical row contains a VICIdial user login matching `users.vici_user`
- **THEN** the response includes that CRM user ID and readable CRM name

#### Scenario: Unknown VICIdial login remains visible
- **WHEN** a historical row contains a login with no CRM user mapping
- **THEN** the row remains in Call History with the raw VICIdial login and a CRM-user-unavailable indication

#### Scenario: Duplicate display names do not affect association
- **WHEN** two CRM users have the same display name but different VICIdial logins
- **THEN** each historical row is associated by its login and not by display name

### Requirement: Status, disposition, phone, and time semantics

The system SHALL preserve raw VICIdial status, resolve CRM disposition labels through the existing disposition mapping, display the source phone number under current CRM privacy behavior, use `length_in_sec` as numeric Call Duration, and interpret dates in the configured report timezone.

#### Scenario: Mapped status has distinct status and disposition values
- **WHEN** a historical row has a status that has an active CRM disposition mapping
- **THEN** Status displays the raw VICIdial code and Disposition displays the mapped CRM label

#### Scenario: Unmapped status remains diagnosable
- **WHEN** a historical row has no CRM disposition mapping
- **THEN** the raw status remains visible and Disposition displays `Unmapped`

#### Scenario: Duration is numeric and formatted at presentation
- **WHEN** a historical row has `length_in_sec = 127`
- **THEN** the API returns `duration_seconds` as the number `127`
- **AND** the UI formats it as `02:07`

#### Scenario: Date boundaries use the effective report timezone
- **WHEN** the user selects a date range
- **THEN** the query uses the configured report timezone's start and end of day and the returned timestamp is rendered in that same effective timezone

### Requirement: Server-side filters, sorting, and pagination

The system SHALL apply CRM campaign scope, date range, agent, phone, raw status, mapped disposition, and mapped VICIdial campaign filters in Laravel/the provider query, sort newest calls first by default, and paginate remotely fetched rows on the server.

#### Scenario: Combined filters return only matching rows
- **WHEN** a user supplies date, phone, agent, status, disposition, or mapped campaign filters
- **THEN** every returned row satisfies all supplied filters

#### Scenario: Phone search accepts common dialing forms
- **WHEN** a user searches for an equivalent phone number written as `09...`, `639...`, or `9...`
- **THEN** the provider searches compatible representations without rewriting stored source phone numbers

#### Scenario: Pagination does not load the full dataset
- **WHEN** the user requests page 2 with a configured page size
- **THEN** the provider returns only that page's rows plus server-calculated pagination metadata

#### Scenario: Default ordering is newest first
- **WHEN** no sort is requested
- **THEN** rows are ordered by historical call date descending with stable tie-breakers

### Requirement: API and user-facing states

The system SHALL expose a stable authenticated Call History API/resource contract and SHALL distinguish loaded data, confirmed empty results, and VICIdial/database failure states.

#### Scenario: Successful API response contains normalized data
- **WHEN** the provider returns historical rows
- **THEN** the API returns normalized rows, pagination metadata, effective scope/filter metadata, and safe source-health status without raw credentials

#### Scenario: Confirmed empty result is not an integration failure
- **WHEN** the scoped VICIdial query succeeds and returns zero rows
- **THEN** the UI says no calls were found for the selected filters and the response marks the source as healthy/confirmed empty

#### Scenario: Remote failure is not shown as zero calls
- **WHEN** the VICIdial database cannot be reached or the source query fails
- **THEN** the UI shows that Call History is unavailable with a retry action and the API marks the source unavailable instead of returning a confirmed empty result

#### Scenario: Detail fields remain authorized and accessible
- **WHEN** a user expands a call row's detail disclosure
- **THEN** the interface exposes only fields already authorized for that view, uses semantic controls, and preserves keyboard/focus access
