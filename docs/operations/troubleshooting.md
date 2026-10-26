# Troubleshooting

Start with the affected workflow, time/timezone, displayed role/campaign, and last known successful step. The causes below are **possibilities inferred from source**, unless explicitly marked source-confirmed. They are not diagnoses of your installed system. Use synthetic reproductions and read-only inspection before changing anything.

Never paste `.env`, real recipient lists, PDF contents, API/SIP credentials, signed URLs, private phone numbers, or unredacted logs into a public issue. Escalations should include release commit, runtime versions, sanitized HTTP status/error, and the steps attempted.

## Composer cannot install dependencies

**Problem:** platform constraint or missing-extension error.

**Possible causes:** PHP below 8.4 despite the project's `^8.2` declaration; missing extensions; CLI uses a different PHP from Apache. The Symfony 8 lockfile requirement is source-confirmed.

**How to check:** run `php -v`, `php --ini`, `php -m`, and `composer check-platform-reqs`; inspect [requirements](../getting-started/requirements.md).

**Resolution:** use a PHP runtime compatible with the committed lockfile and enable the required extensions. Do not ignore platform requirements or run an unreviewed dependency update.

**Verification:** a clean install and platform check in an isolated checkout succeed. **Escalation:** exact sanitized package/extension errors and CLI/web PHP versions.

## Horizon fails on Windows

**Problem:** missing PCNTL/POSIX or process-management failures.

**Possible causes:** native Windows lacks Horizon's required Unix extensions. Manifest platform overrides simulate their presence during dependency resolution; they do not install them.

**How to check:** inspect extension output and Horizon's locked requirements.

**Resolution:** use WSL/Linux for Horizon. For basic isolated Windows development, use database queues with the limitations in the [worker guide](queues-and-workers.md).

**Verification:** actual target runtime has required extensions and supervised Horizon starts in staging. **Escalation:** OS/PHP/extension list and sanitized startup error.

## Database connection or missing sessions table

**Problem:** login or page requests report SQL/connection errors.

**Possible causes:** wrong connection/path/permissions, unavailable database, migrations missing, stale cached configuration. Login directly queries `sessions`; switching the session driver does not remove that dependency.

**How to check:** compare intended `DB_*` names with [configuration](../getting-started/configuration.md) without publishing values; verify database service and file access. An authorized operator can inspect `php artisan migrate:status`.

**Resolution:** correct the isolated environment's configuration and database access. For an existing installation, review and back up before applying pending migrations.

**Verification:** migration status and a synthetic login work. **Escalation:** driver, SQLSTATE, failing table name, migration identifiers; no credentials or row data.

## Migration fails or form schema does not match

**Problem:** migration/submit complains about a missing column, type, or constraint.

**Possible causes:** a partial migration, dynamic form definition/storage mismatch, wrong database driver, existing legacy schema. Some migrations alter or remove registered form columns.

**How to check:** inspect the exact failing migration and registered form/table/field definitions; compare with [database architecture](../developer/database.md). Do not rerun destructive commands to guess a fix.

**Resolution:** restore a staging copy, reproduce safely, and prepare a reviewed repair/upgrade plan. Do not use `migrate:fresh` or drop tables on an established database.

**Verification:** migration and representative form writes succeed in staging without lost fields. **Escalation:** sanitized schema and migration status, source field definition, SQLSTATE.

## Storage, log, or cache permission denied

**Problem:** page, worker, or rotating telephony log cannot write.

**Possible causes:** different web/CLI/worker accounts, directory permissions, a newly rotated file, wrong persistent-storage mount.

**How to check:** identify all runtime accounts and inspect ownership/access for `storage/` and `bootstrap/cache/`. Confirm both directory and existing file permissions.

**Resolution:** have the operator assign the intended shared account/group and restricted writable paths; avoid world-writable permissions. Match service examples to actual accounts.

**Verification:** the same service account can write required runtime paths after rotation/restart. **Escalation:** sanitized path, operation, service identity, ownership/mode; no file contents.

## Login fails, expires, or keeps asking about another session

**Problem:** invalid credentials, retry delay, confirmation loop, or session-expired message.

**Possible causes:** email entered instead of username, failed-attempt/IP limits, expired five-minute confirmation, mismatched URL/cookie settings, database session persistence, stale configuration.

**How to check:** use a fresh login page and confirm intended scheme/host/path; ask the operator to inspect the account and database sessions without exposing them.

