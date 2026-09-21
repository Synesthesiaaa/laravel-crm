## Context

The floating softphone widget currently loads an allowed Vicidial campaign list, lets the agent choose from it, persists that choice to `vicidial_campaign`, and then reuses that value for login and follow-up session calls. That flow exists only to support the widget selector; the login page already has its own separate campaign picker and the rest of the telephony stack already resolves a usable campaign from session, user default, or config.

This change removes the widget-side selector and its supporting lookup/persistence code while keeping the underlying telephony campaign fallback chain intact.

## Goals / Non-Goals

**Goals:**
- Remove the campaign selector from the floating softphone widget.
- Eliminate the widget-only campaign lookup and persistence path.
- Keep Vicidial login, verify, pause, logout, iframe recovery, and status checks working with the existing resolved campaign source.
- Preserve the login-page campaign picker and existing session/bootstrap behavior.

**Non-Goals:**
- Changing login-page campaign selection.
- Changing the campaign fallback chain or the telephony session bootstrap model.
- Redesigning other telephony actions that already accept a campaign parameter.
- Introducing a replacement campaign picker in another part of the UI.

## Decisions

1. Remove the widget selector entirely instead of making it read-only.
   - Rationale: a disabled or read-only selector still implies widget-side campaign ownership and keeps the old lookup/persistence path alive.
   - Alternatives considered:
     - Keep it read-only. Rejected because it preserves redundant state and UI clutter.
     - Hide it behind a feature flag. Rejected because the goal is removal, not conditional behavior.

2. Delete the widget-only Vicidial campaign lookup endpoints and service.
   - Rationale: `/api/vicidial/session/agent-campaigns` and `/api/vicidial/session/select-campaign` exist only to support the removed widget flow.
   - Alternatives considered:
     - Keep the endpoints and return a single resolved campaign. Rejected because it keeps unused API surface and test burden.
     - Keep the endpoints as no-ops. Rejected because it makes stale clients harder to detect and still invites unnecessary maintenance.

3. Keep the existing resolved-campaign source chain unchanged.
   - Rationale: the widget still needs a stable campaign value for login/session actions, and the existing session/default/config fallback already provides that without requiring an agent campaign list.
   - Alternatives considered:
     - Force a single config-only campaign. Rejected because it would be a broader behavior change and would ignore existing user/session defaults.
     - Derive the campaign from Vicidial agent campaigns on every load. Rejected because that is the behavior being removed.

4. Treat the login page as out of scope.
   - Rationale: the login form uses CRM campaign selection for a different workflow and is not tied to the softphone widget selector.
   - Alternatives considered:
     - Remove login-page campaign selection too. Rejected for this change because it would widen the blast radius and change the user login flow.

## Risks / Trade-offs

- [Risk] Removing the widget-side canonicalization step means we no longer normalize the selected campaign against Vicidial campaign metadata. → Mitigation: keep the resolved campaign chain stable and continue to use the existing session value for all widget actions.
- [Risk] Any undocumented consumer of the deleted widget-only endpoints will break. → Mitigation: scope the change to internal widget-only routes, remove the UI call sites, and cover the deleted routes in tests.
- [Risk] If the current session/default campaign is malformed, the widget will no longer “recover” by choosing from an allowed campaign list. → Mitigation: this is already a data/config hygiene issue; keep the fallback chain and surface it through existing Vicidial session failures.

## Migration Plan

1. Ship the frontend and backend removals together so the widget never calls the deleted endpoints.
2. Update and run the Vicidial session feature tests to confirm the remaining login/status flows still use the resolved campaign source.
3. Smoke test the softphone widget after build/deploy to verify it opens without a selector and still logs in with the expected campaign.
4. Rollback is straightforward: restore the removed routes, controller methods, service, and widget UI if the change needs to be reversed.

## Open Questions

None. The scope is locked to the widget-only selector removal, and the login-page campaign picker stays in place.
