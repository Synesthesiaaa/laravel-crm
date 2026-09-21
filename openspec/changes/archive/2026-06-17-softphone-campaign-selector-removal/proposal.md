## Why

The floating softphone currently lets agents pick a Vicidial campaign from a widget-side list, but that selection is no longer needed for the CRM workflow. Removing it simplifies the UI, eliminates redundant Vicidial campaign lookup/persistence code, and reduces the chance that the widget and session state drift out of sync.

## What Changes

- **BREAKING** Remove the campaign selector from the floating softphone widget.
- Remove the widget-side Vicidial campaign lookup, canonicalization, and persistence flow.
- Remove the `agent-campaigns` and `select-campaign` Vicidial session endpoints that exist only to support the widget selector.
- Keep the existing Vicidial campaign fallback chain for login, status, pause, logout, and iframe recovery.
- Leave the login-page campaign picker unchanged.

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `platform-stabilization`: Softphone campaign handling no longer exposes a widget-side selector and now relies on the existing session/bootstrap campaign source instead.

## Impact

- Affects the softphone widget Blade partial and Alpine state in the frontend.
- Affects Vicidial session controller routes and request validation.
- Removes an unused Vicidial campaign lookup service and its config flag.
- Requires test updates for the deleted widget-only endpoints and the preserved fallback campaign behavior.