**Resolution:** use the correct username, wait the indicated throttle interval, retry a fresh confirmation, and correct HTTPS/cookie/proxy configuration. Ask Super Admin for account recovery; no public reset route is documented.

**Verification:** one synthetic sign-in completes and the intended campaign appears. **Escalation:** timestamp, sanitized redirect/status chain, cookie configuration names, role; never password/session cookie.

## Wrong campaign or forbidden/missing navigation

**Problem:** a different work area appears, a page returns 403, or Agent Screen is missing.

**Possible causes:** campaign fallback, inactive campaign, global `agent_screen_access=false`, role/request restriction. These are source-confirmed mechanisms; seeded permissions do not override Super Admin routes.

**How to check:** verify displayed campaign and role, active campaign configuration and feature flag, then [role matrix](../roles-and-permissions.md).

**Resolution:** sign into the intended configured campaign; have Super Admin correct authorized role/configuration. There is no general campaign-membership assignment feature to repair.

**Verification:** the correct synthetic account sees intended navigation and disallowed requests stay forbidden. **Escalation:** route, role, campaign code, feature status; no private records.

## Form submission fails or dates are read-only

**Problem:** validation errors, unexpected hidden fields, or date cannot be edited.

**Possible causes:** configured field rules/visibility, campaign/form mismatch, readonly Agent submission date, storage schema mismatch.

**How to check:** compare [forms and records](../user-guide/forms-and-records.md) with the exact field configuration and validation response.

**Resolution:** correct allowed values/required fields. Ask an authorized form administrator to repair configuration; do not alter browser attributes to bypass server rules. Treat schema errors separately above.

**Verification:** a synthetic valid record is saved once and visible in the expected scope. **Escalation:** field identifiers and sanitized validation messages, not customer values.

## Blank reports, zero totals, or conflicting sales metrics

**Problem:** dashboard/report seems empty or differs from another screen.

**Possible causes:** no records, different date/timezone/form/campaign filters, local history not synchronized, upstream unavailable, different source/formula, configured disposition classifications or sales rules.

**How to check:** read source availability/freshness and filters before comparing counts. Review [report formulas](../user-guide/reports.md), mapping and synchronization status. Do not interpret unavailable as zero.

**Resolution:** correct filters/mappings and recover the relevant source/worker. Keep local form sales distinct from telephony success dispositions and current attendance state.

**Verification:** a known synthetic dataset reconciles within one documented source/window. **Escalation:** source/window/timezone, sanitized filter set, freshness/status and expected synthetic count.

## Operations page is accessible without login

**Problem:** Operations Insights appears publicly accessible.

**Possible cause:** source-confirmed public routes and controller entry points, not a cached missing menu.

**How to check:** inspect [web routes](../../routes/web.php) and the [security guide](security.md); avoid probing a live page containing staff data.

**Resolution:** the responsible operator must review deployment network restrictions; record application changes as a separate security task. Do not claim role restrictions the source does not enforce.

**Verification:** test intended external access policy in an isolated deployment. **Escalation:** route and deployment exposure only; never dashboard content.

## Import says queued but records do not appear

**Problem:** CSV import remains pending or the hopper is still empty.

**Possible causes:** no `imports` consumer, wrong connection, row validation failures, misunderstanding import purpose. The current `ImportLeadsCsvJob` writes form submissions, not `LeadHopper` rows.

**How to check:** inspect import progress/error counts and the connection/lane the consumer uses.

**Resolution:** start the intended controlled consumer and fix the CSV according to form fields. Do not reupload blindly: accepted rows may already exist and repeats can duplicate them.

**Verification:** a synthetic import creates the expected form records and reports row failures accurately. **Escalation:** import ID, headers, synthetic failing row, sanitized failure counts.

## VICIdial connection or session verification fails

**Problem:** remote API error, unavailable lead tools, or session not ready.

**Possible causes:** wrong campaign-linked server, Agent/Non-Agent endpoint mismatch, credentials/remote permissions, TLS trust, network outage, local versus external campaign mapping, expired remote session.

**How to check:** verify effective server mapping and sanitized HTTP/error codes. CRM login alone does not authenticate the external agent session.

**Resolution:** correct approved server/remote permissions and certificate trust; establish the intended external session using [VICIdial guidance](../integrations/vicidial.md). Do not disable readiness or TLS checks.

