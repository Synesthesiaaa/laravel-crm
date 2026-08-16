# Agent Form Date Read-only Design

## Goal

Keep the regular CRM form date visible to agents without allowing agents to edit or forge the record date, while preserving the existing editable behavior for supervisors and administrators.

## Scope

This change applies only to the shared regular CRM form view used by `/forms/{type}` and the quick-form widget embed. It does not apply to the Agent Capture webform, admin form configuration, reports, or existing records.

## Behavior

- When the authenticated user has the exact `User::ROLE_AGENT` role, the date input is rendered with the HTML `readonly` attribute and remains populated with the current date.
- Team Leaders, Admins, and Super Admins continue to receive an editable date input.
- During regular CRM form request preparation, an Agent-submitted date is replaced with the current server date before validation and persistence.
- Non-Agent submissions retain the existing behavior and may submit another valid date.

The exact role constant is used instead of `User::isAgent()` because the current helper returns true for every non-empty role and would incorrectly make elevated roles read-only.

## Implementation

The shared `forms/_content.blade.php` view will pass a role-specific `readonly` prop to the existing `x-form.input` component. `FormSubmissionRequest::prepareForValidation()` will normalize the `date` field for Agents, ensuring the request passed to `FormController` and `FormSubmissionService` already contains the server date.

## Testing

- Render a regular form as an Agent and assert the date input is read-only.
- Render the same form as an elevated non-Agent user and assert the date input remains editable.
- Submit a different date as an Agent with a frozen clock and assert the stored record uses today’s server date.
- Preserve the existing form submission and widget rendering tests.
