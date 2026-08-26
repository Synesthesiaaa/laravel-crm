## Context

VICIdial exposes the Agent API and Non-Agent API separately. A CRM campaign is already mapped to a distinct `vicidial_servers` record, but the record contains only `api_url`. The application derives a Non-Agent URL from that Agent API URL, which is correct for standard installations but prevents reporting from working when VICIdial 10 publishes `non_agent_api.php` at a separate URL.

## Goals / Non-Goals

### Goals

- Allow each mapped VICIdial server to store a dedicated Non-Agent API endpoint.
- Ensure the CRM campaign mapping determines the endpoint used for Supervisor reporting.
- Keep all existing mappings operational without requiring immediate configuration changes.
- Reject malformed explicit endpoints before they are saved.

### Non-Goals

- Do not change Agent API requests, telephony actions, or the campaign-to-server routing rules.
- Do not attempt to discover or provision VICIdial endpoints automatically.
- Do not expose API credentials in the administration interface.

## Decisions

### Add a nullable endpoint to the server mapping

Add `non_agent_api_url` as a nullable URL column on `vicidial_servers`. It belongs on the mapping rather than application configuration because different CRM campaigns can use different VICIdial servers and endpoint topologies.

### Prefer explicit configuration, retain derivation as a fallback

The Non-Agent API service resolves the URL in this order:

1. The selected server's non-empty `non_agent_api_url`.
2. The existing derivation from that selected server's `api_url`.
3. The existing application-level fallback, if no server endpoint is available.

This makes the correction backward compatible and prevents an Agent API endpoint from accidentally being used as the Non-Agent reporting endpoint.

### Reuse resolution for all Non-Agent API use

The shared Non-Agent API service is the primary integration point for Supervisor reporting. Any direct Non-Agent callers that resolve URLs from a `VicidialServer` will use the same explicit-first policy so operational calls are consistent with reporting.

### Configure in the existing server form

The administration form receives an optional, clearly labelled `Non-Agent API URL` field beside the existing Agent API URL. Helper text explains that it is used for Supervisor reports and that leaving it blank uses the conventional derived path, avoiding a breaking form requirement for current deployments.

## Risks / Trade-offs

- A syntactically valid but unreachable endpoint cannot be rejected at save time without making configuration dependent on network availability. The Supervisor panel will continue to surface a degraded/unavailable reporting state for that case.
- Existing installations that use the conventional VICIdial path can leave the new field empty. Administrators with a distinct VICIdial 10 endpoint must save the complete URL including `non_agent_api.php`.

## Migration Plan

The schema change is additive and nullable. Existing records retain their current behavior through URL derivation. Rollback removes only the new column.

## Testing Strategy

- Unit-test explicit endpoint preference and the legacy Agent API URL derivation.
- Feature-test create/update persistence and URL validation in VICIdial server administration.
- Feature-test Supervisor reporting issues requests to the explicit endpoint for the CRM campaign's selected server.
