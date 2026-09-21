## Context

The shared CRM-to-VICIdial scope resolver correctly returns one server and all enabled mapped campaign codes. `ReportingService` then serializes those codes for the VICIdial Non-Agent API. VICIdial's multi-campaign report parameters use a hyphen-delimited list, while the current implementation uses a pipe-delimited list. The pipe is also used inside the application's test fixtures and is not a valid upstream multi-campaign request format.

## Goals / Non-Goals

**Goals:**

- Send all mapped historical campaign codes to VICIdial in its supported multi-campaign format.
- Ensure the optional `campaign_id` parameter is omitted when the effective scope contains more than one campaign.
- Preserve case-insensitive scope filtering and CRM/server isolation.
- Prove the behavior through a request-level regression test and the existing aggregate response assertions.

**Non-Goals:**

- Changing database mappings, server selection, report parsers, or the browser layout.
- Requesting `---ALL---`, which would broaden the upstream query beyond the mapped CRM scope.
- Changing the delimiter for in-group or status filters, which are separate parameters and are already handled by their respective callers.

## Decisions

1. **Use `-` when serializing mapped campaigns.** VICIdial documents campaign lists for `call_status_stats` and related report functions as dash-delimited. The service will continue to resolve and validate the campaign set locally before serialization, so the upstream request remains limited to the CRM campaign's mappings.

2. **Treat the serialized list as an upstream-only representation.** The service will split the canonical serialized list on `-` when deciding whether `agent_stats_export` can receive a single `campaign_id`. A multi-campaign list results in no `campaign_id`, allowing the export to return rows that the historical parser then filters to the mapped scope.

3. **Update existing request assertions instead of retaining the invalid format.** Tests will model the real VICIdial contract and return aggregate rows for the hyphen-delimited request, ensuring a future regression cannot pass merely because a fake accepts the wrong separator.

## Risks / Trade-offs

- [Campaign IDs containing hyphens] → VICIdial's list syntax reserves hyphens as the documented campaign separator; mapped values are therefore treated as VICIdial campaign IDs compatible with that contract.
- [Upstream deployment variation] → The request remains explicit and scope-limited rather than falling back to `---ALL---`; failed or empty upstream responses continue to surface through the existing availability states.

## Migration Plan

No data migration is required. Deploy the service and test changes together; rollback consists of reverting the application change if the target VICIdial installation uses a non-standard request contract.

## Open Questions

None for the current Non-Agent API integration.
