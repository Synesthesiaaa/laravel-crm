# operations-insights-access Specification

## Purpose
TBD - created by archiving change phase1-contain-security-exposure. Update Purpose after archive.
## Requirements
### Requirement: Operations Insights requires an authorized reporting session
The Operations Insights page and each aggregate or record-data endpoint SHALL require an authenticated user in the active campaign context whose application role is Team Leader, Admin, or Super Admin.

#### Scenario: Guest requests an Operations Insights resource
- **WHEN** a guest requests the Operations Insights page, aggregate endpoint, or record endpoint
- **THEN** the request is redirected to sign-in or receives an unauthenticated API response and no report or customer data is returned

#### Scenario: Agent requests an Operations Insights resource
- **WHEN** an authenticated Agent requests the Operations Insights page or either data endpoint
- **THEN** the request is denied with forbidden status and no report or customer data is returned

#### Scenario: Authorized reporting role reads Operations Insights
- **WHEN** a Team Leader, Admin, or Super Admin requests Operations Insights in the active campaign context
- **THEN** the existing reporting and configured record views remain available with their current campaign, form, field-layout, filtering, and pagination behavior

