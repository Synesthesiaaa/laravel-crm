## Context

Authenticated campaign users currently receive an Agent Screen navigation entry, and the Agent Screen plus Agent Capture routes accept direct requests. Telephony feature flags already use `SystemSetting`, `TelephonyFeatureService`, a short cache, Super Admin bypass semantics, configuration activity logging, and the `telephony_feature` middleware alias. The change must reuse those conventions and must not alter existing telephony defaults.

## Goals / Non-Goals

**Goals:**

- Add one globally persisted Agent Screen access flag with a disabled default.
- Expose the flag in the existing Super Admin Telephony Features configuration tab.
- Use one source of truth for navigation visibility, global search, and endpoint enforcement.
- Return a browser-appropriate denial for HTML routes and preserve JSON denial responses for API routes.
- Cover both enabled and disabled behavior with PHPUnit tests.

**Non-Goals:**

- No new database table or package.
- No deletion or migration of Agent Screen field definitions.
- No changes to regular CRM form submission fields or authorization.
- No changes to the existing Super Admin-only Agent Screen configuration routes.

## Decisions

### Use the existing telephony feature service

Add `agent_screen_access` to `TelephonyFeatureService` and keep the setting key under the existing `telephony_feature_` namespace. The service will use a per-feature default map so current flags remain enabled when absent while the new flag is disabled when absent. This preserves the established cache, update, and activity-log behavior.

Alternatives considered: a dedicated settings service would duplicate existing persistence and cache behavior; an `.env` value would not be changeable by a Super Admin without deployment.

### Gate all Agent Screen surfaces at both rendering and request time

Use the existing `telephony_feature` middleware on the Agent Screen page, Agent Capture webform page, and Agent Capture API submission route. Update the middleware to return an HTML 403 response for non-JSON requests while retaining its current JSON response for API requests. Sidebar, dashboard cards, and global search will conditionally omit Agent Screen links using the same service flag for all users while disabled. Super Admins retain direct access to the configuration area and existing management route so they can re-enable the feature.

Alternatives considered: hiding links only would leave direct URLs usable; route-only enforcement would leave misleading navigation and search results visible.

### Place the control with telephony feature access

Add the Agent Screen checkbox to Configuration → Telephony Features, using the existing form submission and validation endpoint. The label and help text will explicitly state that it controls Agent Screen and Agent Capture access.

## Risks / Trade-offs

- [A disabled flag could break an existing VICIdial call URL that opens an Agent Capture webform] → The configuration label will describe the affected surfaces, and Super Admins can re-enable the feature without code or deployment changes.
- [A cached flag could delay a change] → Reuse the existing `flush()` call after updates; subsequent requests reload the setting.
- [A hidden link could be mistaken for complete authorization] → Middleware enforcement covers all direct HTML and API requests.

## Migration Plan

No data migration is required. Deploying the code causes the new flag to default to disabled when no `telephony_feature_agent_screen_access` row exists. Super Admins can enable it from the existing Telephony Features configuration screen. Rollback removes the new code; the unused settings row is harmless and can remain for a later redeploy.

## Open Questions

None. Super Admins retain configuration access and direct management-route access while disabled, but navigation and search surfaces stay hidden until the feature is enabled.
