# GoIP SMS Management

## Compatibility and safety

This module prepares consent-based campaigns and records transport outcomes. It is **not hardware-accepted or production-verified** until the checklist below is completed.

Operator-supplied targets:

| Device | Firmware | Live verification |
| --- | --- | --- |
| GoIP 4 | GST1610-1.01-70-17-8 | Not performed |
| GoIP 16 | GST1610-1.01-70-17-8 | Not performed |
| GoIP 32 | GST1610-1.01-70-16 | Not performed |

Firmware identification does not prove transport compatibility. No actual credentials, recipient data or hardware captures were supplied. Do not assume that CH1–CH4 represents every physical port of a GoIP32.

The implemented outbound reference profile is legacy HTTP `GET /default/en_US/send.html` with `u,p,l,n,m` fields. Its narrowly parsed response contains a matching line, destination and gateway ID. A reference response is `Sending,L1 Send SMS to:+639171234567; ID:0000021f` (synthetic fixture). A successful response means **Submitted to Gateway**, not handset delivery. No status polling endpoint or delivery-report mapping is assumed. Unicode and multipart sending are rejected; the composer estimates their units to explain exclusions.

Inbound reference support is the semicolon-delimited `req` and `RECEIVE` SMS Server profile, with exact `id`/`pass` authentication. `msg` consumes the remainder, including colons and semicolons. The receiver authenticates the exact gateway IPv4 and client ID, persists inbound records and deduplication keys transactionally, then acknowledges. Unsupported `DELIVER` events are deliberately not interpreted or acknowledged. Report support requires firmware-specific evidence and an implemented correlation test before activation.

