# VICIdial Agent Capture Webform Design

## Purpose

Replace the day-to-day CRM Agent Screen workspace with a focused webform that VICIdial loads for each campaign call. Agents must already hold an authenticated CRM session. The webform uses the existing Agent Screen Field configuration and persists existing Agent Capture Records; it does not use the selected CRM form's Form Fields or normal form-submission table.

## Goals

- Let an administrator select one active CRM form for each campaign's VICIdial webform configuration.
- Keep Agent Screen Fields as the source of truth for the webform's visible capture inputs, validation, conditional visibility, ordering, and VICIdial field mappings.
- Generate a copy-ready VICIdial Web Form URL for each configured campaign.
- Prefill Agent Screen Fields from VICIdial's call URL parameters when a call arrives.
- Preserve existing Agent Capture Record storage and configured `post` / `both` writeback to VICIdial.
- Require an authenticated CRM agent; do not introduce a public or token-authenticated capture route.

## Non-goals

- Do not embed the full CRM Agent Screen, floating softphone, call controls, navigation, or polling inside VICIdial's webform frame.
- Do not replace or migrate existing Form Fields, Form Submission tables, or reporting based on those forms.
- Do not save a record merely because VICIdial loaded the webform.
- Do not let a query parameter choose a different campaign or form than the administrator configured.

## Configuration

### Campaign webform selection

Add an optional `agent_webform_form_id` to `campaigns`. It references an active `forms` record that belongs to the same campaign. The selected form supplies the webform name and establishes that a specific CRM form is intentionally associated with that campaign; it does not supply the webform fields or submission destination.

The existing Admin -> Agent Screen page will add a campaign-level configuration section:

1. Select an active CRM form belonging to the selected campaign.
2. Save the selection after validating campaign ownership.
3. Display a copy-ready VICIdial Web Form URL only when the selection is valid.
4. Keep the current Agent Screen Field editor unchanged as the field configuration surface.

Changing the selected form must clear the campaign configuration cache. Deactivating or moving a selected form must make that campaign's agent webform unavailable until an administrator selects a valid active form again.

### Generated VICIdial URL

The generated URL will target a dedicated authenticated route keyed by campaign, for example:

```text
VARhttps://crm.example.com/agent-webforms/mbsales?lead_id=--A--lead_id--B--&phone_number=--A--phone_number--B--&email=--A--email--B--
```

The URL generator always includes `lead_id` and `phone_number`. It appends one query parameter for each distinct `vici_field` assigned to an Agent Screen Field whose direction is `get` or `both`. It must omit empty, duplicate, and non-read (`post` or `none`) mappings. VICIdial substitutes its `--A--field--B--` placeholders when it opens the campaign webform.

An administrator pastes the generated value into the matching VICIdial campaign's Web Form setting and configures the campaign to launch that webform for calls.

## Request and capture flow

1. A VICIdial campaign receives or presents a call and loads its configured webform URL in the VICIdial frame.
2. The browser requests `GET /agent-webforms/{campaign}` with VICIdial's lead parameters. The route requires the existing CRM authentication middleware.
3. The controller resolves the route campaign, verifies that it is active and has a selected active CRM form owned by that campaign, then retrieves the campaign's ordered Agent Screen Fields.
4. For every Agent Screen Field mapped to a `get` or `both` VICIdial field, the controller copies the matching URL value into the field key used by the form. `lead_id` and `phone_number` are retained as capture metadata.
5. A dedicated slim Blade view renders only the selected form title, lead/call context, Agent Screen Fields, validation errors, save feedback, and the submit control. It uses the existing visibility behaviour and responsive field-width conventions.
6. The agent reviews or edits the prefilled values and submits. The client sends the campaign, lead metadata, field values, and visible-field list to the existing authenticated Agent Capture endpoint.
7. The existing endpoint validates visible required fields, writes an `AgentCaptureRecord`, and pushes configured writable `post` / `both` field values to VICIdial.
8. The webform displays an in-frame success message and retains submitted values until VICIdial opens the next call's webform.

The standard Form Controller and Form Submission Service are not used for this capture flow. The associated CRM form is retained only as the administrator's specific, named form selection for the campaign.

## Security and data integrity

- The dedicated webform route is protected by CRM authentication. A user who has not logged into the CRM is sent through the existing login flow and cannot submit capture data.
- The route campaign is authoritative. Incoming `campaign`, `form`, `user`, or similarly named VICIdial URL values are display/input data only and cannot override the selected campaign or form.
- The authenticated CRM user's identity remains the `agent` and `user_id` stored on the capture record. A VICIdial `user` query parameter is never trusted for attribution.
- A selected form must belong to the URL campaign and be active. Missing, inactive, or cross-campaign configuration renders a clear no-capture configuration state and never accepts a submission for that configuration.
- `get` and `both` mappings may prefill values. Missing or unmapped source values leave fields empty. Prefill alone does not create or update any record.
- Existing Agent Capture validation, visible-required-field behavior, percentage normalization, writeback rules, and ownership checks for optional `call_session_id` remain in force.
- VICIdial and the CRM are assumed to use HTTPS subdomains of the same parent site. The CRM session therefore remains a same-site authenticated session when the CRM page is framed by VICIdial. Production configuration must preserve secure HTTPS cookies.

## Error handling

- Missing login: follow the existing CRM authentication redirect; do not render the capture form anonymously.
- No selected or inactive campaign form: show a concise configuration error to the logged-in user and do not show a submit action.
- Invalid field mapping or missing VICIdial parameter: leave only that field unfilled and allow the agent to complete it manually.
- Client or server validation failure: retain all typed values and show field-level errors.
- VICIdial writeback failure: preserve current Agent Capture behavior—store the record, log the failed writeback, and return the existing successful capture response.

## Implementation boundaries

- New campaign configuration persistence, model relationship/cast, validation, controller action, route, dedicated webform Blade view, and focused client-side form component are expected.
- The Agent Screen administration controller and view will be extended rather than replaced.
- Existing Agent Screen Fields, Agent Capture Records, Agent Capture API, Form models, and their current tests will be reused and extended.
- The current full `/agent` page may remain available during this change, but the generated VICIdial URL will always target the slim agent-capture webform.

## Verification

Automated coverage must prove:

1. unauthenticated users cannot access or submit the agent-capture webform;
2. an administrator can select only an active form that belongs to the selected campaign;
3. the admin page renders a generated URL containing the required and configured VICIdial placeholders;
4. a valid configured webform renders the selected title and Agent Screen Fields;
5. `get` and `both` VICIdial URL values prefill the mapped Agent Screen field keys while `post` and `none` mappings do not;
6. form submission creates an Agent Capture Record and preserves existing required/visibility and VICIdial writeback behavior;
7. missing or inactive configuration cannot render a submittable webform.

Before release, verify in a browser that an already logged-in CRM agent can open the generated URL in a VICIdial-like iframe, receives prefilled call data, submits successfully, and sees the compact frame-safe layout. Then paste the generated URL into a test VICIdial campaign and verify it reloads with the expected lead data on a real test call.

## Rollout and rollback

1. Deploy the schema/configuration and webform route while keeping the existing Agent Screen available.
2. An administrator chooses a form, configures Agent Screen Field mappings, and copies the generated URL to a test VICIdial campaign.
3. Validate the real call workflow before configuring production campaigns.
4. Roll back by removing the generated URL from VICIdial or clearing the campaign's selected agent webform. Existing Agent Screen and historical Agent Capture Records remain intact.
