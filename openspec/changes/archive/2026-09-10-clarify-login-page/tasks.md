## 1. Clarify user-facing language

- [x] 1.1 Replace the broad login heading/subtitle with direct CRM destination and credential instructions.
- [x] 1.2 Label the campaign choice as an instruction and add concise helper text describing the post-login starting campaign.
- [x] 1.3 Give the theme toggle a specific initial accessible name/title while preserving dynamic theme updates.
- [x] 1.4 Replace generic invalid-credential feedback with privacy-safe recovery guidance.

## 2. Preserve semantics and presentation

- [x] 2.1 Connect campaign helper text to the native select without changing request names, values, or old-input behavior.
- [x] 2.2 Keep authentication routes, CSRF, branding, themes, focus indicators, reduced motion, touch targets, and responsive no-overflow behavior unchanged.

## 3. Verification

- [x] 3.1 Update focused login feature assertions for the clarified copy and invalid-credential message.
- [x] 3.2 Run the focused PHPUnit test, Pint, and the configured Vite build.
- [x] 3.3 Run one bounded Playwright pass for normal, theme, campaign, keyboard-focus, invalid-credential, responsive, and console-error states.
- [x] 3.4 Sync the canonical login-experience spec, validate OpenSpec, and archive the completed change.
