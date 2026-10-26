## ADDED Requirements

### Requirement: Local CRM drafts retain only approved fields for a bounded time
The system SHALL persist only logical form fields explicitly marked safe for browser draft recovery. Draft payloads SHALL expire after eight hours, expired/corrupt drafts and legacy unrestricted draft keys SHALL be removed, and graceful logout SHALL clear all CRM draft keys. Draft keys SHALL NOT contain phone values.

#### Scenario: Draft-safe field is persisted
- **WHEN** a user edits a field explicitly marked safe for drafts and the form autosaves
- **THEN** only opted-in logical field values are stored in the browser draft payload

#### Scenario: Sensitive field is not opted in
- **WHEN** a form field is not marked safe for drafts
- **THEN** its value is neither persisted nor restored from a draft

#### Scenario: Draft expires
- **WHEN** a draft is more than eight hours old or has an invalid timestamp/payload
- **THEN** it is removed and is not restored

#### Scenario: User logs out
- **WHEN** the graceful logout flow runs
- **THEN** all CRM draft keys are removed before the logout form is submitted
