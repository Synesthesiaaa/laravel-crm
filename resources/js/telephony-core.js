/**
 * TelephonyCore – SIP.js WebRTC client singleton.
 *
 * Manages SIP registration, incoming INVITE auto-answer, mute, hold, and hangup.
 * Persists on window.TelephonyCore across page navigations (SPA-like behavior).
 *
 * Call flow:
 *   1. Laravel API creates CallSession + sends AMI Originate to SIP/{extension}
 *   2. Asterisk sends INVITE to browser via WSS
 *   3. SIP.js auto-answers the INVITE (agent is the "callee")
 *   4. Asterisk bridges agent to GoIP trunk → GSM call proceeds
 *   5. Reverb broadcasts state changes to the UI
 */

import {
    UserAgent,
    Registerer,
    RegistererState,
    SessionState,
    Web,
} from 'sip.js';
import TelephonyLogger from './telephony-logger';

// ── Error codes (mirror app/Support/CallErrors.php) ──────────────────────────
const CALL_ERRORS = {
    NETWORK_FAILURE:      'NETWORK_FAILURE',
    EXTENSION_OFFLINE:    'EXTENSION_OFFLINE',
    SIP_NOT_REGISTERED:   'SIP_NOT_REGISTERED',
    NO_ANSWER:            'NO_ANSWER',
    BUSY:                 'BUSY',
    CHANNEL_UNAVAILABLE:  'CHANNEL_UNAVAILABLE',
    AUTH_FAILURE:         'AUTH_FAILURE',
};

// ── Module-level state ────────────────────────────────────────────────────────
let _userAgent      = null;
let _registerer     = null;
let _activeSession  = null;   // current InvitationAcceptOptions session
let _credentials    = null;   // cached /api/sip/credentials response
let _noAnswerTimer  = null;

// ── Helpers ───────────────────────────────────────────────────────────────────
function getAlpineStore(name) {
    return window.Alpine?.store?.(name) ?? null;
}

function toastError(msg) {
    getAlpineStore('toast')?.error(msg);
}

function toastInfo(msg) {
    getAlpineStore('toast')?.info(msg);
}

function setCallState(state) {
    const store = getAlpineStore('call');
    if (store) store.state = state;
}

function mapSipStatusToError(statusCode) {
    if (statusCode === 486) return CALL_ERRORS.BUSY;
    if (statusCode === 503) return CALL_ERRORS.CHANNEL_UNAVAILABLE;
    if (statusCode === 401 || statusCode === 403) return CALL_ERRORS.AUTH_FAILURE;
    if (statusCode === 404 || statusCode === 480) return CALL_ERRORS.EXTENSION_OFFLINE;
    return CALL_ERRORS.CHANNEL_UNAVAILABLE;
}

function clearNoAnswerTimer() {
    if (_noAnswerTimer) {
        clearTimeout(_noAnswerTimer);
        _noAnswerTimer = null;
    }
}

/**
 * Attach remote audio stream to the <audio id="remoteAudio"> element.
 */
function attachRemoteAudio(session) {
    const remoteAudioEl = document.getElementById('remoteAudio');
    if (!remoteAudioEl) return;

    const remoteStream = new MediaStream();

    session.sessionDescriptionHandler.peerConnection
        .getReceivers()
        .forEach(receiver => {
            if (receiver.track) remoteStream.addTrack(receiver.track);
        });

    remoteAudioEl.srcObject = remoteStream;
    remoteAudioEl.play().catch(() => {
        // Autoplay blocked – user interaction required first
    });
}

