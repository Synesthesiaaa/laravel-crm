# Phase 3 - Telephony dual-stack clarification

## Problem

The CRM has two WebRTC paths that can run at once for the same agent:

1. **SIP.js** in the browser via [`resources/js/telephony-core.js`](../../resources/js/telephony-core.js), registered by the bootstrap block in [`resources/views/layouts/app.blade.php`](../../resources/views/layouts/app.blade.php).
2. **Vicidial ViciPhone** running inside the agent session iframe driven by [`resources/js/vicidial-session.js`](../../resources/js/vicidial-session.js).

When both register with Asterisk using the **same extension**, call setup becomes flaky: re-INVITEs race, DTLS fingerprints mismatch, one path hangs the other. The `NOTICE ... Received AVP profile in audio answer but AVPF is enabled` + `WARNING ... Failed to receive SDP offer/answer with required SRTP crypto attributes` lines observed in Asterisk logs are the tell-tale signature.

## Decision framework

There is no "right" answer without knowing the deployment. Both are viable:

- **SIP.js owns audio** (`media_path=sipjs`, default)
  - Pros: CRM has full control of audio; no hidden state in Vicidial iframe; click-to-call works uniformly.
  - Cons: You must implement every Vicidial action (login, pause, transfer) in the CRM. The Vicidial iframe is still useful for session UI but must be configured with audio **disabled**.

- **ViciPhone owns audio** (`media_path=viciphone`)
  - Pros: Vicidial handles dialing, pause codes, campaign logic out of the box.
  - Cons: The CRM's SIP.js stack and click-to-call feature become dead weight. Browser audio path depends on Vicidial version.

The correct answer usually depends on:

1. Can you disable ViciPhone audio in your Vicidial agent page? (Vicidial admin -> user -> "Phone Login" iframe settings.)
2. Does your Asterisk have a PJSIP WebRTC endpoint configured for the CRM extension? (The SRTP warning in the original log points at chan_sip; a proper PJSIP endpoint with `media_encryption=dtls` + `rtp_symmetric=yes` removes those warnings.)
3. Do supervisors need features (monitor/whisper) that are already implemented against one stack?

## Implementation: feature flag

Added a single flag so the decision is codified instead of implicit.

- **File:** [`config/webrtc.php`](../../config/webrtc.php)
- **Env var:** `TELEPHONY_MEDIA_PATH`
- **Values:** `sipjs` (default), `viciphone`, `both`

The flag is read in the authenticated layout and exposed to the browser as `window.__telephonyMediaPath` ([`resources/views/layouts/app.blade.php`](../../resources/views/layouts/app.blade.php) around the telephony bootstrap block).

### Browser behavior per value

| Value | `TelephonyCore.register()` | ViciPhone audio | Notes |
|-------|----------------------------|-----------------|-------|
| `sipjs` (default) | yes | should be disabled in Vicidial user profile | Current pre-audit behavior preserved |
| `viciphone` | **skipped** | enabled | Use when Vicidial handles media |
| `both` | yes | enabled | Migration / debugging only; logs a `console.warn` |

### Diagnostics

New admin check surfaces the config: [`app/Http/Controllers/Admin/TelephonyDiagnosticsController.php`](../../app/Http/Controllers/Admin/TelephonyDiagnosticsController.php) `checkMediaPath()`.

- Returns `ok` for `sipjs` or `viciphone`.
- Returns `warn` for `both` with an explanation.
- Returns `fail` for unknown values.

The existing "Campaign -> ViciDial Server Mapping" check was also fixed: it was running a `VicidialServer::where(...)->first()` per campaign in a loop. Now it does **one** `whereIn('campaign_code', ...)` query and looks up in memory.

## Asterisk side (documentation only)

Repository-side changes stop short of server config. The following are the minimum Asterisk steps required to eliminate the SRTP/AVPF mismatch seen in logs, regardless of which `media_path` is chosen:

1. **Use PJSIP, not chan_sip**, for WebRTC endpoints.
2. PJSIP endpoint must set `transport=transport-wss`, `webrtc=yes`, `media_encryption=dtls`, `dtls_auto_generate_cert=yes`, `rtp_symmetric=yes`, `force_rport=yes`, `rewrite_contact=yes`.
3. Ensure browser-facing WSS cert is trusted or use a valid cert; self-signed breaks some browsers under strict privacy settings.

A deeper Asterisk guide lives at [`docs/asterisk/`](../asterisk/) (existing). When a decision is made, add a `docs/telephony/pjsip-webrtc.md` with the exact snippets.

## Verification

- `php artisan config:clear && php artisan config:cache` after changing `.env`.
- `GET /admin/telephony-diagnostics` (existing endpoint) now includes the **Telephony Media Path** check.
- If switching to `viciphone`, the browser console should **not** print `[TelephonyInit] SIP register...` lines, confirming SIP.js is inactive.

## Rollback

Delete the env var (or set `TELEPHONY_MEDIA_PATH=sipjs`). Behavior reverts to pre-audit defaults.

## Open question

The project owner still needs to pick `sipjs` vs `viciphone` for production. This phase delivers the **mechanism**; the **decision** is an operational one. Until then, the default `sipjs` preserves the current runtime behavior.
