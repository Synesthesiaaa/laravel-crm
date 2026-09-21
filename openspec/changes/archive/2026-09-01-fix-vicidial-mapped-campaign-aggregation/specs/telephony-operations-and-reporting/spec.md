## MODIFIED Requirements

### Requirement: Historical Reports use the shared mapped campaign scope
Historical Telephony Reports SHALL resolve the selected CRM campaign through the shared scope resolver, select its assigned VICIdial server, include every enabled mapped VICIdial campaign code by default, serialize that effective campaign set using VICIdial's supported hyphen-delimited multi-campaign format, and filter backend rows to that set using case-insensitive normalization. An optional secondary VICIdial campaign filter SHALL only narrow that set and SHALL never expand it. The same effective scope SHALL be used for current and comparison periods and reflected in the API response.

#### Scenario: Report totals aggregate all mapped campaigns
- **WHEN** CRM campaign `mbsales` maps `mbsales`, `mbsales2`, `cro1`, and `cro2`
- **AND** raw campaign totals are 413, 4326, 21, and 2 calls respectively
- **THEN** the historical report request sends `campaigns=mbsales-mbsales2-cro1-cro2` to VICIdial
- **AND** the CRM report total is 4762 calls
- **AND** rows for `winback` are excluded before aggregation

#### Scenario: Secondary campaign filter cannot escape CRM scope
- **WHEN** a report requests `cro1`
- **THEN** only `cro1` from the CRM campaign's mapped set is included
- **AND** a request for unmapped `winback` is rejected or produces no permitted rows

#### Scenario: Campaign breakdown remains available
- **WHEN** a report aggregates multiple mapped campaigns
- **THEN** the response includes CRM-level totals and a per-mapped-campaign contribution breakdown
- **AND** each campaign's rate is derived from its own raw numerator and denominator

#### Scenario: Campaign code case differs in the source
- **WHEN** a mapped code is stored as `CAMP_A` and VICIdial returns `camp_a`
- **THEN** the row is included in the mapped scope
- **AND** the response emits one canonical campaign contribution rather than an unmapped row

### Requirement: Historical agents and dispositions merge across mapped campaigns
Reports SHALL aggregate agent activity and dispositions across all enabled mapped campaigns by stable normalized VICIdial agent identifier or normalized disposition code, while retaining optional campaign-level detail. Aggregation SHALL occur before derived rates, percentages, ordering, and contact-rate calculations. Unmapped campaigns SHALL not appear in any API row, chart series, table row, or total.

#### Scenario: Agent activity is merged
- **WHEN** one agent has 80 calls on `mbsales` and 62 calls on `mbsales2`
- **THEN** the primary agent row contains 142 calls
- **AND** its detail can identify the two campaign contributions

#### Scenario: Dispositions are merged
- **WHEN** `NA` occurs 100 times on `mbsales`, 800 times on `mbsales2`, and 10 times on `cro1`
- **THEN** the CRM-level `NA` total is 910
- **AND** unrelated campaign dispositions are excluded

#### Scenario: Contact rate is derived from scoped totals
- **WHEN** the configured contacted disposition group totals 200 calls and the scoped total-call denominator is 1000
- **THEN** contact rate is 20 percent
- **AND** the rate is unavailable when either required raw total is unavailable
