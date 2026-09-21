## Context

The CRM already stores its own campaign state for CRM pages, while the floating phone widget uses a separate Vicidial campaign for telephony actions. The problem is not the CRM campaign itself; it is that a mismatch between CRM and Vicidial campaign values can still look like a softphone startup failure instead of a normal telephony session running under a different campaign.

## Goals / Non-Goals

**Goals:**
- Preserve the CRM campaign selection and session value unchanged.
- Let Vicidial session login, verify, pause, logout, and reconnect continue on the Vicidial campaign even when it differs from the CRM campaign.
- Treat live Vicidial confirmation as the readiness signal and stop using campaign equality as a failure trigger.
- Keep real transport, authentication, and iframe-load failures visible.

**Non-Goals:**
- Changing the login-page campaign picker.
- Introducing a new campaign mapping table or synchronization job.
- Reworking unrelated CRM pages that legitimately depend on the CRM campaign.

## Decisions

- Split the Vicidial session requests from the CRM campaign gate in `routes/web.php` so the softphone path is not forced through CRM campaign selection. Alternative: special-case the middleware. Rejected because route-level separation is clearer and keeps the CRM contract intact.
- Keep `session('campaign')` as CRM state and `session('vicidial_campaign')` as telephony state. Alternative: mirror one value into the other. Rejected because it reintroduces coupling and can overwrite legitimate CRM context.
- Continue to mark a session `ready` when Vicidial confirms a live agent, regardless of the CRM campaign value. Alternative: fail on mismatch and force users to align campaigns. Rejected because it creates the timeout and connecting regression the user wants removed.
- Resolve Vicidial session requests against the campaign-specific server when present, then fall back to the active default Vicidial server when the campaign is not registered in CRM. Alternative: require every telephony campaign to have a CRM campaign row. Rejected because it breaks off-CRM Vicidial campaigns and surfaces false connection failures.
- Preserve the current timeout and iframe error handling for real request, network, or iframe failures. Alternative: collapse all failures into `ready` or `idle`. Rejected because it would hide genuine connection problems.

## Risks / Trade-offs

- Route separation could expose places that still assume `campaign` middleware ran first. Mitigation: cover the Vicidial session endpoints with regression tests and keep CRM routes on the existing middleware.
- Relaxing mismatch handling may make genuine misconfiguration harder to notice. Mitigation: keep explicit failure messages for missing credentials, missing server config, and iframe load errors.
- If no active Vicidial server exists at all, the session can still fail. That is a real infrastructure/configuration error and should remain visible.

## Migration Plan

- Deploy the route and state changes together so the widget never sees mixed behavior.
- Update the Vicidial session and widget tests before implementation is merged.
- Roll back by restoring the CRM campaign gate on Vicidial session routes if an unexpected regression appears.

## Open Questions

- None. The scope is limited to keeping CRM and Vicidial campaign state separate and removing mismatch-based softphone failures.
