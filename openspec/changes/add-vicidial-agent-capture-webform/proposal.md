## Why

Agents currently use the full CRM Agent Screen as a separate workspace while VICIdial already owns the call presentation. This change makes the configured Agent Screen capture fields available as an authenticated VICIdial webform, so each call can open one campaign-specific form with lead details already populated.

## What Changes

- Allow an administrator to associate one active CRM form with each campaign's Agent Screen webform configuration.
- Generate a copy-ready VICIdial Web Form URL with lead and mapped VICIdial call variables.
- Add an authenticated, frame-safe webform route that renders Agent Screen Fields only.
- Prefill mapped `get`/`both` fields from VICIdial query parameters and keep `lead_id`/`phone_number` as capture metadata.
- Save submissions through the existing Agent Capture Record API and preserve configured `post`/`both` VICIdial writeback.
- Keep the full CRM Agent Screen available and leave the normal CRM Form submission flow unchanged.

## Capabilities

### New Capabilities

- `vicidial-agent-capture-webform`: Authenticated campaign webform configuration, VICIdial URL generation, mapped call-data prefill, and Agent Capture Record submission.

### Modified Capabilities

None.

## Impact

- Laravel campaign metadata, Agent Screen administration, a new authenticated webform controller/view, and the existing Agent Capture API boundary.
- Alpine/Vite frontend assets for frame-safe rendering and submission.
- VICIdial campaign Web Form settings require the generated URL to be pasted and tested by an administrator.
- No new package or external service dependency.
