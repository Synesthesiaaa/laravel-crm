# Configuration

Start with [.env.example](../../.env.example), then inspect the corresponding `config/` file. This guide documents names and purpose, not the values of a running installation. Never commit `.env` or expose it through a web server.

## Core settings

| Area | Variables | Source and important behavior |
| --- | --- | --- |
| App | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE` | [app.php](../../config/app.php); UTC default; preserve an established encryption key |
| Database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | [database.php](../../config/database.php); SQLite example and MySQL connections |
| Session | `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`, `SESSION_SAME_SITE`, `SESSION_DOMAIN`, `SESSION_PATH` | [session.php](../../config/session.php); keep database sessions for the current replacement workflow |
| Cache | `CACHE_STORE`, `CACHE_PREFIX` | [cache.php](../../config/cache.php); web and workers need a coherent shared cache/prefix for locks and heartbeats |
| Queues | `QUEUE_CONNECTION`, `DB_QUEUE_CONNECTION`, `DB_QUEUE_TABLE`, `DB_QUEUE_RETRY_AFTER`, `REDIS_QUEUE_RETRY_AFTER` | [queue.php](../../config/queue.php); a connection and a queue name are separate concepts |
| Redis | `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_DB`, `REDIS_CACHE_DB`, `REDIS_QUEUE_CONNECTION` | [database.php](../../config/database.php), [queue.php](../../config/queue.php); phpredis needs its extension |
| Logs | `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL`, `LOG_DAILY_DAYS`, `LOG_AUDIT_DAYS`, `LOG_SECURITY_DAYS`, `LOG_TELEPHONY_DAYS`, `LOG_TELEPHONY_EVENTS_DAYS`, `LOG_TELEPHONY_ERRORS_DAYS`, `LOG_RATE_LIMIT_DAYS` | [logging.php](../../config/logging.php); channels have different retention/content |

## Realtime and browser settings

`BROADCAST_CONNECTION` selects log/null versus Reverb or another configured broadcaster. Reverb uses `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, `REVERB_SERVER_HOST`, and `REVERB_SERVER_PORT`. The public reverse-proxy address can differ from the internal listening address. See [broadcasting.php](../../config/broadcasting.php), [reverb.php](../../config/reverb.php), and [Reverb integration](../integrations/laravel-reverb.md).

`VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`, `VITE_PUSHER_APP_KEY`, and `VITE_PUSHER_APP_CLUSTER` are browser build values. Never put the Reverb secret or other credentials into `VITE_*`. Rebuild assets after changing browser-exposed values.

## VICIdial and AMI

