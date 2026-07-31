# Data Master Navigation and Search Specification

## Purpose

Keep Data Master form switching compatible with the persistent application shell and make record lookup efficient and safe.

## Requirements

### Requirement: Data Master form selection preserves the telephony shell

The Data Master form selector MUST load a selected form through the existing soft-navigation boundary when the authenticated application shell is available, preserving the floating Vicidial widget and its active iframe session.

#### Scenario: User loads another Data Master form with an active Vicidial session

- **WHEN** the user selects a different form and submits the Data Master selector
- **THEN** only the page content is replaced, the URL contains the selected form, and the Vicidial iframe is not recreated by a full browser reload

#### Scenario: Soft navigation cannot load the selected form

- **WHEN** the marked GET navigation fails or the response is not a valid application shell
- **THEN** the browser falls back to normal navigation so the selected form remains reachable

### Requirement: Data Master records support safe server-side search

The Data Master page MUST accept an optional search term and filter records in the selected, campaign-allowed table across its actual database columns without treating user input as a column identifier.

#### Scenario: Search finds a matching value

- **WHEN** an authorized user submits a non-empty search term for a selected form
- **THEN** the page shows only records containing that term in at least one available column and keeps the term in the search control

#### Scenario: Search has no matches

- **WHEN** an authorized user submits a search term with no matching record
- **THEN** the page shows a clear empty state and does not query a table outside the campaign allowlist

#### Scenario: Search pagination preserves filters

- **WHEN** a user opens another result page after searching
- **THEN** the pagination URL preserves both the selected form and the search term
