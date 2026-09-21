## Why

CRM form submissions still rely on a browser POST followed by a redirect, which makes the page feel unstable on soft navigation and can drop in-progress data if the browser reloads or the session is interrupted. We need a shared form flow that saves drafts locally, submits without a full page reload, and only clears the form after the saved record is confirmed.

## What Changes

- Replace the classic full-page submit behavior on CRM form pages with an AJAX-based submission path.
- Persist in-progress form state in the browser so reloads, soft navigation, and accidental closes can restore the draft.
- Clear draft state only after the server confirms the submission succeeded and the record has been stored.
- Keep the existing form validation and persistence rules intact on the server.
- Apply the same behavior to both standard CRM form pages and the embedded quick-form widget, since they render the same form system.

## Capabilities

### New Capabilities
- `form-draft-autosave`: Shared CRM form draft persistence and no-reload submission for all form pages and embedded form widgets.

### Modified Capabilities

## Impact

- `resources/views/forms/_content.blade.php`
- `resources/views/forms/widget.blade.php`
- `app/Http/Controllers/FormController.php`
- `app/Http/Requests/FormSubmissionRequest.php`
- `app/Services/FormSubmissionService.php`
- `resources/js/app.js`
- new shared frontend module for form draft persistence and AJAX submit handling
- tests covering form submission, draft restore, and draft reset behavior
