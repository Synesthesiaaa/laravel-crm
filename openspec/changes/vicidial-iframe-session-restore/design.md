## Context

The Vicidial softphone already persists session state and can recover a pending login by reusing the saved iframe URL. The gap is reload behavior after the session has already become usable. In that case, the backend still reports the session as `ready`, `paused`, or `in_call`, but the browser can reload into a blank iframe because the current restore logic only handles `login_pending`.

The affected flow spans the floating softphone widget, the Vicidial session helper, and the existing iframe URL recovery endpoint. The design must keep the current campaign resolution and session storage model intact while making reloads restore the live embedded session instead of leaving the widget half-initialized.

## Goals / Non-Goals

**Goals:**
- Restore the Vicidial iframe after reload when the local session is already active.
- Continue to support mid-login recovery without adding a new server endpoint.
- Rebuild the iframe URL from the active session when the cached URL is missing.
- Keep widget phase and logged-in indicators aligned with the restored session state.
- Preserve the existing telephony campaign and session persistence model.

**Non-Goals:**
- Do not add a new persisted iframe URL field.
- Do not change Vicidial login credentials or campaign selection behavior.
- Do not change the logout endpoint contract.
- Do not introduce a new frontend state store for Vicidial sessions.

## Decisions

### 1. Extend the existing restore helper instead of adding a new endpoint

The current `maybeReconnectPending` flow already knows how to rebuild the iframe URL and re-enter verification for `login_pending`. The simplest and least risky approach is to broaden that helper so it also restores active sessions after reload.

Why this approach:
- It keeps the reload logic in one place.
- It reuses the existing `iframe-url` endpoint and session service.
- It avoids adding another frontend branch or backend API surface.

Alternatives considered:
- Add a dedicated restore endpoint. Rejected because the existing endpoint already rebuilds the URL.
- Duplicate restore logic in the widget init path. Rejected because it would split the behavior between two places.

### 2. Treat active session states as restorable, not just pending sessions

Reload recovery should apply to `ready`, `paused`, and `in_call` as well as `login_pending`. Those states still represent an active Vicidial session that should survive a browser refresh.

Why this approach:
- It matches the user expectation that the widget should come back in place after refresh.
- It keeps the hidden iframe consistent with the backend session state.
- It prevents the widget from looking logged in while the iframe is blank.

Alternatives considered:
- Only restore pending logins. Rejected because it leaves active sessions half-restored after reload.
- Force a full relogin after every reload. Rejected because it would be slower and would risk interrupting live sessions.

### 3. Keep `last_iframe_url` as an optimization, not a hard dependency

The saved iframe URL is still useful, but the recovery path should rebuild the URL from the active session when that value is missing. That makes reload recovery resilient to partial state loss.

Why this approach:
- It preserves the fast path when the URL is already stored.
- It avoids failing reload recovery just because the cached URL was cleared.
- It leverages the existing backend session row, which already has the phone login and campaign context.

Alternatives considered:
- Store a second URL field. Rejected because it duplicates state without adding reliability.
- Skip recovery when `last_iframe_url` is missing. Rejected because that recreates the blank-iframe bug.

### 4. Clear the widget's idle state whenever the backend reports logout

The periodic status sync should always push the widget phase back to idle when the session is logged out. This keeps the floating control in sync even when logout happens from another tab or after a delayed sync.

Why this approach:
- It removes a stale "online" state after logout.
- It keeps the launcher badge consistent with the backend.
- It is a narrow, low-risk correction to an obviously stale phase branch.

Alternatives considered:
- Leave the phase as-is and depend on a later refresh. Rejected because the widget can stay visually wrong in the meantime.

## Risks / Trade-offs

- [Reloading an active iframe might briefly show a loading state] -> Mitigation: restore the iframe with the existing helper and immediately resync the session state.
- [Vicidial URL rebuild could fail if the active session row is missing phone login data] -> Mitigation: keep the saved URL as the fast path and surface the existing actionable recovery error when rebuild is impossible.
- [The restore logic now covers more session states] -> Mitigation: keep the branching explicit and only allow known active statuses.

## Migration Plan

1. Deploy the frontend restore logic that treats active sessions as recoverable after reload.
2. Keep the existing iframe URL endpoint as the backend source for rebuilds when the cached URL is missing.
3. Validate that a reloaded `ready` session restores the iframe, then pause/logout still work against the restored session.
4. If needed, revert the frontend restore helper and phase sync adjustment. No schema rollback is required.

## Open Questions

None.
