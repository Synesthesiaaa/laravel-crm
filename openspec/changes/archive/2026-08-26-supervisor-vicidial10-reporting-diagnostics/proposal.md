## Why

The Supervisor dashboard falls back to empty CRM metrics when its mapped VICIdial Non-Agent API cannot be reached or is not authorized, without explaining why. This obscures server-mapping, network, and API-permission problems and makes a correctly implemented VICIdial 10 report integration appear broken.

## What Changes

- Report a non-sensitive VICIdial reporting health state for the selected CRM campaign.
- Preserve the VICIdial 10 Non-Agent API report requests and per-metric CRM fallback behavior while surfacing actionable unavailable or degraded-report guidance in the Supervisor UI.
- Prevent connection-error logs from retaining Non-Agent API usernames or passwords embedded in failed request URLs.

## Capabilities

### New Capabilities

### Modified Capabilities

- `campaign-scoped-vicidial-supervision`: The Supervisor dashboard will communicate mapped-server reporting health safely when its live VICIdial reports cannot supply metrics.

## Impact

- `SupervisorAgentsController` routing metadata and report-health calculation.
- `VicidialNonAgentApiService` connection-error logging.
- Supervisor dashboard status presentation and API/feature tests.
