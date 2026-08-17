## Why

Regular CRM form submissions currently send the entered values as soon as the user activates `Save Record`. A review step will give users an opportunity to catch incorrect customer or application details before the asynchronous submission creates a record.

## What Changes

- Add a pre-submission review modal to the shared regular CRM form flow.
- Show visible, enabled form fields and their current formatted values before any network request is made.
- Allow users to return to the form without losing their input.
- Require an explicit `Confirm & Save` action before the existing asynchronous submission runs.
- Preserve existing browser validation, server validation handling, draft autosave, success reset, and toast behavior.
- Leave Agent Capture webforms and administrative/filter/destructive forms unchanged.

## Capabilities

### New Capabilities

- `crm-form-review-confirmation`: Review and explicitly confirm regular CRM form data before submission.

### Modified Capabilities

None.

## Impact

- Shared regular CRM form view: `resources/views/forms/_content.blade.php`.
- Shared Alpine form state: `resources/js/form-visibility.js`.
- Frontend regression tests for review-state and value formatting.
- No route, controller, database, API, or dependency changes are required.
