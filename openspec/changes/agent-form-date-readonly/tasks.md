## 1. Regression Tests

- [ ] 1.1 Add a view test proving an Agent sees the regular CRM date input with `readonly`.
- [ ] 1.2 Add a view test proving Team Leaders/Admins/Super Admins retain an editable date input.
- [ ] 1.3 Add a submission test proving a forged Agent date is stored as the current server date.

## 2. Production Implementation

- [ ] 2.1 Pass the exact Agent-role check to the shared regular CRM date input's existing `readonly` prop.
- [ ] 2.2 Normalize Agent dates to `now()->toDateString()` in `FormSubmissionRequest::prepareForValidation()` before validation and persistence.

## 3. Verification and Handoff

- [ ] 3.1 Run the focused view and form submission PHPUnit tests.
- [ ] 3.2 Run Laravel Pint on modified PHP files.
- [ ] 3.3 Verify the regular CRM form in the browser and check browser console health.
- [ ] 3.4 Sync the completed capability and archive the OpenSpec change after validation.
