## Why

Historical reports resolve every enabled VICIdial campaign mapped to a CRM campaign, but serialize that scope with the wrong separator for VICIdial's Non-Agent API. As a result, `call_status_stats` does not query all mapped campaigns and the CRM total calls, answered calls, volume, statuses, and campaign comparison can be incomplete or empty.

## What Changes

- Serialize mapped VICIdial campaign lists using the delimiter required by the Non-Agent API.
- Keep single-campaign parameters unambiguous when the resolved scope contains multiple campaigns.
- Add regression coverage proving a multi-campaign historical request reaches VICIdial as one valid mapped scope and aggregates all returned rows.
- Preserve CRM scope isolation so unmapped server campaigns are never included.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `telephony-operations-and-reporting`: Historical report requests must pass all mapped VICIdial campaigns in the API's supported multi-campaign format and aggregate their results.

## Impact

- `ReportingService` historical and shared campaign-scope request construction.
- Historical telephony feature tests and the telephony reporting specification.
- No schema, dependency, or UI changes.
