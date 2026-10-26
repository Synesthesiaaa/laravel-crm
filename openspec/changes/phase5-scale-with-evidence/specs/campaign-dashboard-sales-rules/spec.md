## MODIFIED Requirements

### Requirement: Custom sales aggregation shares one behavior-preserving evaluator

Dashboard custom-sales summary, range, and daily report paths SHALL use a shared evaluator and bounded query/aggregation service. The extracted implementation SHALL preserve configured trigger semantics, case normalization, numeric amount handling, date boundaries, per-agent and per-form totals, and existing result shapes.

#### Scenario: Tag-triggered sales are aggregated across report modes
- **WHEN** a configured tag rule matches case-insensitively in a selected period
- **THEN** the summary, range, and daily report count the same records and amounts

#### Scenario: Marked-amount rule sees an invalid amount
- **WHEN** a marked amount field is blank or non-numeric
- **THEN** the shared evaluator excludes that record from the sale count as before

#### Scenario: Large custom-sales source
- **WHEN** custom-sales reporting scans more records than the processing chunk size
- **THEN** it builds the same aggregate through bounded chunks without changing dashboard output
