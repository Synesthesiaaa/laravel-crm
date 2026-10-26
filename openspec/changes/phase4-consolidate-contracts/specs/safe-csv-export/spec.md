## ADDED Requirements

### Requirement: CSV exports neutralize spreadsheet formulas
The system SHALL apply one shared encoding rule to headers and exported values from every CRM CSV endpoint. A value SHALL remain numeric only when its field is numeric and the value passes strict numeric parsing; other values beginning with a spreadsheet formula marker after leading whitespace or control characters SHALL be emitted as text.

#### Scenario: Formula-like customer text is exported
- **WHEN** an exported text value begins with `=`, `+`, `-`, or `@`, including after leading spaces, tabs, or line breaks
- **THEN** the CSV cell is encoded as text so opening the file cannot execute the value as a formula

#### Scenario: Valid numeric values retain numeric meaning
- **WHEN** a configured numeric field contains a strict decimal or integer value, including a negative number
- **THEN** the exported cell retains its numeric value without a text prefix

#### Scenario: Both export paths use the encoder
- **WHEN** a user exports extraction data or capture records
- **THEN** both endpoints apply the same header and cell encoding behavior