**Verification:** controlled session verify/status and a permitted synthetic workflow succeed. **Escalation:** server identifier, API type, campaign mapping, redacted response/time; no query credentials.

## AMI listener will not start or webhook is rejected

**Problem:** unknown option, connection failure, 401/503 webhook response.

**Possible causes:** missing AMI secret/network permission; webhook missing or wrong shared secret; selecting a listener process that does not match the configured AMI event path.

**How to check:** `php artisan help ami:listen` and `php artisan help ami:events:listen` are safe command-shape checks. Inspect intended AMI/webhook configuration names. A telephony preflight contacts AMI; run only with authorized infrastructure.

**Resolution:** use the current release's actual command signature; configure approved network access/secret/header. Production POST webhooks fail closed when the configured secret is empty. Never make webhooks public by removing verification.

**Verification:** supervised startup and controlled synthetic events reach the correct local call in staging. **Escalation:** selected command description, redacted HTTP status, event type/identifier correlation; no secrets.

## SIP does not register

**Problem:** browser phone remains disconnected.

**Possible causes:** WSS certificate/URL, incorrect extension/password/domain, network restrictions, duplicate SIP.js and ViciPhone registration, unsupported channel expectations.

**How to check:** inspect the sanitized browser connection error and configured audio owner; see [browser softphone](../integrations/browser-softphone.md). Current CRM agent routing is SIP-only.

**Resolution:** configure trusted WSS and the correct approved account; choose a single audio owner. Do not accept untrusted certificates or publish credentials to diagnose.

**Verification:** controlled extension registers once and stays registered. **Escalation:** browser/OS, sanitized WSS/certificate error, audio owner and extension alias.

## Call connects but audio is missing or one-way

**Problem:** call state changes but participants cannot hear.

**Possible causes:** denied microphone permission, wrong device, ICE/NAT/firewall/TURN constraints, incompatible PBX media setup. These require device/network validation and are not proven from PHP source.

**How to check:** inspect microphone permission/device selection and sanitized WebRTC connection state in a controlled session.

**Resolution:** select the intended device, grant microphone access to the trusted HTTPS origin, and have PBX/network operators review ICE/media routing. Avoid using real customers for tests.

**Verification:** two approved test endpoints exchange audio in both directions. **Escalation:** browser/OS/device, network topology summary, sanitized ICE state; no recorded customer media.

## Call disconnects, control fails, or next dial is blocked

**Problem:** call terminates, transfer/hold is unavailable, or next dial is refused.

**Possible causes:** remote/session outage, stale local state, disabled feature, pending disposition, unsupported remote control. CRM and PBX state can diverge.

**How to check:** inspect current call/session/outcome and timestamps; compare external PBX state with an authorized operator. Some controls are feature-gated.

**Resolution:** save the required disposition, recover the correct external session, and investigate event correlation. Do not clear local state to force another dial or retry a live call without confirming remote state.

**Verification:** the approved synthetic lifecycle completes once with matching local/external outcome. **Escalation:** sanitized call/session aliases, event sequence, times, pending-outcome message.

## Call history is delayed or missing after refresh

**Problem:** completed calls do not appear or refresh stays queued.

**Possible causes:** scheduler disabled, `telephony` consumer absent, mapping excludes remote campaign, source error, recent-window coverage, stale locks/failed jobs.

**How to check:** inspect local sync status/timestamps, `VICI_CALL_HISTORY_SYNC_ENABLED`, scheduler configuration and queue consumer. A refresh queues work; it is not a synchronous backfill.

**Resolution:** repair mapping/source/consumer configuration. For older dates, an authorized operator can plan the source-defined `vicidial:sync-call-history --campaign=training --from=2026-10-01 --to=2026-10-02` backfill after reviewing load. This command dispatches work and the jobs contact remote systems; it is not a read-only probe.

**Verification:** the intended time window's synthetic calls are present with updated successful sync status. **Escalation:** sanitized scope, dates, job failure and sync status; no phone/customer records.

## Email campaign stays queued

**Problem:** imported/reviewed recipients do not progress.

**Possible causes:** wrong dedicated connection/queue, worker heartbeat missing, live/local gate blocked, stale shared cache/configuration, pacing delay, failed preflight.

**How to check:** inspect campaign status/blockers and the exact worker command/configuration. `composer run dev` and Horizon defaults do not consume `email-database/email-campaigns`.

