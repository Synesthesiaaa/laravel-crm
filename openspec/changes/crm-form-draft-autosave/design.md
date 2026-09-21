## Context

The CRM form pages are rendered from a shared Blade partial that is reused by the full page and the widget iframe. Today those forms submit with a normal POST/redirect flow, which causes a full browser navigation. In this application that is risky because the telephony shell, Vicidial iframe, and softphone session state can be torn down by a reload before the agent finishes the workflow.

The same form surface also needs draft recovery. If the browser crashes, the tab reloads, or the agent moves away and comes back through soft navigation, in-progress form state should reappear without relying on the server to have already saved it.

## Goals / Non-Goals

**Goals:**
- Prevent CRM form submissions from forcing a page reload when JavaScript is available.
- Persist in-progress form state in the browser and restore it after reloads, crashes, and soft-navigation swaps.
- Apply the behavior to the shared CRM form partial so the full page and widget iframe behave the same way.
- Clear the saved draft only after the server confirms the submission succeeded.
- Preserve the existing non-JavaScript POST/redirect behavior as a fallback.

**Non-Goals:**
- Do not add a server-side draft storage table or queue-backed autosave pipeline.
- Do not change Vicidial login or telephony session rules.
- Do not change the underlying form field definitions or submission table schema.
- Do not introduce a new frontend dependency.

## Decisions

### 1. Keep draft persistence in the shared Alpine form helper

The form partial already initializes a shared `formVisibility()` Alpine component on every CRM form page. Extending that helper keeps the autosave logic in one place and automatically covers every form that uses the partial, including the widget iframe.

Why this approach:
- It applies the fix once instead of per form page.
- It keeps draft capture close to the existing visibility logic that already owns field state.
- It avoids adding another global store or splitting draft behavior from the form rendering logic.

Alternatives considered:
- Create a separate autosave store. Rejected because the form state already lives in the existing helper.
- Add per-form inline JavaScript. Rejected because it would fragment behavior and be harder to keep consistent.

### 2. Use browser localStorage for drafts

Drafts need to survive reloads and browser crashes, so session-only storage is not enough. localStorage is the simplest client-side store that persists across those events without requiring backend state.

Why this approach:
- It survives refreshes and tab crashes.
- It is available in both the full page and iframe contexts.
- It keeps the server free of intermediate draft records.

Alternatives considered:
- sessionStorage. Rejected because it is too ephemeral for crash recovery.
- server-side draft persistence. Rejected because it adds schema and cleanup complexity for a behavior that is only needed until a record is saved.

### 3. Scope draft keys by user and form context

Draft keys should be stable across the full form page and widget iframe, but they must not collide across campaigns, form types, users, or lead contexts. A composite key based on user identity plus form context provides that scope while still letting the same draft reopen in either presentation mode.

Why this approach:
- It keeps multiple agents from clobbering each other's drafts on shared devices.
- It lets the same agent continue the same form in the widget or full page.
- It keeps drafts isolated per campaign/form/lead context.

Alternatives considered:
- Use only the form type as the key. Rejected because it would collide across agents and contexts.
- Use the page URL alone. Rejected because it would not share drafts between the widget iframe and full page.

### 4. Submit via AJAX for JS-enabled sessions, but keep redirect fallback

The no-reload requirement is best met by intercepting the form submit and sending the payload with XHR/fetch. The server should return JSON for these requests so the client can clear the draft and reset the form without navigating away. The traditional redirect path should remain for any browser session that does not use JavaScript.

Why this approach:
- It removes the reload that can disrupt Vicidial and softphone state.
- It allows the client to keep the user on the same form after save.
- It preserves compatibility with existing non-JS behavior.

Alternatives considered:
- Force AJAX-only submission. Rejected because it would remove the graceful fallback.
- Keep redirect submission and try to suppress reload behavior with browser hacks. Rejected because the browser still navigates and the telephony shell can still be disrupted.

### 5. Reset the form from the initial rendered state after a verified save

After the server confirms the save, the client should clear the stored draft and restore the form to the initial state that was rendered for that page load. That keeps the next entry fast and predictable while preserving any fixed context like campaign or route defaults.

Why this approach:
- It gives the agent a clean form for the next record.
- It avoids keeping stale draft data after a confirmed save.
- It matches the requirement that the reset happens only after data is stored and verified.

Alternatives considered:
- Leave the saved values in place after success. Rejected because it would leave stale data on screen and increase the chance of duplicate entries.
- Hard-reset everything, including contextual defaults. Rejected because it would discard useful page defaults like the current date or campaign context.

## Risks / Trade-offs

- [Risk] localStorage drafts can become stale if the user keeps an old browser profile around. -> Mitigation: version the storage key and clear it on successful save.
- [Risk] Conditional fields may lose their values when they are hidden. -> Mitigation: keep the existing visibility behavior so hidden fields do not get submitted accidentally.
- [Risk] Multiple open tabs for the same form context can overwrite each other. -> Mitigation: keep the key scoped to user and form context so the overwrite surface is limited to the same record entry workflow.
- [Risk] AJAX errors need explicit UI handling. -> Mitigation: return JSON for XHR requests and render validation feedback in the form component.

## Migration Plan

1. Ship the frontend autosave and AJAX submit changes together with the JSON-aware controller response.
2. Verify that the shared form partial still renders the normal POST fallback for browsers without JavaScript.
3. Confirm that successful submissions clear the draft and that validation errors leave the draft intact.
4. Roll back by reverting the shared form helper and controller response changes if the no-reload flow introduces regressions.

## Open Questions

None.