Reference implementations inspected without installing their PHP 8.5 package:
- [Legacy HTTP implementation](https://github.com/styryl/goip/blob/master/src/Sms/HttpSms.php)
- [Packet parser](https://github.com/styryl/goip/blob/master/src/Request.php)
- [Keepalive acknowledgment](https://github.com/styryl/goip/blob/master/src/Messages/RequestMessage.php)
- [Inbound acknowledgment](https://github.com/styryl/goip/blob/master/src/Messages/ReceiveMessage.php)

These references establish a profile for fixture tests, not compatibility with the supplied hardware.

## Server configuration

Back up the database and APP_KEY. Credentials, templates, campaigns and message bodies use Laravel encryption; losing the key loses access to encrypted data. No dependencies or PHP version upgrades are required (existing PHP 8.2+ with mbstring, OpenSSL, PDO and UDP-capable PHP streams).

Add to the deployment environment:

```dotenv
SMS_LIVE_ENABLED=false
SMS_EMERGENCY_STOP=true
SMS_QUEUE_CONNECTION=database
SMS_ALLOWED_IPS=192.168.10.20
SMS_RECEIVER_HOST=192.168.10.10
SMS_RECEIVER_PORT=44444
SMS_TEST_NUMBER=
SMS_RETENTION_DAYS=0
```

Use actual approved private gateway addresses. The allowlist is exact IPv4; DNS names and arbitrary URLs are rejected. `SMS_RETENTION_DAYS` enables the explicitly approved content-cleanup policy; default zero means no automatic removal. The daily `sms:prune` command clears expired terminal message bodies and completed campaign content while preserving states, consent evidence and deduplication keys. Configure deployment backups and access restrictions for the database because normalized numbers and consent evidence must remain queryable.

Run:

```sh
php artisan migrate --force
php artisan config:cache
php artisan queue:work database --queue=sms --sleep=1 --tries=1 --timeout=20 --max-time=3600
php artisan schedule:run
php artisan sms:receive
```

Run `schedule:run` every minute from cron/Task Scheduler. `sms:maintain` queues at most 100 pending records per run, repairs lost queued jobs after ten minutes, and marks processing records older than five minutes unknown. A database queue requires its matching database worker; Horizon Redis workers cannot consume it. If choosing Redis, change both SMS_QUEUE_CONNECTION and the supervised worker connection and verify the extension/cache configuration. Queue retry_after must exceed the 20-second worker timeout.

Use the provided Supervisor example on Linux. Windows can supervise the same two Artisan processes using the organization's service manager. Stop/restart only SMS processes. Never edit Asterisk, SIP, VICIDial or dialplan configuration for this module.

The receiver binds only loopback/RFC1918 IPv4. Allow UDP ingress to its configured port only from configured gateway IPs on a private/VPN network. Outbound sending is HTTP to each configured gateway port; UDP 44444 is not an assumed outbound command port. Legacy HTTP query credentials and UDP passwords are cleartext: isolate the segment, bypass HTTP proxies and disable access/packet logging containing those values. Browser-facing CRM must use HTTPS and existing session/CSRF protection.

In SMS Management → Gateways & channels, save actual firmware, private IP, HTTP credentials and port. Add each verified SMS line separately, with its own SMS Server client ID/password where applicable. Blank password inputs preserve existing values and never display secrets. Saving a gateway without the explicit transport-verification checkbox clears verification. Document the reference/date/interfaces tested in its verification note. Enable inbound only after checking the authenticated profile on this firmware. Network connection testing opens TCP only; it does not establish authentication or SMS capability.

Only after controlled acceptance should operations set `SMS_LIVE_ENABLED=true`, `SMS_EMERGENCY_STOP=false`, refresh config and restart SMS workers. Configure `SMS_TEST_NUMBER` for a designated approved recipient; test sending still requires explicit UI approval and consent evidence. Automated tests use HTTP fakes and never live hardware.

## Super Admin workflow

1. Record actual consent evidence for the exact mobile number and purpose. CRM lead membership is not evidence. Record its source/reference and timestamp; recording consent never removes an opt-out.
2. Create a draft: gateway, actual channels, purpose, body, optional schedule and quiet hours. Schedules are entered in Asia/Manila. Supported variables are `{{recipient_name}}` (LeadHopper.client_name/CSV name) and `{{campaign_name}}`. Unknown variables fail validation. Marketing requires STOP instructions and a verified monitored inbound profile.
3. Add individual recipients, evaluate a CRM lead group or import the downloadable CSV. There is no separate contacts table in this CRM; no customer database is duplicated. CSV columns are exactly `phone,recipient_name,consent_source,consented_at`; UTF-8 BOM is accepted. Supply ISO timestamps with timezone. Files are limited to 2 MB/10,000 recipient rows and one correctly quoted record per physical line. Multiline CSV fields are rejected before writes. A first streaming pass validates the complete file, then short per-row transactions import it; an unexpected interruption can leave partial draft decisions that must be reviewed or reset before retrying. Blank evidence requires existing consent. Invalid evidence rejects the upload; invalid destinations, duplicates, missing consent, opt-outs and unsupported rendered messages are counted as exclusions.
4. Review eligible/excluded totals and rendered messages. The confirmation digest binds the draft revision and current eligibility. Exclusions are retained; use a new draft to reevaluate a previously excluded recipient after correcting evidence. Large campaigns (1,000+) require an additional confirmation.
5. Confirm the audience. The scheduler and dedicated worker submit asynchronously; web requests do not send campaigns. Consent/suppression are checked before the submission boundary. Pause/resume/cancel act on pending messages; in-flight submissions can still complete. Emergency stop disables every SMS gateway and pauses active campaigns. Recovery requires deliberately enabling verified gateways/channels and resuming campaigns.
6. Review inbox/outbox, exact-number conversations, unread state, campaign ledger and status CSV exports. Replies use the same campaign composer/consent workflow; there is no separate bypass transport. CSV exports exclude message bodies and escape spreadsheet formula prefixes. Exports and sensitive mutations are audited without bodies, credentials or recipient numbers.

STOP/STOPALL/UNSUBSCRIBE/CANCEL/END/QUIT received through the authenticated receiver suppress future marketing, including pending jobs. Arbitrary replies never resubscribe a number. Administrative revocation revokes all stored consent for the number and cancels pending messages. There is intentionally no casual opt-out removal action.

## Message and campaign states

Preparation states: eligible, invalid, duplicate, no_consent, suppressed, unsupported. Only eligible rows become queued after confirmation.

Submission transitions: queued → processing → submitted/failed/unknown; queued → canceled. Processing is persisted before transport I/O. Once processing starts, pause/cancel cannot promise recall. Failed means a definitive authentication rejection in this profile. Timeout, malformed response, disconnection after attempting I/O and worker interruption mean unknown, never automatic retry. Submitted means gateway acceptance. Delivered is reserved and never fabricated from HTTP `DONE` or stale channel data. Inbound records are received.

Campaign transitions: draft → scheduled/queued → running → completed; scheduled/queued/running → paused → scheduled/queued; draft/scheduled/queued/running/paused → canceled. Creator role loss fails an active campaign and cancels pending recipients. Completion means no pending work; inspect message outcomes to determine success. Canceled campaigns cannot resume.

Limits count reserved attempts conservatively (including uncertain submissions). Each channel enforces a minimum 30-second interval and configured daily count in Asia/Manila. Gateway-wide reservation serializes physical submissions until concurrency is proven. Do not rotate SIMs to evade carrier restrictions. Unsubmitted messages expire three days after the scheduled window, or confirmation time for immediate sends. Throughput has not been benchmarked or verified on hardware.

## Diagnosis and recovery

- Draft cannot confirm: inspect live/stop settings, operator verification, enabled selected channels, eligible audience and durable queue connection.
- Queued messages do not move: verify scheduler heartbeat, dedicated worker connection/queue, quiet hours, date, gateway reservation, channel enabled state, next-send time and daily limit. No recent job heartbeat alone does not prove a quiet worker is offline.
- Unknown submission: inspect gateway/carrier evidence through the trusted management network; do not resend blindly. The campaign review provides an audited reconciliation to submitted or definitive failed. It never sends another SMS; evidence is hashed in audit records to avoid retaining sensitive diagnostic text. Keep the original evidence in the organization's approved incident system. Review before re-enabling the disabled channel.
- Worker crash: wait for sms:maintain to mark abandoned processing unknown; queued records can be safely re-enqueued, processing records cannot. Never manually change processing/unknown back to queued without independent evidence proving no SMS was accepted.
- UDP retransmissions: matching event IDs/content acknowledge the durable record without duplicate rows. Reused event ID with different payload is rejected for review. Clear sequence rollover handling requires firmware-specific testing and an approved archival procedure; do not delete deduplication keys during ongoing gateway retransmissions.
- No delivery reports: this release has no verified firmware-specific delivery correlation. Treat submitted as submitted and confirm actual receipt during acceptance.

Retention defaults to preserving records and deduplication keys. Approve a legal/operational retention window before automating removal; archive sensitive bodies and consent evidence under the organization's policy without erasing active idempotency. Do not assume a retention deletion policy from a generic default. For rollback, disable live sending, pause campaigns, stop SMS worker/receiver, back up/export records, then roll back the additive SMS migration only if permanent removal is intended. Existing CRM tables are not destructively modified.

## Hardware acceptance (separate, not performed)

For each target model/build, retain sanitized evidence of: model/channel addressing, HTTP command/response, receiver authentication/ACK and supported encoding. Then:

- [ ] Network connection and correct credential rejection/acceptance.
- [ ] Exactly the configured physical channel is used without disturbing voice.
- [ ] One explicitly approved SMS to the designated consented recipient.
- [ ] Correlated gateway acceptance ID and actual recipient receipt.
- [ ] Delivery-report correlation only if a trustworthy firmware mapping is established and implemented.
- [ ] Inbound SMS persisted before ACK, duplicate retransmission and sender matching.
- [ ] STOP suppresses a queued marketing message.
- [ ] Pause/resume/cancel/emergency stop and interruption recovery.
- [ ] Actual safe interval, daily limits, encoding and concurrency constraints.

Passing fixture tests does not satisfy this checklist.
