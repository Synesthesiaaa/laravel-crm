## Context

The regular CRM form view in `resources/views/forms/_content.blade.php` renders the system `date` field through the shared form input component. The current form request validates that field but accepts any valid date, so an Agent can change it in the browser or in a direct request. The same view is embedded by the quick-form widget; the separate Agent Capture webform has a different implementation and is outside this change.

## Goals / Non-Goals

**Goals:**

- Keep the regular CRM form date visible to Agents.
- Make the date input read-only for the exact `User::ROLE_AGENT` role.
- Keep the date editable for Team Leaders, Admins, and Super Admins.
- Ensure Agent submissions persist the current server date even if the request is forged.

**Non-Goals:**

- Change Agent Capture webforms.
- Change admin form configuration or existing stored records.
- Add new dependencies, routes, migrations, or database columns.

## Decisions

1. **Use the shared Blade view and existing input component.** The date field is rendered once in `forms/_content.blade.php`, which covers both the full regular form and its widget embed. The existing `readonly` prop on `x-form.input` will be reused instead of introducing a new component or JavaScript behavior.

2. **Check the exact Agent role constant.** The view and request will compare the authenticated user's role with `User::ROLE_AGENT`. The existing `User::isAgent()` helper is not suitable because it currently returns true for every non-empty role, which would incorrectly affect elevated roles.

3. **Normalize the date in `FormSubmissionRequest::prepareForValidation()`.** This keeps the server-side guard at the request boundary before validation and before the controller passes data to the submission service. The current date is used, so an Agent cannot bypass the restriction by removing `readonly` or posting directly.

4. **Preserve non-Agent behavior.** Requests from non-Agent roles are not rewritten, so supervisors and administrators retain the current ability to submit a valid historical or future date.

## Risks / Trade-offs

- **[Client-side read-only can be bypassed]** → Normalize the date server-side for Agent requests before validation.
- **[User role helper is overly broad]** → Compare against `User::ROLE_AGENT` directly and cover an elevated-role rendering test.
- **[Form remains open across midnight]** → The persisted Agent date is the server date at submission time, which is the authoritative record date.

## Migration Plan

No migration is required. Deploy the view, request, and test changes together. Rollback consists of reverting those code changes; existing records are unaffected.

## Open Questions

None.
