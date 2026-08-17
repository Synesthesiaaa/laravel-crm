# Regular CRM Form Review Confirmation Design

## Goal

Add a review-and-confirm step to regular CRM forms so users can inspect entered values before a record is saved.

## Scope

The shared regular CRM form partial is used by both the full-page form and Quick Form widget. The standalone Agent Capture webform and administrative forms are out of scope.

## Design

The existing `formVisibility` Alpine component will own the review state. The form's normal submit event will continue to be intercepted, but it will first validate the form and populate review rows from the same enabled, visible controls used to build the submission payload. A local modal in `forms/_content.blade.php` will display those rows and provide `Back to Form` and `Confirm & Save` actions. Confirmation will call the existing `submitForm()` method so Axios submission, validation errors, draft autosave, success reset, and toast behavior remain unchanged.

System metadata and hidden/disabled controls will not be shown. Select and multiselect controls will use display labels; checkboxes will use Yes/No or selected option labels; blank values will be shown as an em dash. The modal will use the application's existing modal styles and Alpine focus trapping.

## Verification

- JavaScript tests will cover review state, grouping/formatting, hidden/system-field exclusion, cancellation, and confirmation.
- Existing form view tests will assert that full-page and widget renders include the review hooks.
- Playwright will exercise a regular CRM form: fill values → click `Save Record` → verify the modal and values → cancel → reopen → confirm → verify the save outcome and browser console.
