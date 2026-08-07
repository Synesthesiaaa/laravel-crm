## ADDED Requirements

### Requirement: Expanded activity entries show structured audit details

The system SHALL expose a readable expanded Activity Log entry containing actor identity, event context, resource context, request telemetry when available, and field-level before/after changes when available. The complete sanitized raw payload SHALL remain available for inspection.

#### Scenario: Model activity shows actor and field changes

- **WHEN** a Super Admin expands an activity for a changed model
- **THEN** the terminal shows the actor's name, username, role, and ID together with the resource, event, and each changed field's previous and new value

#### Scenario: Request activity shows request telemetry

- **WHEN** a Super Admin expands an authenticated request activity
- **THEN** the terminal shows the method, path, route, response status, IP address, user agent, and sanitized query parameters

#### Scenario: Activity without optional details remains readable

- **WHEN** an activity has no request metadata, actor, subject, or before/after values
- **THEN** the terminal shows the available event context without rendering errors or misleading values

#### Scenario: Sensitive values remain redacted in detail views

- **WHEN** an activity contains password, token, credential, authorization, or equivalent sensitive values
- **THEN** both structured details and raw JSON display the sanitizer's redaction marker instead of the original value
