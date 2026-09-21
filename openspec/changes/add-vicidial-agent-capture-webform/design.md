## Context

The application has two related but separate surfaces: the full authenticated Agent Screen, which owns Agent Screen Field configuration and Agent Capture Records, and configurable CRM Forms, which use Form Fields and dynamic submission tables. VICIdial can load an HTTP Web Form per campaign and substitute call variables into its query string. The change must connect that VICIdial entry point to Agent Screen Fields without merging the two persistence models.

The route will be loaded in a VICIdial frame while the agent is already logged into the CRM. VICIdial and the CRM are deployed as HTTPS subdomains of the same parent domain, so the CRM session remains same-site when the CRM page is framed. The route campaign must be explicit and must not change the agent's normal CRM campaign session.

## Goals / Non-Goals

**Goals:**

- Persist one active CRM Form selection per campaign as the named webform configuration.
- Generate a copy-ready `VAR` URL using VICIdial `--A--field--B--` substitutions.
- Render only ordered Agent Screen Fields in a compact authenticated page.
- Map `get`/`both` query parameters into Agent Screen field keys before rendering.
- Reuse the existing Agent Capture API, record storage, validation, and VICIdial writeback.
- Preserve the existing full Agent Screen and normal Form Controller behavior.

**Non-Goals:**

- No public webform, shared-secret authentication, or login bypass.
- No migration of Agent Capture Records into Form Submission tables.
- No CRM softphone, call controls, websocket polling, sidebar, or navigation in the frame.
- No automatic record creation on webform load.

## Decisions

### 1. Store the selected form as a campaign foreign key

Add nullable `campaigns.agent_webform_form_id` referencing `forms.id` with `nullOnDelete`. Validate that the selected form is active and belongs to the submitted campaign. The Form relation provides a stable human-facing name and explicit administrator choice, while Agent Screen Fields remain the field source.

Alternatives considered:

- Store a form code in a JSON campaign config. Rejected because it duplicates the existing Forms table and weakens referential validation.
- Always select the first active form. Rejected because the requirement calls for one administrator-selected specific form.
- Add a separate webform table. Rejected because no additional per-webform data is needed beyond the campaign-to-form association.

### 2. Keep mapping and URL generation in a dedicated service

Create `AgentCaptureWebformService` with configuration lookup, query-to-field prefill, and URL-template generation methods. The service will query active campaign/form state directly so the explicit route campaign does not mutate the session or depend on the cached campaign-selection middleware.

Alternatives considered:

- Put mapping logic in the controller. Rejected because the same mapping is needed by admin URL generation and webform rendering.
- Reuse `FormController`. Rejected because it would couple Agent Screen Fields to Form Fields and the normal Form Submission Service.
- Put mapping in JavaScript only. Rejected because URL generation and server-rendered values must be deterministic and testable before the browser runs.

### 3. Use an authenticated campaign route with a standalone Blade view

Add `GET /agent-webforms/{campaign}` under `auth` only. The controller resolves the path campaign, selected active form, and Agent Screen Fields, then renders a standalone Blade document that loads the existing CSS/JS assets without `layouts.app`. The path campaign is authoritative; query values are limited to field prefill metadata.

Alternatives considered:

- Put the route in the existing `auth,campaign` group. Rejected because `EnsureCampaignSelected` would overwrite or reject an explicit VICIdial campaign based on the CRM session campaign.
- Render the full `/agent` page. Rejected because telephony controls and polling are not frame-safe and duplicate VICIdial ownership.
- Make the route public and sign every field value. Rejected because the agreed access model requires the agent to be logged into CRM.

### 4. Reuse the existing Agent Capture API from a small Alpine component

The standalone form will submit `{campaign_code, call_session_id: null, lead_id, phone_number, capture_data, visible_fields}` to `/api/agent/capture`. A new `agentCaptureWebform` component will compose the existing `formVisibility` behavior, collect visible controls, show inline feedback, and retain successful values.

Alternatives considered:

- Add a second capture endpoint. Rejected because it would duplicate required-field filtering, percentage normalization, and VICIdial writeback.
- Submit through `FormController`. Rejected because the webform must persist Agent Capture Records.
- Clear the form after save. Rejected because retaining values gives the agent confirmation and avoids losing context before VICIdial loads the next call.

## Risks / Trade-offs

- [A campaign has no active selected form] → The controller renders an explicit no-configuration state without a submit action; the admin page exposes the configuration and generated URL only after a valid selection.
- [A VICIdial parameter is missing or mapped to an unsupported field] → Prefill is best-effort; the field remains editable/blank and no record is written until normal submission validation passes.
- [An attacker changes `campaign`, `form`, or `user` query parameters] → The route campaign and selected form come from server-side configuration, and record attribution comes from the authenticated CRM user.
- [The frame is loaded after the CRM session expires] → Existing `auth` middleware redirects to CRM login; no anonymous capture is accepted.
- [VICIdial writeback fails after a record is saved] → Preserve the current Agent Capture behavior: log the writeback failure and keep the stored capture record.
- [Browser frame dimensions are narrow] → Use the existing responsive field-width classes and a standalone frame-safe view, then verify at VICIdial-sized viewport dimensions.

## Migration Plan

1. Run the additive campaign migration; all existing campaigns remain unconfigured and the full Agent Screen remains available.
2. Deploy the service, route, view, admin selector, and Alpine component.
3. In Admin -> Agent Screen, select one active CRM form per campaign and configure Agent Screen Field `get`/`post`/`both` mappings.
4. Copy the generated `VAR` URL into a test VICIdial campaign Web Form setting and verify an authenticated test call.
5. Roll out to production campaigns after successful prefill, submit, and writeback checks.
6. Roll back by clearing the campaign selection or removing the VICIdial Web Form URL; existing records and the original Agent Screen are unaffected. The nullable column can be removed in a later rollback migration if the feature is permanently abandoned.

## Open Questions

None. The campaign, form, authentication, mapping, persistence, and browser-frame decisions were approved during design review.
