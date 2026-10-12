# Validation evidence - 2026-10-13 Asia/Manila

- Original email suites: baseline 18 tests / 129 assertions passed before changes.
- Final relevant suites: `php artisan test tests/Feature/Admin/EmailCampaignsTest.php tests/Feature/Admin/EmailConfigurationTest.php tests/Feature/Admin/EmailDocumentDeliveryTest.php`: 39 tests / 319 assertions passed. Synthetic documents, fake disk/queue, array transport. One-address MIME attachment SHA checks, no CC/BCC, strict mappings, unsafe ZIP rejection, private roles, hash/identity changes, frozen message edits, shared/generated modes, dry-run/confirmation, cancellation, uncertain transport/interruption, explicit provider-evidence reconciliation, recovery and retention are covered.
- Changed PHP files: targeted `php vendor/bin/pint --test ...` passed for 24 files; changed files formatted through Pint. `git diff --check` passed.
- `npm run build`: passed; existing ApexCharts chunk exceeds 500 kB warning. No new frontend dependency.
- Isolated SQLite migration/rollback/reapply preserved a historical campaign row. After confirming `php artisan env` returned local, the additive migration applied successfully to local MySQL. No production migration or destructive rollback executed.
- `php artisan route:list --name=email-campaigns`: verified route group; document/review/export/start remain under Super Admin middleware with policy checks.
- Playwright against isolated synthetic fixture server: uploaded CSV/two PDFs, created draft, inspected both mappings, ran internal dry-run, explicitly confirmed queueing, viewed completed status and authenticated no-store preview. Fixture-only inspector accepted known synthetic PDF hashes; this is not positive real Poppler inspection or a production authentication claim.
- Playwright also exercised explicit synthetic provider-evidence reconciliation; accepted resolution returned the campaign to completed and removed retry controls, without dispatching mail. Mobile campaign upload form had no page overflow; review table tabindex was verified as 0.
- Responsive review at 375/768/1024/1440 widths: document width did not exceed viewport after layout settled. Mobile table scrolls; its region is keyboard-focusable. Desktop/mobile screenshots retained under ignored storage/framework/testing/email-rehearsal/screenshots.
- Real LOCAL NON-SENDING database queue worker: one two-recipient campaign and one single-recipient campaign processed; queue ended with zero email jobs, campaigns completed with 2 and 1 accepted respectively, zero failures. MIME evidence contains expected recipient filename/SHA pairs and zero CC/BCC. Heartbeat inspection returned email-database / email-campaigns / pending 0 / problem null immediately afterward. No shared live queue was consumed.
- Browser shell still reported ws/wss 127.0.0.1:6001 Reverb connection refusal and /api/sip/credentials 422. Email endpoints succeeded; these unrelated environment errors remain, so no clean whole-application browser claim.

## Synthetic SMTP self-test regression follow-up

The local SMTP-test failure was reproduced by three failing regression tests before editing. After correcting the guard and introducing a named syntheticTest error bag, all three relevant suites passed: 42 tests / 347 assertions. Pint passed for the changed PHP files; git diff --check passed. An isolated Playwright fixture verified a separate configuration alert with no blocked-mapping heading and an enabled internal dry-run, then a successful array-transport synthetic test to reviewer@example.com with zero attachments/CC/BCC and unchanged draft status. No real SMTP message was sent during verification. The browser retained existing unrelated Reverb/SIP/VICIdial configuration errors. This fix does not clear the rollout checks below or enable bulk sends.

## Local bulk sending follow-up

- User explicitly authorized local bulk sending. Only EMAIL_CAMPAIGN_LIVE_ENABLED=true and EMAIL_CAMPAIGN_ALLOW_LOCAL=true were applied to the ignored local .env, followed by php artisan config:clear. A metadata-only runtime check confirmed environment local, both flags true, shared policy allowing live transport, and email-database/email-campaigns connection/queue. No SMTP credentials printed; no live worker started or actual recipients contacted.
- The new local regression suite failed four intended readiness/transport/retry tests before implementation and passed all seven after the shared policy was added. The four relevant email suites passed 49 tests / 377 assertions, including existing recipient-document safety, local confirmation, missing SMTP/heartbeat, flag revocation and testing real-transport denial. Worker transport testing substituted an array transport behind a synthetic SMTP snapshot.
- Playwright used an isolated APP_ENV=local fixture database with synthetic SMTP readiness and non-sending array transport. A real empty fixture queue worker supplied the shared-cache heartbeat. Internal dry-run and explicit confirmation queued the synthetic campaign without a blocked-environment error. This verifies local HTTP guards, not real SMTP delivery or actual authenticated production sessions.
- The subsequent fixture array worker processed that queued recipient and emptied the fixture email queue; browser refresh showed completed with one accepted result. No main-application worker was started and no real email was sent.
- No frontend styling changed. Existing unrelated Reverb/SIP console errors remain. Original rollout gates below are still pending.

## Production consumer diagnosis from operator output

The operator supplied terminal output from /var/www/laravel-crm on crm.cidglobal.ph. Supervisor reports only Horizon, Reverb and an AMI listener. Every listed Horizon worker consumes Redis queues default/imports/asterisk/telephony; none consumes email-campaigns. The deployed EmailCampaignQueueStatus reports connection email-database, queue email-campaigns, pending 0, missing heartbeat, and cache.default redis. This establishes the missing database consumer as the cause in that supplied snapshot. Two Horizon masters and a FATAL AMI listener are separate observed issues and were not modified.

A scoped Supervisor installation/review runbook is prepared; the template connection comment correctly names EMAIL_CAMPAIGN_CONNECTION. Production PHP-FPM/service identity and file permissions remain unverified. No production consumer was started from this session: direct SSH authentication was rejected. The operator must confirm service RUNNING and inspect().problem null after applying the reviewed configuration. No PHP/UI behavior changed in this follow-up; validation is source/runbook review and git diff --check, not a production runtime claim.

## Outstanding rollout checks still pending

- Install/configure approved Poppler pdfinfo and exercise positive, corrupt and encrypted PDF cases on the intended host; approved scanner if required. The real inspector fails closed when unavailable. No unreviewed PDF tool downloaded.
- Organization must approve payroll purpose, retention/backups and original PDF password requirements. Uploaded-PDF password protection is unavailable and policy-required payroll remains blocked. Live sending is disabled by default and in development.
- Production process supervisor, shared cache heartbeat, scheduler, provider quotas and actual SMTP acceptance/inbox callbacks are unverified. No delivered/bounced provider callbacks or automatic uncertain resends are provided.
- Multi-process MySQL contention was not exercised; transaction locks and idempotent repeat processing have source/unit evidence only.
- Keep change unarchived until required rollout and browser checks are satisfied under the supplied AGENTS.md completion rules.
