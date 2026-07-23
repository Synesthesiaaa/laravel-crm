## 1. Campaign configuration and mapping

- [x] 1.1 Add nullable `campaigns.agent_webform_form_id` with a foreign key to `forms.id`, update `Campaign` fillable/casts/relation, and add model coverage.
- [x] 1.2 Implement `AgentCaptureWebformService::configuration()` to require an active campaign and active same-campaign Form, returning ordered Agent Screen Fields.
- [x] 1.3 Implement `AgentCaptureWebformService::prefill()` and `vicidialUrl()` with `lead_id`/`phone_number`, unique `get`/`both` VICIdial placeholders, and mapped query values; add unit coverage.

## 2. Administrator configuration

- [x] 2.1 Add `SaveAgentScreenWebformRequest` with super-admin authorization and active same-campaign Form validation.
- [x] 2.2 Add the Super Admin Agent Screen webform update route and controller action, persist the selected form, and clear the campaign cache.
- [x] 2.3 Extend the Agent Screen admin view with a form selector and copy-ready generated VICIdial URL; add admin feature coverage for valid and cross-campaign selections.

## 3. Authenticated webform endpoint

- [x] 3.1 Add `GET /agent-webforms/{campaign}` under `auth` middleware only and implement `AgentCaptureWebformController::show()` using the mapping service.
- [x] 3.2 Add a standalone frame-safe Blade view that renders selected Form metadata and only ordered Agent Screen Fields, including visibility, required state, field types, and prefill values.
- [x] 3.3 Add feature coverage for authentication, mapped GET/BOTH prefill, route-campaign authority, and the no-configuration state.

## 4. Capture submission and writeback

- [x] 4.1 Add `agentCaptureWebform` Alpine behavior, compose the existing `formVisibility` state, collect visible fields, and submit the existing Agent Capture API payload.
- [x] 4.2 Bind the component to the standalone view, show inline success/validation feedback, retain values after success, and keep telephony polling/navigation out of the frame.
- [x] 4.3 Re-run existing Agent Capture API tests and add a focused regression only if a webform payload exposes an uncovered persistence or writeback edge case.

## 5. Verification and rollout

- [x] 5.1 Run focused PHPUnit coverage for campaign configuration, service mapping, webform rendering, and Agent Capture API behavior.
- [x] 5.2 Run Pint and `npm run build`, then verify the named route and migration status.
- [ ] 5.3 Use Playwright to verify authenticated frame rendering, prefill, submit success, required-field errors, and absence of CRM telephony controls at VICIdial-sized dimensions.
  - Server-rendered coverage and the unauthenticated login redirect are verified; authenticated browser interaction remains pending an approved active CRM session.
- [x] 5.4 Document the generated URL paste/test step in the implementation handoff and leave the full Agent Screen available for rollback.
