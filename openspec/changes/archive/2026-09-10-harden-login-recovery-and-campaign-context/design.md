## Context

The guest login view already uses the CRM's shared theme tokens, configured brand lockup, native form controls, and campaign-aware request fields. The controller accepts an omitted campaign and falls back to the first configured campaign, while authentication remains rate-limited and privacy-safe. The critique identified a missing in-flight state, weak recovery guidance, and unnecessary campaign selection when only one campaign is configured.

The change is intentionally limited to the guest login experience. It must remain compatible with the existing POST route, `LoginRequest`, session campaign resolution, authentication flow, and progressive enhancement when JavaScript is unavailable.

## Goals / Non-Goals

**Goals:**

- Make submission progress visible and announced without changing the server-side login protocol.
- Prevent accidental duplicate submits while the browser is navigating.
- Provide field-level feedback, password visibility, and a clear supervisor/help-desk recovery message.
- Present one configured campaign as immutable context while keeping a native selector for multiple campaigns.
- Preserve keyboard access, reduced-motion behavior, privacy-safe errors, and compact mobile usability.

**Non-Goals:**

- Do not add password-reset infrastructure or a new public support endpoint.
- Do not invent a supervisor email address, phone number, or external URL; the UI will direct users to their existing supervisor/help-desk process.
- Do not change authentication, rate limiting, campaign authorization, session handling, or telephony bootstrap behavior.
- Do not add a frontend dependency or move the small login interaction into a new build entrypoint.

## Decisions

### Use progressive enhancement in the existing login view

The form remains a normal POST form. A small inline script listens for submit, sets `aria-busy`, disables the button, changes its label to “Signing in…”, and updates a live status region. A normal page response resets the state, so Laravel validation and rate-limit errors remain authoritative.

Alternative considered: submit through `fetch` and manually render the response. Rejected because it would duplicate Laravel's redirect/session/error behavior and create avoidable authentication edge cases.

### Render campaign context based on available campaign count

When exactly one campaign is available, render its name and a hidden input only if the request contract needs the value; the visible UI is read-only and makes the starting workspace clear. When multiple campaigns are available, retain the existing native select and one concise helper sentence. When none are available, render no campaign control and preserve the controller's existing behavior.

Alternative considered: always keep the selector for visual consistency. Rejected because it asks the user to make a redundant decision and was the main progressive-disclosure finding.

### Keep recovery guidance factual and non-configured

Field-level errors will be rendered from Laravel's error bag, while the global alert will point users to the highlighted fields. A password reveal button will reduce correction cost. A short help block will say to contact a supervisor or help desk for access or lockout support, without exposing an unverified contact detail.

Alternative considered: link to `MAIL_FROM_ADDRESS`. Rejected because a sender address is not guaranteed to be a support channel and could be a no-reply mailbox.

### Keep the UI token-based and motion-aware

New states use existing surface, border, text, danger, primary, and focus tokens. The progress indicator will be CSS-only and will stop animating under `prefers-reduced-motion: reduce` while the text announcement remains visible.

## Risks / Trade-offs

- [Risk] A user may remain on the page if the network request fails before navigation. → Mitigation: expose a clear in-flight message and retain the disabled state until the browser returns a response; the page reload/error response restores the normal form.
- [Risk] Duplicating a privacy-safe error in the alert and field feedback may add verbosity. → Mitigation: keep the global alert concise and put the actionable explanation adjacent to the affected field.
- [Risk] A hidden campaign input could be mistaken for an authorization boundary. → Mitigation: keep campaign validation and fallback in the existing controller/request; the view only reflects available configuration.
- [Risk] A help message without a direct link may not satisfy every deployment's support process. → Mitigation: use neutral supervisor/help-desk wording now and leave a configured contact channel as a future, separately approved capability.

## Migration Plan

No database or route migration is required. Deploy the Blade/CSS/test changes with the existing application build. Rollback is a file-level revert of the login view and stylesheet changes; the controller and authentication contract remain unchanged.

## Open Questions

None for this scoped implementation. A concrete support URL or email can be added later once the deployment owner supplies an approved channel.