**Resolution:** follow [email campaigns](../EMAIL_CAMPAIGNS.md) and the [dedicated worker procedure](queues-and-workers.md). Resolve validation and policy blockers first. Do not enable live delivery just to make a diagnostic status disappear.

**Verification:** approved synthetic test campaign passes gates and progresses under the intended worker; no such send is performed by this guide. **Escalation:** campaign/status alias, heartbeat state, connection/queue and sanitized blocker.

## SMTP error, accepted message not received, or uncertain send

**Problem:** sending fails, provider accepted but inbox is empty, or a send outcome is uncertain.

**Possible causes:** transport credentials/TLS/limits, frozen sender snapshot, provider filtering, interruption after acceptance. Acceptance is not confirmed recipient delivery.

**How to check:** inspect the campaign report and provider evidence under authorized access. Saved SMTP disabled state can fall back to environment settings; frozen campaigns retain transport values.

**Resolution:** resolve transport problems through [SMTP documentation](../integrations/smtp.md). Reconcile uncertain outcomes with provider evidence before retrying; retry controls target blocked recipients, not a promise of deduplicated provider delivery. Never bulk-retry unknown side effects.

**Verification:** report and provider evidence agree for controlled recipients. **Escalation:** sanitized error category, timestamp, state and provider reference; never SMTP password/message/recipient data.

## Recipient CSV or PDF validation/mapping fails

**Problem:** strict import rejects rows/files, preview blocked, or payroll cannot queue.

**Possible causes:** headers/encoding, duplicate or case-colliding recipients/files, formula-like fields, missing/extra documents, incorrect exact basename, file limits, missing `pdfinfo`/scanner, encrypted original PDFs, password policy.

**How to check:** use downloaded templates and synthetic [CSV examples](../examples/README.md); inspect preflight errors and exact filename mapping.

**Resolution:** correct source files and mappings; never guess the recipient by employee name, row order, or partial filename. Payroll password policy blocks uploaded originals because required protection is unavailable. Do not disable an organizational requirement to send.

**Verification:** strict import, every assignment review and dry-run succeed before any authorized delivery. **Escalation:** synthetic headers/basenames and redacted validation reason; never a real payroll PDF.

## Reverb or notifications do not refresh

**Problem:** page works but realtime state is stale or WebSocket errors appear.

**Possible causes:** Reverb absent, wrong public host/scheme/key, cached frontend values, proxy upgrade/TLS issues, channel authorization failure, missing consumer for queued broadcasts.

**How to check:** inspect sanitized browser console/network status and private subscription response; verify source polling/freshness behavior before assuming lost data.

**Resolution:** align web/worker/Reverb settings, rebuild browser assets for changed `VITE_*`, configure WSS proxy and correct channel authorization. Never open private channels globally.

**Verification:** a controlled event reaches only authorized synthetic users; polling fallback remains correct. **Escalation:** channel type, status, release, frontend build and sanitized connection error.

## Frontend build or asset loading fails

**Problem:** Vite engine error, missing manifest, blank/unformatted page.

**Possible causes:** Node below locked requirements, incomplete `npm ci`, missing production build, stale dev hot-file/server, incorrect URL/public-root or old cached assets.

**How to check:** inspect `node --version`, lockfile engines, sanitized build output, browser 404 and production `public/build/manifest.json` presence.

**Resolution:** use a compatible Node runtime, run `npm ci` then `npm run build` in a build environment, and deploy matching assets. Review stale Vite development state before serving production. Do not invent npm test/lint commands: package scripts currently supply only build/dev.

**Verification:** build succeeds and intended assets load without errors in isolated browser validation. **Escalation:** Node/npm version, sanitized first build error, failed asset URL and release.

## Retention removed records or deletion is delayed

**Problem:** form data disappears or enabled policy has not run.

**Possible causes:** due permanent retention job, selected fixed date window/form, OS scheduler absent, failed overlap/job configuration.

**How to check:** inspect configured policy, target form/window and scheduler evidence; do not run `data-retention:run` as a diagnostic probe.

**Resolution:** stop and investigate unintended policy execution through authorized operations; use a verified backup for recovery. For delayed intended work, repair scheduling only after confirming scope. Deleting a policy does not restore rows.

**Verification:** isolated policy rehearsal affects exactly the synthetic intended window. **Escalation:** policy alias, form/window/timezone, sanitized run summary and backup timestamp.

Related: [monitoring](monitoring.md), [security](security.md), [maintenance](maintenance.md), [backup/recovery](backup-and-recovery.md).
