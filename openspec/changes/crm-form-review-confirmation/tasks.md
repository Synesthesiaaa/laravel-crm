## 1. Regression Tests

- [x] 1.1 Add JavaScript tests for opening review without submission, preserving values when cancelled, formatting select/multiselect/checkbox values, and excluding hidden/disabled/system fields.
- [x] 1.2 Extend the regular CRM form view render tests to assert the review event hook, review modal semantics, and confirmation controls are present in both full-page and widget renders.
- [x] 1.3 Run the new JavaScript tests and focused PHP view tests to confirm the new assertions fail before implementation.

## 2. Alpine Review State

- [x] 2.1 Add review state and DOM-driven review-row collection to `resources/js/form-visibility.js`, grouping repeated controls by normalized field name and formatting option labels, multiselects, checkboxes, percentages, and empty values.
- [x] 2.2 Add open, cancel, and confirm methods that validate before review, prevent network submission until confirmation, and delegate confirmed saves to the existing `submitForm()` method.
- [x] 2.3 Run the JavaScript tests and focused PHP view tests until the review state and formatting assertions pass.

## 3. Review Modal Markup

- [x] 3.1 Change the shared regular CRM form submit hook in `resources/views/forms/_content.blade.php` to open review state while retaining native validation.
- [x] 3.2 Add the accessible, scrollable review modal with field rows, close/back controls, and an explicit `Confirm & Save` button inside the shared form partial.
- [x] 3.3 Run `npm run build` and the focused tests to verify the full-page and widget assets compile and existing form behavior remains intact.

## 4. Verification

- [x] 4.1 Start the local Laravel/Vite app using the existing project workflow and identify an authenticated regular CRM form route.
- [ ] 4.2 Use Playwright to verify the page identity, meaningful content, absence of framework overlays, console health, modal opening without a request, cancel preservation, and confirmation submission.
- [x] 4.3 Run the final focused test/build commands, inspect the diff, and mark all implementation tasks complete.
