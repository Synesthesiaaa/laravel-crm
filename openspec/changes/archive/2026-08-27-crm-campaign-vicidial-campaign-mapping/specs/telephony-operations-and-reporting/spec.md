## ADDED Requirements

### Requirement: Historical Reports use the shared mapped campaign scope
Historical Telephony Reports SHALL resolve the selected CRM campaign through the shared scope resolver, filter backend rows to enabled mapped VICIdial campaign codes, and default to all mapped codes. An optional secondary VICIdial campaign filter SHALL only narrow that set.

#### Scenario: Report totals aggregate all mapped campaigns
- **WHEN** CRM campaign `mbsales` maps `mbsales`, `mbsales2`, `cro1`, and `cro2`
- **AND** raw campaign totals are 413, 4326, 21, and 2 calls respectively
- **THEN** the CRM report total is 4762 calls
- **AND** rows for `winback` are excluded before aggregation

#### Scenario: Secondary campaign filter cannot escape CRM scope
- **WHEN** a report requests `cro1`
- **THEN** only `cro1` from the CRM campaign's mapped set is included
- **AND** a request for unmapped `winback` is rejected or produces no permitted rows

#### Scenario: Campaign breakdown remains available
- **WHEN** a report aggregates multiple mapped campaigns
- **THEN** the response includes CRM-level totals and a per-mapped-campaign contribution breakdown
- **AND** each campaign's rate is derived from its own raw numerator and denominator

### Requirement: Report rates use weighted raw totals
Combined report rates SHALL be calculated from summed numerators and denominators, never by averaging campaign percentages. This applies to answer, contact, conversion, and abandonment rates.

#### Scenario: Combined answer rate is weighted
- **WHEN** campaign A has 100 calls and 50 answered and campaign B has 900 calls and 90 answered
- **THEN** the combined report contains 1000 calls and 140 answered
- **AND** the answer rate is 14 percent rather than 30 percent

### Requirement: Historical agents and dispositions merge across mapped campaigns
Reports SHALL aggregate agent activity and dispositions across all mapped campaigns by stable VICIdial agent identifier or normalized disposition code, while retaining optional campaign-level detail.

#### Scenario: Agent activity is merged
- **WHEN** one agent has 80 calls on `mbsales` and 62 calls on `mbsales2`
- **THEN** the primary agent row contains 142 calls
- **AND** its detail can identify the two campaign contributions

#### Scenario: Dispositions are merged
- **WHEN** `NA` occurs 100 times on `mbsales`, 800 times on `mbsales2`, and 10 times on `cro1`
- **THEN** the CRM-level `NA` total is 910
- **AND** unrelated campaign dispositions are excluded

### Requirement: Report scope failure is explicit and safe
Reports SHALL return a not-configured/incomplete state for a missing server or empty mapping and SHALL never substitute all VICIdial campaigns or another CRM campaign's mapping.

#### Scenario: Missing server does not search another server
- **WHEN** the selected CRM campaign has no mapped VICIdial server
- **THEN** the report reports VICIdial routing as not configured
- **AND** it does not query a server assigned to another CRM campaign
