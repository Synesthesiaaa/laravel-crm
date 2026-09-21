## Context

Regular CRM forms are rendered by `resources/views/forms/_content.blade.php` in both the full page and Quick Form widget surfaces. Their Alpine state is provided by `resources/js/form-visibility.js`; `submitForm()` currently collects enabled controls, sends the payload with Axios, and manages draft, feedback, toast, and reset behavior. The change must add a review step without changing the server contract or affecting the separate Agent Capture and administrative forms.

## Goals / Non-Goals

**Goals:**

- Stop the first click on `Save Record` from sending a request.
- Present a readable review of the current visible and enabled CRM fields.
- Keep conditional-field behavior, native validation, draft autosave, async errors, and success handling intact.
- Make the review modal available in both full-page and widget form renderings.

**Non-Goals:**

- No route, controller, request-validation, database, or API changes.
- No review step for Agent Capture webforms, admin configuration forms, filters, exports, or destructive actions.
- No new package or global modal-store behavior.

## Decisions

### Keep review state in `formVisibility`

The review state and field-formatting helpers will live in the existing `formVisibility` Alpine component. This is the shared behavior used by both regular CRM form renderings, so one implementation covers both surfaces without coupling the widget to the authenticated application layout. A global modal store was considered, but it would add coordination for the standalone widget and would not improve the form-specific payload handling.

### Review the same payload that will be submitted

When review opens, the component will call the existing form-value collector and build display rows from the same visible/enabled controls. Internal transport fields (`_token`, `campaign`, `form_type`, `lead_id`, `phone_number`, `request_id`, and other system-only fields) will be omitted. Conditional fields that are disabled or hidden will not appear. Selects will use option labels, multiselects will show selected option labels, checkboxes will show Yes/No or selected options, and empty values will use an explicit em dash.

### Keep confirmation separate from the existing submit method

The form submit event will open the review state. A modal `Confirm & Save` button will close the review state and call the existing `submitForm()` method, preserving the current Axios payload, error handling, draft persistence, reset, and toast behavior. The modal will use Alpine `x-cloak`, `x-show`, transitions, and the already-loaded focus plugin's `x-trap.noscroll` pattern.

### Validate before review and again before confirmation

The browser's normal constraint validation remains the first gate. The review-open method will also guard with `checkValidity()`/`reportValidity()` so programmatic or repeated interactions cannot bypass required fields. Confirmation will re-check validity before sending and will return to the form if the form is no longer valid.

## Risks / Trade-offs

- [Dynamic controls may be represented by several DOM elements] → Group controls by normalized field name and use control type/selected options to produce one review row per logical field.
- [The review modal could expose transport metadata] → Maintain an explicit excluded-name list and skip hidden/disabled controls.
- [A long form may create a tall review list] → Reuse the existing scrollable modal styling and constrain the review list height with internal scrolling.
- [Review UI regressions could be missed by server tests] → Add JavaScript behavior tests and verify the rendered full-page form and modal interaction through Playwright.