// ── Session event wiring ──────────────────────────────────────────────────────
function wireSessionEvents(session, noAnswerTimeout) {
    const callStore = getAlpineStore('call');

    session.stateChange.addListener((state) => {
        TelephonyLogger.debug('TelephonyCore', 'Session state changed', { state });

        switch (state) {
            case SessionState.Establishing:
                setCallState('ringing');
                clearNoAnswerTimer();
                _noAnswerTimer = setTimeout(() => {
                    TelephonyLogger.warn('TelephonyCore', 'No-answer timeout reached');
                    toastError('Call not answered within the allowed time.');
                    setCallState('idle');
                    hangup();
                }, (noAnswerTimeout || 45) * 1000);
                break;

            case SessionState.Established:
                clearNoAnswerTimer();
                setCallState('connected');
                callStore?.startTimer?.();
                attachRemoteAudio(session);
                break;

            case SessionState.Terminating:
            case SessionState.Terminated:
                clearNoAnswerTimer();
                if (callStore) {
                    callStore.stopTimer?.();
                    if (!['idle', 'wrapup'].includes(callStore.state)) {
                        callStore.state = 'wrapup';
                    }
                }
                _activeSession = null;
                break;
        }
    });
}

// ── Public API ────────────────────────────────────────────────────────────────

/**
 * Fetch SIP credentials and register with Asterisk via WSS.
 * Idempotent: if already registered, returns immediately.
 */
async function register() {
    if (_registerer && _registerer.state === RegistererState.Registered) {
        TelephonyLogger.debug('TelephonyCore', 'Already registered, skipping');
        return;
    }

    // Tear down stale UA if it exists
    if (_userAgent) {
        try { await _userAgent.stop(); } catch (_) {}
        _userAgent = null;
        _registerer = null;
    }

    try {
        const res = await window.axios.get('/api/sip/credentials');
        if (!res.data?.success) {
            TelephonyLogger.warn('TelephonyCore', 'No SIP credentials', { message: res.data?.message });
            return;
        }
        _credentials = res.data;
    } catch (err) {
        TelephonyLogger.warn('TelephonyCore', 'Could not fetch SIP credentials', { error: err?.message || err });
        return;
    }

    const { sip_uri, ws_url, password, domain, ice_servers, no_answer_timeout } = _credentials;

    const uri = UserAgent.makeURI('sip:' + sip_uri);
    if (!uri) {
        TelephonyLogger.error('TelephonyCore', 'Invalid SIP URI', { sip_uri });
        return;
    }

    const iceServers = (Array.isArray(ice_servers) && ice_servers.length)
        ? ice_servers
        : [{ urls: 'stun:' + (import.meta.env.VITE_STUN_SERVER || 'stun.l.google.com:19302') }];

    _userAgent = new UserAgent({
        uri,
        transportOptions: {
            server: ws_url,
            traceSip: import.meta.env.DEV ?? false,
        },
        authorizationPassword: password,
        authorizationUsername: sip_uri.split('@')[0],
        sessionDescriptionHandlerFactoryOptions: {
            peerConnectionConfiguration: {
                iceServers,
            },
        },
        delegate: {
            /**
             * Incoming INVITE from AMI Originate (Asterisk calls agent's browser first).
             * Auto-answer so the agent is connected and Asterisk bridges to GoIP trunk.
             */
            onInvite(invitation) {
                TelephonyLogger.event('TelephonyCore', 'IncomingInvite', 'Incoming INVITE (AMI originate)');
                _activeSession = invitation;

                setCallState('ringing');
                wireSessionEvents(invitation, no_answer_timeout);

                // Auto-answer the INVITE: agent browser becomes the "phone"
                invitation.accept({
                    sessionDescriptionHandlerOptions: {
                        constraints: { audio: true, video: false },
                    },
                }).then(() => {
                    TelephonyLogger.info('TelephonyCore', 'INVITE accepted, call established');
                }).catch(err => {
                    TelephonyLogger.error('TelephonyCore', 'Failed to accept INVITE', { error: err?.message || err });
                    toastError('Failed to establish call: ' + (err.message || 'unknown error'));
                    setCallState('idle');
                });
            },
        },
        logLevel: import.meta.env.DEV ? 'debug' : 'error',
    });

    _registerer = new Registerer(_userAgent);

    _registerer.stateChange.addListener((state) => {
        TelephonyLogger.debug('TelephonyCore', 'Registerer state changed', { state });
        if (state === RegistererState.Unregistered) {
            TelephonyLogger.warn('TelephonyCore', 'SIP unregistered, attempting re-register in 10s');
            setTimeout(() => register(), 10000);
        }
    });

    try {
        await _userAgent.start();
        await _registerer.register();
        TelephonyLogger.info('TelephonyCore', 'SIP registered', { sip_uri });
    } catch (err) {
        TelephonyLogger.error('TelephonyCore', 'SIP registration failed', { error: err?.message || err });
        const errCode = err?.cause?.status_code ?? null;
        const callError = errCode ? mapSipStatusToError(errCode) : CALL_ERRORS.SIP_NOT_REGISTERED;
        toastError('SIP registration failed (' + callError + ')');
    }
}

