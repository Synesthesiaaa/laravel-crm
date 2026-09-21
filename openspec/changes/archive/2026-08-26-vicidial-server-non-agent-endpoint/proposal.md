## Why

Each CRM campaign can be mapped to its own VICIdial server, but the mapping stores only the Agent API URL. Supervisor reports use VICIdial's Non-Agent API and currently derive its URL from the Agent API URL. That does not work when a VICIdial 10 installation exposes the Non-Agent API through a different host or path.

## What Changes

- Add an optional, validated Non-Agent API URL to every VICIdial server mapping.
- Make VICIdial Non-Agent API integrations prefer the explicitly configured endpoint for the selected CRM campaign's server.
- Preserve the existing derived endpoint behavior for existing mappings that do not set a Non-Agent API URL.
- Make the VICIdial server administration form clear about the endpoint's Supervisor-reporting purpose and the legacy fallback.

## Capabilities

### Modified Capabilities

- `campaign-scoped-vicidial-supervision`: Campaign-scoped Supervisor reporting obtains VICIdial Non-Agent API data from the explicitly configured per-server endpoint when present.

## Impact

- Additive migration and `VicidialServer` model attribute.
- VICIdial server store/update validation and administration UI.
- Non-Agent API service endpoint selection, including Supervisor metrics and other Non-Agent API integrations.
- Feature and unit coverage for explicit endpoint selection, validation, and the legacy fallback.
