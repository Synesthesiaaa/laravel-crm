## 1. Regression Tests

- [x] 1.1 Add a view test proving an Agent sees the regular CRM date input with `readonly`.
- [x] 1.2 Add a view test proving Team Leaders/Admins/Super Admins retain an editable date input.
- [x] 1.3 Add a submission test proving a forged Agent date is stored as the current server date.

## 2. Production Implementation

- [x] 2.1 Pass the exact Agent-role check to the shared regular CRM date input's existing `readonly` prop.
- [x] 2.2 Normalize Agent dates to `now()->toDateString()` in `FormSubmissionRequest::prepareForValidation()` before validation and persistence.

## 3. Verification and Handoff

- [x] 3.1 Run the focused view and form submission PHPUnit tests.
- [x] 3.2 Run Laravel Pint on modified PHP files.
- [x] 3.3 Verify the regular CRM form in the browser and check browser console health.
- [x] 3.4 Sync the completed capability and archive the OpenSpec change after validation.
