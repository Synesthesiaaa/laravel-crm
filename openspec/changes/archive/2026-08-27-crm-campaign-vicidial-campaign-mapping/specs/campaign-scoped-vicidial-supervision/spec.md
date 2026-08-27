## ADDED Requirements

### Requirement: Supervisor live data is filtered by the resolved mapped campaign set
The Supervisor SHALL remain CRM-campaign-first. After resolving the selected CRM campaign and its one VICIdial server, it SHALL include live agents, active calls, waiting calls, sessions, and CRM fallback records only when their reliable VICIdial campaign identifier belongs to the resolved enabled campaign set.

#### Scenario: Agents on different mapped campaigns are included
- **WHEN** CRM campaign `mbsales` maps `mbsales`, `mbsales2`, `cro1`, and `cro2`
- **AND** agents are currently logged into `mbsales2` and `cro1`
- **THEN** both agents appear in the `mbsales` Supervisor snapshot
- **AND** each card retains its actual current VICIdial campaign

#### Scenario: Unmapped campaign is excluded
- **WHEN** a server-wide feed also returns an agent on `winback`
- **THEN** that agent is excluded from the `mbsales` snapshot
- **AND** the response does not expose the unrelated campaign row

#### Scenario: Duplicate live agent rows are collapsed
- **WHEN** one VICIdial agent appears more than once in the server feed
- **THEN** the Supervisor returns one agent record keyed by stable VICIdial username/user ID
- **AND** the record retains the selected current campaign context

### Requirement: Supervisor aggregates operational metrics across mapped campaigns
The Supervisor SHALL sum valid active calls and queue signals across all mapped live campaigns and SHALL expose the resolved campaign count/codes in routing context. It SHALL not issue one request per dashboard widget or fall back to all campaigns when the set is empty.

#### Scenario: Active calls are summed across mapped campaigns
- **WHEN** `mbsales` has two active calls, `mbsales2` has four, and `cro1` has one
- **THEN** the combined Supervisor active-call total is seven
- **AND** unrelated campaign activity is excluded

#### Scenario: Empty mapping is safe
- **WHEN** a CRM campaign has a configured server but zero enabled mapped campaigns
- **THEN** the Supervisor reports an incomplete/no-mapping state
- **AND** it returns zero permitted remote campaigns without querying or exposing all server campaigns

#### Scenario: Routing context explains the aggregate scope
- **WHEN** the Supervisor snapshot is returned for a CRM campaign with five mappings
- **THEN** routing context includes the CRM campaign, server identity, mapped count, and permitted campaign codes
- **AND** it excludes credentials and credential-bearing URLs

### Requirement: Supervisor CRM fallback queries use the same mapped set
When VICIdial data is unavailable, CRM sessions, dispositions, and local user inclusion SHALL use `whereIn` against the resolved mapped campaign codes. A user may remain visible through the established CRM membership rule, but unrelated campaign activity SHALL not contribute to telephony metrics.

#### Scenario: Fallback totals aggregate mapped CRM sessions
- **WHEN** CRM sessions contain calls for `mbsales` and `mbsales2`, plus calls for `winback`
- **THEN** the fallback total includes only the first two campaign codes
- **AND** the `winback` calls are excluded

#### Scenario: Offline CRM member remains visible without telephony leakage
- **WHEN** an agent is a CRM member of `mbsales` but has no current mapped VICIdial session
- **THEN** the existing CRM membership rule may keep the agent card visible
- **AND** no unrelated campaign's live status or call metrics are copied onto that card
