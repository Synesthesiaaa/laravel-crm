## ADDED Requirements

### Requirement: Standard form validation follows server-evaluated visibility

Regular CRM form request validation and persistence SHALL evaluate each configured field's visibility rule from submitted values. A hidden field SHALL neither be required nor persisted, regardless of any client-side visibility claim.

#### Scenario: Hidden required form field is omitted
- **WHEN** a field is configured as required but its visibility rule evaluates false
- **THEN** the submission succeeds without that field
- **AND** the hidden field is stored as null

#### Scenario: Visible required form field is omitted
- **WHEN** a field is configured as required and its visibility rule evaluates true
- **THEN** the submission returns a validation error for that field

#### Scenario: Visibility source is a multiselect value
- **WHEN** a visibility rule uses `in` or `not_in` against an array-valued source
- **THEN** server evaluation matches the existing form UI operator semantics

### Requirement: Numeric form values match their storage precision

New numeric form values SHALL preserve their validated base-10 sign and digits. Values SHALL fit the existing `DECIMAL(65,2)` precision, exponent notation and more than two fractional digits SHALL be rejected for decimal fields, and identifier-like numeric fields stored as strings SHALL preserve leading zeroes. The system SHALL NOT silently strip characters, truncate, or round a submitted value.

#### Scenario: Negative decimal is preserved
- **WHEN** a numeric form field receives `-12.50`
- **THEN** the persisted value remains negative with its submitted two-decimal meaning

#### Scenario: Unsupported exponent or excess precision is rejected
- **WHEN** a decimal field receives `1e3` or `12.345`
- **THEN** validation rejects the value before persistence

#### Scenario: Numeric identifier retains leading zeroes
- **WHEN** an identifier-like numeric field receives `001234567890`
- **THEN** its string-backed value retains all leading zeroes