/**
 * Hang up the active SIP session.
 * Also notifies Laravel API to update call state.
 */
async function hangup() {
    clearNoAnswerTimer();

    if (_activeSession) {
        try {
            switch (_activeSession.state) {
                case SessionState.Initial:
                case SessionState.Establishing:
                    await _activeSession.reject();
                    break;
                case SessionState.Established:
                    await _activeSession.bye();
                    break;
                default:
                    break;
            }
        } catch (err) {
            TelephonyLogger.warn('TelephonyCore', 'Hangup error', { error: err?.message || err });
        }
        _activeSession = null;
    }

    // Notify backend
    const callStore = getAlpineStore('call');
    const sessionId = callStore?.sessionId ?? null;
    try {
        await window.axios.post('/api/call/hangup', { session_id: sessionId });
    } catch (_) {}

    callStore?.stopTimer?.();
    setCallState(callStore?.state === 'connected' ? 'wrapup' : 'idle');
}

/**
 * Mute / unmute local audio.
 */
function setMuted(muted) {
    if (!_activeSession?.sessionDescriptionHandler) return;
    const pc = _activeSession.sessionDescriptionHandler.peerConnection;
    pc.getSenders().forEach(sender => {
        if (sender.track?.kind === 'audio') {
            sender.track.enabled = !muted;
        }
    });
}

function mute()   { setMuted(true);  }
function unmute() { setMuted(false); }

/**
 * Hold / unhold via SIP re-INVITE.
 */
async function hold() {
    if (_activeSession?.state !== SessionState.Established) return;
    try {
        await _activeSession.invite({
            sessionDescriptionHandlerModifiers: [Web.holdModifier],
        });
        setCallState('hold');
    } catch (err) {
        TelephonyLogger.error('TelephonyCore', 'Hold failed', { error: err?.message || err });
    }
}

async function unhold() {
    if (_activeSession?.state !== SessionState.Established) return;
    try {
        await _activeSession.invite({
            sessionDescriptionHandlerModifiers: [],
        });
        setCallState('connected');
    } catch (err) {
        TelephonyLogger.error('TelephonyCore', 'Unhold failed', { error: err?.message || err });
    }
}

/**
 * Unregister and destroy the UA (call on logout only).
 */
async function destroy() {
    clearNoAnswerTimer();
    try {
        if (_registerer) await _registerer.unregister();
        if (_userAgent) await _userAgent.stop();
    } catch (_) {}
    _userAgent     = null;
    _registerer    = null;
    _activeSession = null;
    _credentials   = null;
}

/**
 * Returns true if a session is currently active.
 */
function hasActiveCall() {
    return _activeSession !== null &&
        ![SessionState.Terminated, SessionState.Terminating].includes(_activeSession?.state);
}

// ── Expose singleton ──────────────────────────────────────────────────────────
const TelephonyCore = {
    register,
    hangup,
    mute,
    unmute,
    hold,
    unhold,
    destroy,
    hasActiveCall,
    CALL_ERRORS,
};

export default TelephonyCore;
