## MODIFIED Requirements

### Requirement: Safe logout and webhook hardening
The system SHALL complete logout cleanup without double-submit behavior and SHALL reject public webhook traffic whenever its required shared secret is missing or invalid in any runtime environment.

#### Scenario: Logout during active telephony use
- **WHEN** a signed-in user logs out while a telephony session is active
- **THEN** the client SHALL hang up or destroy the telephony runtime once, clear any embedded session frame, and submit the logout form once

#### Scenario: Missing webhook secret
- **WHEN** either public telephony event POST webhook receives a request while its shared secret is not configured
- **THEN** the request SHALL return service unavailable and SHALL NOT process or acknowledge the event as processed

#### Scenario: Invalid webhook secret
- **WHEN** either public telephony event POST webhook receives a missing or incorrect `X-Webhook-Secret` header while a secret is configured
- **THEN** the request SHALL return unauthorized and SHALL NOT process the event

#### Scenario: Valid webhook secret
- **WHEN** either public telephony event POST webhook receives the configured shared secret
- **THEN** the event SHALL follow its existing validation and processing behavior
