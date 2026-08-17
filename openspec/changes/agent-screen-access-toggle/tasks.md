## 1. Regression Tests

- [x] 1.1 Add PHPUnit service tests proving `agent_screen_access` defaults to disabled and is persisted by `TelephonyFeatureService::updateMany()`.
- [x] 1.2 Add PHPUnit feature tests proving the Super Admin configuration control persists the flag and non-Super Admin users cannot see or directly access disabled Agent Screen surfaces.
- [x] 1.3 Run the new tests before production changes and confirm they fail for the missing feature behavior.

## 2. Feature Flag and Configuration

- [x] 2.1 Extend `TelephonyFeatureService` with the Agent Screen feature key, a disabled default for that key, and existing cache/audit update behavior.
- [x] 2.2 Add the Agent Screen access checkbox and explanatory text to the Super Admin Telephony Features configuration form.

## 3. Visibility and Enforcement

- [x] 3.1 Update the feature middleware to return an HTML 403 response for browser routes while preserving the existing JSON 403 response for API requests.
- [x] 3.2 Gate the Agent Screen page, Agent Capture webform, and Agent Capture submission routes with the Agent Screen feature flag.
- [x] 3.3 Hide disabled Agent Screen entries from the sidebar and global search while preserving Super Admin access conventions.

## 4. Verification and Handoff

- [x] 4.1 Run Laravel Pint on modified PHP files and execute all affected PHPUnit tests.
- [ ] 4.2 Start the local application and use Playwright to verify disabled/enabled navigation, configuration, and direct-access behavior.
- [ ] 4.3 Sync the final implementation into the OpenSpec main spec and archive the completed change.
