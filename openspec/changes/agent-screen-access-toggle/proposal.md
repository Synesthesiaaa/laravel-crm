## Why

The Agent Screen and Agent Capture surfaces are currently available to authenticated campaign users even when the business does not want agents using them. A Super Admin needs a single, auditable switch to keep these surfaces hidden and inaccessible by default while allowing them to be enabled when required.

## What Changes

- Add a persisted Agent Screen access feature flag, disabled by default.
- Add a Super Admin configuration control for enabling or disabling Agent Screen access.
- Hide Agent Screen navigation and search links for non-Super Admin users while disabled.
- Protect the Agent Screen page, Agent Capture webform, and Agent Capture submission endpoint from direct access while disabled.
- Preserve existing Agent Screen configuration access for Super Admins.
- Keep regular CRM forms and existing telephony feature flags unchanged.

## Capabilities

### New Capabilities

- `agent-screen-access`: Controls global Agent Screen visibility and endpoint access through a Super Admin-managed feature flag.

### Modified Capabilities

- None.

## Impact

- `TelephonyFeatureService` and the existing `system_settings` storage.
- Super Admin configuration UI and its existing update/audit flow.
- Sidebar, global search, and Agent Screen/Agent Capture routes.
- PHPUnit feature and service tests.
- No new dependencies or schema tables are required.
