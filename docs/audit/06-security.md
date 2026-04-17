# Phase 6 - Security and config audit

## Session defaults ([`config/session.php`](../../config/session.php))

| Setting | Current value | Assessment |
|---------|---------------|------------|
| `driver` | `database` (env) | Good. `file` would risk inconsistent logouts under multi-node deploys. |
| `lifetime` | 120 minutes (env) | Reasonable for call-center agents. Consider 480 if shift length is longer. |
| `expire_on_close` | false (env) | Good. Agents routinely close the browser between calls. |
| `encrypt` | false (env) | Acceptable since session data is server-side (database). |
| `secure` | env | **Action required**: set `SESSION_SECURE_COOKIE=true` in production env. Not enforced in config to allow local http. |
| `http_only` | true | Good. |
| `same_site` | `lax` | Good. `strict` would break any cross-site nav flows (none in this app). |
| `partitioned` | false | Good - no third-party embed. |

**Recommendation:** add these two lines to the production `.env`:

```
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=<your-domain>
```

No code change needed in the repo; this is operational.

## Middleware coverage on `/api/*` ([`routes/web.php`](../../routes/web.php))

Scanned every `Route::...('/api/...')` declaration.

- **Authenticated + campaign-scoped** (`auth`, `campaign` middleware): all agent/supervisor call-handling endpoints (`api/call/*`, `api/vicidial/*`, `api/leads/*`, `api/reports/*`, `api/disposition/*`, `api/attendance/*`).
- **Authenticated + role-gated** (`role:Team Leader,Admin,Super Admin`): supervisor actions (`api/supervisor/monitor`, `whisper`, `force-pause`, `force-logout`, `send-notification`).
- **Authenticated + super-admin**: `admin/attendance-statuses/*` and other super-admin CRUD.
- **Unauthenticated** (by design):
  - `api/webhooks/ami` - Asterisk AMI events. Validated via `X-Webhook-Secret` header in [`AmiWebhookController`](../../app/Http/Controllers/Api/AmiWebhookController.php).
  - `api/webhooks/vicidial-events` - Vicidial push events. Validated via `X-Webhook-Secret` in [`VicidialEventsWebhookController`](../../app/Http/Controllers/Api/VicidialEventsWebhookController.php).
  - `api/telephony/health` - public health probe.
  - `api/websocket/health` - public WebSocket config for login page. Returns non-sensitive connection parameters only.

**Finding:** webhook secret check is guarded by `if ($secret !== '' && ...)`. An operator who **forgets** to set the env var leaves the webhook unauthenticated. Low-severity hardening opportunity: make the secret mandatory in production by throwing 503 when unset AND `app()->environment('production')`.

- No `/api/*` route is missing auth unless intentionally public. **No gaps found.**

## Rate limiting

All authenticated API endpoints use named throttles (`throttle:api`, `throttle:vicidial`). The login endpoint uses `throttle:login` - already covered by feature test `LoginRateLimitTest`. No changes.

## Dependency audits

- `composer audit`: **2 medium advisories** in `league/commonmark` (see [`00-baseline.md`](00-baseline.md)). Transitive via Laravel. No direct usage in app code found via `rg 'CommonMark|MarkdownConverter' app/`. Risk is low; wait for a compatible Laravel patch.
- `npm audit --omit=dev`: **0 vulnerabilities** across production deps.

## File upload endpoints

- Search for `mimes|mime_types|Illuminate\Http\UploadedFile` in `app/Http/Controllers` returned **no matches**.
- No user-facing CSV import route found in [`routes/web.php`](../../routes/web.php). The extraction feature (`admin.extraction.index`) is **download-only** (generate CSV), no upload.
- **Nothing to harden** in this phase.

## Telephony media path (Phase 3 cross-reference)

New `TELEPHONY_MEDIA_PATH` env var documented in [`03-telephony.md`](03-telephony.md). When left unset, defaults to `sipjs` (preserves pre-audit behavior). Misconfiguration is surfaced by the admin telephony diagnostics endpoint rather than silently failing.

## CSRF / same-origin

- `VerifyCsrfToken` applies to all POST routes in the `web` group by default.
- Exempted paths: the two webhooks. Both enforce a shared-secret header when configured. Documented in their respective controllers.
- No `Route::any(` or overly permissive methods in the auth group.

## Summary

| Category | Status |
|----------|--------|
| Session config defaults | OK (requires operational env vars for production) |
| Middleware coverage | Complete; no unauthenticated endpoints beyond intentional webhooks and health probes |
| Rate limiting | Complete |
| Webhook authentication | Implemented; improvement: enforce mandatory secret in prod |
| Dependency vulns | 2 medium (transitive `commonmark`), 0 in npm |
| File uploads | None in scope |

**No code changes made in Phase 6.** All findings are either operational (env vars) or already documented patterns. Recommendations:

1. Set `SESSION_SECURE_COOKIE=true` and `SESSION_DOMAIN` in production env.
2. Monitor for a `laravel/framework` patch that bumps `league/commonmark` past 2.8.1.
3. (Optional) add a production guard to webhook controllers that refuses to serve when the secret env var is empty.
