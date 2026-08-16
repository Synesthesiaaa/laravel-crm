## Why

Agents currently receive an editable date field on regular CRM forms, which allows the submitted record date to be changed away from the actual submission date. The date should remain visible for agent context while the application preserves the current date for agent-created records.

## What Changes

- Render the regular CRM form date field as read-only for users with the Agent role.
- Keep the date field editable for Team Leaders, Admins, and Super Admins.
- Normalize agent submissions to the current server date so a forged request cannot change the stored date.
- Leave the separate Agent Capture webform and its fields unchanged.

## Capabilities

### New Capabilities

- `agent-form-date-readonly`: Defines role-specific date visibility/editability and server-side date integrity for regular CRM form submissions.

### Modified Capabilities

None.

## Impact

- Regular CRM form Blade rendering in `resources/views/forms/_content.blade.php`.
- Regular CRM form request normalization in `app/Http/Requests/FormSubmissionRequest.php`.
- Feature coverage in `tests/Feature/FormShowViewRenderTest.php` and `tests/Feature/FormSubmissionTest.php`.
- No new dependencies, migrations, routes, or changes to Agent Capture webforms.
