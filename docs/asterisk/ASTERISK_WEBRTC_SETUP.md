# Asterisk 13 WebRTC Setup Guide

**Server:** Asterisk 13.38.3  
**Strategy:** PJSIP for WebRTC browser agents + chan_sip for GoIP GSM trunk

---

## Phase 1 – Verify Required Modules

SSH into Asterisk server and run:

```bash
asterisk -rx "module show like pjsip"
asterisk -rx "module show like http_websocket"
asterisk -rx "module show like rtp"
```

If any module is missing, edit `/etc/asterisk/modules.conf`:

```ini
[modules]
autoload=yes
load = res_pjsip.so
load = res_pjsip_endpoint_identifier_anonymous.so
load = res_pjsip_endpoint_identifier_ip.so
load = res_pjsip_endpoint_identifier_user.so
load = res_pjsip_transport_websocket.so
load = res_http_websocket.so
load = res_rtp_asterisk.so
load = res_pjsip_sdp_rtp.so
load = res_pjsip_session.so
load = res_pjsip_authenticator_digest.so
load = res_pjsip_registrar.so
load = res_pjsip_outbound_registration.so
load = chan_pjsip.so
```

Reload:

```bash
asterisk -rx "core restart gracefully"
```

---

## Phase 2 – HTTP + WSS (Self-Signed Certs)

### Generate self-signed certificate

```bash
mkdir -p /etc/asterisk/keys
openssl req -x509 -nodes -days 3650 \
  -newkey rsa:2048 \
  -keyout /etc/asterisk/keys/asterisk.key \
  -out /etc/asterisk/keys/asterisk.pem \
  -subj "/CN=asterisk.local"
# Asterisk needs cert + key concatenated in one pem:
cat /etc/asterisk/keys/asterisk.key >> /etc/asterisk/keys/asterisk.pem
chown asterisk:asterisk /etc/asterisk/keys/*
chmod 640 /etc/asterisk/keys/*
```

### `/etc/asterisk/http.conf`

```ini
[general]
enabled=yes
bindaddr=0.0.0.0
bindport=8088

tlsenable=yes
tlsbindaddr=0.0.0.0:8089
tlscertfile=/etc/asterisk/keys/asterisk.pem
tlsprivatekey=/etc/asterisk/keys/asterisk.key

enablestatic=no
```

Reload HTTP:

```bash
asterisk -rx "http show status"
asterisk -rx "module reload res_http_websocket"
```

**IMPORTANT (self-signed certs):**  
Each agent must visit `https://ASTERISK_IP:8089` once in their browser and click "Accept risk / proceed" to trust the self-signed cert before WebRTC will work.

---

## Phase 3 – PJSIP Configuration

### `/etc/asterisk/pjsip.conf`

Append to the **bottom** of your existing `pjsip.conf` (or create if not present). Do NOT remove any existing content.

```ini
;; ============================================================
;; WebRTC WSS Transport
;; ============================================================
[transport-wss]
type=transport
protocol=wss
bind=0.0.0.0

;; ============================================================
;; WebRTC Endpoint Template
;; ============================================================
[webrtc_endpoint](!)
type=endpoint
context=from-internal
disallow=all
allow=opus,ulaw,alaw
webrtc=yes
media_encryption=dtls
dtls_auto_generate_cert=yes
dtls_verify=fingerprint
dtls_setup=actpass
ice_support=yes
direct_media=no
rtp_symmetric=yes
force_rport=yes
rewrite_contact=yes
use_avpf=yes
media_use_received_transport=yes
trust_id_inbound=yes
send_rpid=yes
send_pai=yes

;; ============================================================
;; Per-Agent Endpoints
;; Add one block per agent. Match extension = user's CRM extension.
;; ============================================================

;; Example: agent with extension 6001
;[6001](webrtc_endpoint)
;auth=auth6001
;aors=6001
;callerid="Agent Name" <6001>
;
;[auth6001]
;type=auth
;auth_type=userpass
;username=6001
;password=CHANGE_TO_SECURE_PASSWORD
;
;[6001]
;type=aor
;max_contacts=1
;remove_existing=yes
;qualify_frequency=30
```

After adding agents, reload PJSIP:

```bash
asterisk -rx "module reload res_pjsip.so"
asterisk -rx "pjsip show endpoints"
asterisk -rx "pjsip show transports"
```

---

## Phase 4 – GoIP chan_sip Trunk (Verify Unchanged)

Open `/etc/asterisk/sip.conf` and confirm your GoIP trunk still has:

```ini
[goip-trunk]
type=peer
host=GOIP_IP
directmedia=no
qualify=yes
nat=force_rport,comedia
rtpkeepalive=30
```

Do NOT modify chan_sip. PJSIP and chan_sip coexist on the same Asterisk.

---

## Phase 5 – Dial Plan (extensions.conf)

Add to `/etc/asterisk/extensions.conf` in the `[from-internal]` context:

```ini
[from-internal]

;; WebRTC agent outbound call via GoIP trunk
;; Prefix 9 to dial out: agent dials 9+number
exten => _9.,1,NoOp(WebRTC outbound from ${CALLERID(num)} to ${EXTEN:1})
 same => n,Set(CALLERID(num)=+63XXXXXXXXXX)  ; Set your outbound caller ID
 same => n,Dial(SIP/goip-trunk/${EXTEN:1},45,g)
 same => n,Congestion()
 same => n,Hangup()

;; Direct dial without prefix (if preferred)
exten => _+63XXXXXXXXXX,1,NoOp(WebRTC outbound international)
 same => n,Dial(SIP/goip-trunk/${EXTEN},45,g)
 same => n,Hangup()
```

Reload dial plan:

```bash
asterisk -rx "dialplan reload"
```

---

## Verification Commands

```bash
# Show all PJSIP endpoints
asterisk -rx "pjsip show endpoints"

# Show registered WebSocket connections
asterisk -rx "http show status"

# Show chan_sip peers (GoIP trunk should show OK)
asterisk -rx "sip show peers"

# Test registration from browser (check logs)
tail -f /var/log/asterisk/messages | grep PJSIP
```

---

## Firewall Ports to Open

| Port | Protocol | Purpose |
|------|----------|---------|
| 8088 | TCP | Asterisk HTTP (non-TLS WS) |
| 8089 | TCP | Asterisk HTTPS (WSS for WebRTC) |
| 5038 | TCP | AMI (Laravel → Asterisk) |
| 10000–20000 | UDP | RTP media |

---

## AMI Event Listener (Optional)

To keep Laravel `call_sessions` in sync when the remote party hangs up, run the AMI Event Listener. It connects to Asterisk AMI, receives Hangup/Bridge/DialEnd events, and POSTs them to `POST /api/webhooks/ami`.

```bash
php artisan ami:listen
```

Options:
- `--reconnect-delay=5` – Seconds to wait before reconnecting after disconnect
- `--webhook-url=URL` – Override webhook URL (default: APP_URL/api/webhooks/ami)

**Supervisor config** (`/etc/supervisor/conf.d/ami-listener.conf`):

```ini
[program:laravel-ami-listener]
process_name=%(program_name)s
command=php /path/to/laravel-crm/artisan ami:listen
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/laravel-crm/storage/logs/ami-listener.log
```
