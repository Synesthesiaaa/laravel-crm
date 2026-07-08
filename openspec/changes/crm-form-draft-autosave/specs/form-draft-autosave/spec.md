## ADDED Requirements

### Requirement: CRM form drafts persist across reloads and shared entry points
The system SHALL persist in-progress CRM form state in the browser for authenticated users. The draft SHALL be scoped by user identity and form context so the same draft is restored when the same user opens the same campaign and form type through the full CRM page or the widget iframe.

#### Scenario: Restore a draft after a reload
- **WHEN** an authenticated user enters data on a CRM form page and reloads the browser
- **THEN** the form SHALL restore the previously entered values from the browser draft store

#### Scenario: Share the same draft between full page and widget iframe
- **WHEN** an authenticated user enters data on the full CRM form page and later opens the same campaign and form type in the widget iframe
- **THEN** the widget SHALL load the same draft values for that user and context

### Requirement: CRM form submissions do not reload the page when JavaScript is available
The system SHALL submit CRM forms without a browser reload when JavaScript is available. The system SHALL send the submission asynchronously, SHALL keep the user on the same form view after success, and SHALL preserve the existing redirect-based POST flow as a fallback when JavaScript is unavailable.

#### Scenario: Successful asynchronous save
- **WHEN** the user submits a CRM form with valid data while JavaScript is enabled
- **THEN** the system SHALL save the record asynchronously and SHALL keep the current page loaded

#### Scenario: Non-JavaScript fallback remains available
- **WHEN** the browser does not run the CRM form JavaScript
- **THEN** the form SHALL still submit through the normal POST route and SHALL continue to use redirect-based feedback

### Requirement: Verified saves clear the draft and reset the form
The system SHALL clear the stored draft only after the server confirms the submission succeeded. After a verified save, the system SHALL reset the form back to its initial rendered state so the next record starts from a clean slate.

#### Scenario: Draft clears after a verified save
- **WHEN** the server confirms that a CRM form submission succeeded
- **THEN** the browser SHALL remove the saved draft for that form context
- **AND THEN** the form SHALL reset to the initial values rendered for that page load

### Requirement: Failed submissions preserve the draft and surface feedback
The system SHALL preserve the saved draft when validation fails, the request fails, or the browser loses connectivity. The system SHALL surface the save error to the user without discarding the in-progress values.

#### Scenario: Validation failure keeps the draft intact
- **WHEN** the user submits a CRM form with invalid data
- **THEN** the system SHALL show the validation feedback
- **AND THEN** the browser SHALL keep the saved draft values available for correction

#### Scenario: Network failure keeps the draft intact
- **WHEN** an asynchronous CRM form submission fails because the request cannot complete
- **THEN** the system SHALL preserve the draft and SHALL show a save failure message
