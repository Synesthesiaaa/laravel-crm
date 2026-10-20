## Why

The CRM has no consent-based SMS campaign workflow. Super Admins need auditable preparation, gateway configuration, asynchronous submission, suppression and truthful outcomes without changing VICIdial or SIP.

## What Changes

- Add Super Admin SMS overview, campaigns, review, history, templates, gateway settings and reports.
- Persist encrypted gateway credentials, explicit channels, consent evidence, opt-outs and idempotent message records.
- Add database-backed jobs, conservative channel reservation, quiet hours and recovery that never blindly retries unknown submissions.
- Implement a fixture-tested legacy HTTP profile and authenticated inbound UDP profile, gated by explicit firmware verification. Live sending defaults off.
- Document workers, receiver, recovery and hardware acceptance. Unverified delivery reports never produce Delivered.

## Capabilities

### New Capabilities
- `sms-management`: authorized consent-based campaigns, transport boundaries and operational controls.

### Modified Capabilities
None.

## Impact

New SMS tables, models, requests, services, jobs, commands and Blade views. Reuses Laravel queues, Spatie auditing, existing role middleware and design tokens. No new dependencies or changes to telephony. Hardware acceptance requires model, firmware, channel mapping and an approved test recipient.
