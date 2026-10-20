# Implementation inventory

## Created application files

- `config/sms.php`
- `database/migrations/2026_10_20_000001_create_sms_management_tables.php`: sms_gateways, sms_channels, sms_templates, sms_consents, sms_opt_outs, sms_campaigns, sms_messages, sms_gateway_events.
- `app/Models/`: SmsGateway.php, SmsChannel.php, SmsTemplate.php, SmsConsent.php, SmsOptOut.php, SmsCampaign.php, SmsMessage.php, SmsGatewayEvent.php.
- `app/Services/Sms/`: SmsText.php, GoipHttpTransport.php, SmsCampaignService.php, GoipReceiver.php.
- `app/Jobs/SendSmsMessage.php`.
- `app/Console/Commands/`: MaintainSmsCampaigns.php, ReceiveGoipSms.php, PruneSmsContent.php.
- `app/Http/Requests/`: SmsCampaignRequest.php, SmsGatewayRequest.php.
- `app/Http/Controllers/Admin/SmsController.php`.
- `routes/sms.php`: Super Admin /admin/sms overview/workspaces, campaigns/review/update/reset/recipients/import/leads/confirm/test/pause/resume/cancel, consent/revocation, private history filter, gateway/channel settings, safe network test, templates, read state, reconciliation, emergency stop, recipient template and reports.
- `resources/views/admin/sms/`: index.blade.php, review.blade.php, gateway-form.blade.php.
- `tests/Unit/SmsTextTest.php` and `tests/Feature/Admin/SmsManagementTest.php`.
- `deploy/supervisor/laravel-crm-sms.conf.example` and `docs/SMS_MANAGEMENT.md`.

## Modified existing files

- `.env.example`: safe SMS defaults and operational settings.
- `README.md`: SMS guide link.
- `app/Services/UserActivityRecorder.php`: excludes recipient search values from SMS request audit queries.
- `app/Support/AdminNavigation.php`: Super Admin SMS Management destination/group.
- `routes/admin.php`: loads SMS routes inside the existing Super Admin gate.
- `routes/console.php`: scheduled SMS maintenance and explicitly opted-in content retention.

## Specifications and review evidence

- `openspec/specs/sms-management/spec.md`: synchronized capability.
- `openspec/changes/goip-sms-management/`: proposal, design, capability delta, tasks, surface brief, file inventory, automated/browser validation and design/finish review evidence.
- `.impeccable/surfaces/resources-views-admin-sms-index-blade-php.md`: scoped Operate direction, preserving incumbent system.
- `.taste-skill/`: generated app direction; palette/type retained.
- `output/playwright/sms-*.png`: ten synthetic-data validation captures.

No dependency, global design-system file, existing CRM table, VICIDial, Asterisk, SIP or dialplan implementation was changed. Temporary preview scripts/database were removed and the isolated browser/server were stopped. No SMS worker or receiver was enabled against the actual CRM configuration.