- API: `VICI_API_URL`, `VICI_NON_AGENT_API_URL`, `VICI_SOURCE`, `VICI_VERIFY_SSL`, `VICI_CONNECT_TIMEOUT`, `VICI_API_TIMEOUT`, `VICI_RETRY_TIMES`, `VICI_AGENT_CONNECT_TIMEOUT`, `VICI_AGENT_API_TIMEOUT`.
- Database: `VICI_DB_URL`, `VICI_DB_HOST`, `VICI_DB_PORT`, `VICI_DB_NAME`, `VICI_DB_USERNAME`, `VICI_DB_PASSWORD`, `VICI_DB_SOCKET`.
- Login: `VICI_AUTO_BOOTSTRAP`, `VICI_DEFAULT_CAMPAIGN`, `VICI_REQUIRE_VICIDIAL_SESSION_BEFORE_DIAL`, `VICI_SESSION_IFRAME_AGENT_API_ONLY`, `VICI_SESSION_IFRAME_CONFIRM_NON_AGENT_LIVE`, `VICI_SESSION_SKIP_NON_AGENT_LIVE_CHECK`. Automatic bootstrap also needs per-user `auto_vici_login` and complete credentials. Read effective config rather than obsolete example comments.
- Call history: `VICI_CALL_HISTORY_SYNC_ENABLED`, `VICI_CALL_HISTORY_RECENT_WINDOW_MINUTES`, `VICI_CALL_HISTORY_OVERLAP_MINUTES`, `VICI_CALL_HISTORY_SYNC_CHUNK_SIZE`, `VICI_CALL_HISTORY_JOB_TIMEOUT_SECONDS`, `VICI_CALL_HISTORY_RETRY_TIMES`, `VICI_CALL_HISTORY_STALE_AFTER_MINUTES`. This is scheduled dispatch plus a `telephony` queue consumer.
- Reporting: `VICI_REPORT_TIMEZONE`, `VICI_SUPERVISOR_POLL_SECONDS`, `VICI_SUPERVISOR_ROLLING_WINDOW_MINUTES`, `VICI_SUPERVISOR_STALE_AFTER_SECONDS`, `VICI_SUPERVISOR_REMOTE_CACHE_SECONDS`, and report disposition classification variables in [vicidial.php](../../config/vicidial.php).
- AMI: `ASTERISK_AMI_HOST`, `ASTERISK_AMI_PORT`, `ASTERISK_AMI_USERNAME`, `ASTERISK_AMI_SECRET`, `ASTERISK_AMI_TIMEOUT`, `ASTERISK_AMI_READ_TIMEOUT`, `ASTERISK_GOIP_TRUNK`, `AMI_LISTENER_RECONNECT_DELAY`, `AMI_LISTENER_MAX_RECONNECT_DELAY` in [asterisk.php](../../config/asterisk.php). Agent channel is hardcoded `SIP`; the historical `ASTERISK_AGENT_CHANNEL` example does not switch it to PJSIP.
- Incoming webhooks: `ASTERISK_AMI_WEBHOOK_SECRET`, `VICIDIAL_EVENTS_WEBHOOK_SECRET`, `VICIDIAL_CALL_URL_SECRET`. Missing POST webhook secrets fail in production; GET callbacks need their shared signature. See [API reference](../developer/api-reference.md).

Campaign-linked VICIdial server records can select the actual endpoint and credentials. Environment defaults alone do not establish the effective connection. Do not copy internal fallback hostnames/IPs from source into public examples.

## Browser voice

[webrtc.php](../../config/webrtc.php) reads `ASTERISK_WS_URL`, `SIP_DOMAIN`, `STUN_SERVER`, `ICE_SERVERS`, `SIP_REGISTER_TIMEOUT`, `SIP_NO_ANSWER_TIMEOUT`, and `TELEPHONY_MEDIA_PATH`. Browser build values include `VITE_ASTERISK_WS_URL`, `VITE_SIP_DOMAIN`, and `VITE_STUN_SERVER`. `ICE_SERVERS` is JSON for additional connectivity servers. Choose one audio owner, `sipjs` or `viciphone`; `both` risks duplicate registration. See [SIP/WebRTC](../integrations/sip-webrtc.md).

## Email and application settings

Read the [email guide](../EMAIL_CAMPAIGNS.md) for `MAIL_*`, `EMAIL_CAMPAIGN_*`, `EMAIL_DOCUMENT_*`, `EMAIL_PDFINFO_BINARY`, `EMAIL_MAX_RECIPIENTS`, `EMAIL_PAYROLL_*`, and OCR binary settings. Saved SMTP settings and queue-time snapshots also influence sending; environment changes are not a universal stop switch for existing campaigns.

Branding, report classifications, retention policies, and telephony feature access live in the application. TelephonyFeatureService stores global `system_settings` flags and caches them for five minutes. `agent_screen_access` defaults false; the other declared flags default true. Flags are not campaign membership rules.

## Applying changes

After an authorized deployment configuration change, rebuild configuration with `php artisan config:cache`; restart long-running consumers/listeners and Reverb so they read the same settings as web requests. Rebuild frontend assets when `VITE_*` changes. Never run `key:generate` as routine maintenance on established encrypted data.

Related: [production configuration](../operations/production-configuration.md), [worker topology](../operations/queues-and-workers.md), [security](../operations/security.md).
