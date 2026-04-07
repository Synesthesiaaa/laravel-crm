const DEFAULT_VERIFY_MAX = 15;
const DEFAULT_VERIFY_DELAY_MS = 1500;
const DEFAULT_TIMEOUT_MS = 20000;

function isIframeAgentApiOnly() {
    return window.__VICIDIAL_SESSION_IFRAME_ONLY === true;
}

const state = {
    inflight: false,
    verifyTimer: null,
    verifyTimeout: null,
    verifyCount: 0,
};

function getCampaign(campaign) {
    return campaign || document.body?.dataset?.campaign || 'mbsales';
}

function phaseSet(ctx, phase) {
    if (ctx?.vici) {
        ctx.vici.phase = phase;
    }
}

function loadingSet(ctx, loading) {
    if (ctx?.vici) {
        ctx.vici.loading = loading;
    }
}

function cancelVerify(ctx) {
    if (state.verifyTimer) {
        clearTimeout(state.verifyTimer);
        state.verifyTimer = null;
    }
    if (state.verifyTimeout) {
        clearTimeout(state.verifyTimeout);
        state.verifyTimeout = null;
    }
    state.verifyCount = 0;
    if (ctx?.vici) {
        ctx.vici._verifyPollCount = 0;
    }
}

async function syncStatus(campaign, ctx = null) {
    const effectiveCampaign = getCampaign(campaign);
    const data = await window.Alpine.store('vicidial').sync(effectiveCampaign);
    const localStatus = data?.local_session?.session_status || '';

    if (['ready', 'paused', 'in_call'].includes(localStatus)) {
        phaseSet(ctx, 'ready');
    } else if (localStatus === 'logged_out') {
        phaseSet(ctx, 'idle');
    }

    return data;
}

async function pollVerify(campaign, ctx = null, maxAttempts = DEFAULT_VERIFY_MAX) {
    if (state.verifyCount >= maxAttempts) {
        cancelVerify(ctx);
        phaseSet(ctx, 'timeout');
        loadingSet(ctx, false);
        window.Alpine.store('toast').warning(
            'VICIdial session did not confirm in time. It may become available shortly - check your credentials if it persists.'
        );
        state.inflight = false;
        return false;
    }

    state.verifyTimer = setTimeout(async () => {
        state.verifyCount++;
        if (ctx?.vici) {
            ctx.vici._verifyPollCount = state.verifyCount;
        }

        try {
            const res = await window.axios.post('/api/vicidial/session/verify', { campaign });
            if (res.data?.success === false && res.data?.data?.stop_verify_poll) {
                cancelVerify(ctx);
                phaseSet(ctx, 'failed');
                loadingSet(ctx, false);
                state.inflight = false;
                window.Alpine.store('toast').error(res.data?.message || 'VICIdial verify failed.');
                return;
            }
            const loginState = res.data?.login_state;
            if (loginState === 'ready') {
                cancelVerify(ctx);
                phaseSet(ctx, 'ready');
                loadingSet(ctx, false);
                state.inflight = false;
                window.Alpine.store('toast').success('VICIdial session is live and ready.');
                await syncStatus(campaign, ctx);
                if (window.TelephonyCore?.register) {
                    window.TelephonyCore.register().catch(() => {});
                }
                return;
            }
        } catch (_) {
            // 202 pending and transient errors should continue polling.
        }

        await pollVerify(campaign, ctx, maxAttempts);
    }, DEFAULT_VERIFY_DELAY_MS);

    return true;
}

/**
 * Single verify after iframe load (used when Non-Agent polling is disabled).
 */
async function verifyOnceAfterIframeLoad(campaign, ctx = null) {
    const effectiveCampaign = getCampaign(campaign);
    phaseSet(ctx, 'syncing');
    state.verifyCount = 1;
    if (ctx?.vici) {
        ctx.vici._verifyPollCount = 1;
    }

    try {
        const res = await window.axios.post('/api/vicidial/session/verify', { campaign: effectiveCampaign });
        cancelVerify(ctx);

        if (res.data?.success === false && res.data?.data?.stop_verify_poll) {
            phaseSet(ctx, 'failed');
            loadingSet(ctx, false);
            state.inflight = false;
            window.Alpine.store('toast').error(res.data?.message || 'VICIdial verify failed.');
            return;
        }

        const loginState = res.data?.login_state;
        if (loginState === 'ready') {
            phaseSet(ctx, 'ready');
            loadingSet(ctx, false);
            state.inflight = false;
            window.Alpine.store('toast').success(
                isIframeAgentApiOnly()
                    ? 'VICIdial session ready (iframe load; Non-Agent status disabled).'
                    : 'VICIdial session is live and ready.'
            );
            await syncStatus(effectiveCampaign, ctx);
            if (window.TelephonyCore?.register) {
                window.TelephonyCore.register().catch(() => {});
            }
            return;
        }

        phaseSet(ctx, 'timeout');
        loadingSet(ctx, false);
        state.inflight = false;
        window.Alpine.store('toast').warning('VICIdial session did not confirm. Try Login again.');
    } catch (e) {
        cancelVerify(ctx);
        phaseSet(ctx, 'failed');
        loadingSet(ctx, false);
        state.inflight = false;
        window.Alpine.store('toast').error(e.response?.data?.message || 'VICIdial verify failed.');
    }
}

function getFrame() {
    return document.getElementById('vici-session-frame');
}

function clearFrame() {
    const frame = getFrame();
    if (!frame) return;
    frame.onload = null;
    frame.onerror = null;
    frame.src = 'about:blank';
}

/**
 * Reload the hidden iframe from a stored URL when the server session is still login_pending
 * (e.g. after full page refresh mid-login). Avoids a second POST /session/login.
 */
async function maybeReconnectPending(localSession, campaign, ctx = null) {
    const effectiveCampaign = getCampaign(campaign);
    if (state.inflight) {
        return false;
    }
    if (window.Alpine.store('vicidial').loggedIn) {
        return false;
    }
    if (!localSession || localSession.session_status !== 'login_pending') {
        return false;
    }

    let url = null;
    try {
        const res = await window.axios.get('/api/vicidial/session/iframe-url', {
            params: { campaign: effectiveCampaign },
        });
        if (res.data?.success && res.data?.iframe_url) {
            url = res.data.iframe_url;
        }
    } catch (_) {
        // Fall back to stored URL below.
    }
    if (!url) {
        url = localSession.last_iframe_url;
    }
    if (!url || typeof url !== 'string') {
        return false;
    }

    state.inflight = true;
    cancelVerify(ctx);
    phaseSet(ctx, 'iframe_loading');
    loadingSet(ctx, true);

    const frame = getFrame();
    if (!frame) {
        phaseSet(ctx, 'failed');
        loadingSet(ctx, false);
        state.inflight = false;
        window.Alpine.store('toast').error('Hidden VICIdial session frame is missing.');
        return false;
    }

    frame.onload = () => {
        if (isIframeAgentApiOnly()) {
            verifyOnceAfterIframeLoad(effectiveCampaign, ctx).catch(() => {});
        } else {
            phaseSet(ctx, 'syncing');
            state.verifyCount = 0;
            if (ctx?.vici) {
                ctx.vici._verifyPollCount = 0;
            }
            pollVerify(effectiveCampaign, ctx, DEFAULT_VERIFY_MAX).catch(() => {});
        }
    };
    frame.onerror = () => {
        cancelVerify(ctx);
        phaseSet(ctx, 'failed');
        loadingSet(ctx, false);
        state.inflight = false;
        window.Alpine.store('toast').error('Hidden VICIdial session frame failed to load. Check VICIdial URL configuration.');
    };
    frame.src = url;

    state.verifyTimeout = setTimeout(() => {
        const phase = ctx?.vici?.phase ?? '';
        if (phase === 'iframe_loading' || phase === 'syncing' || phase === 'requesting') {
            cancelVerify(ctx);
            phaseSet(ctx, 'timeout');
            loadingSet(ctx, false);
            window.Alpine.store('toast').warning(
                'VICIdial session timed out. Check your phone credentials and try again.'
            );
            window.Alpine.store('vicidial').loggedIn = false;
            state.inflight = false;
        }
    }, DEFAULT_TIMEOUT_MS);

    return true;
}

async function login({
    campaign = null,
    phoneLogin = null,
    phonePass = null,
    blended = true,
    ingroups = [],
    ctx = null,
    maxAttempts = DEFAULT_VERIFY_MAX,
} = {}) {
    const effectiveCampaign = getCampaign(campaign);
    if (state.inflight) return false;

    state.inflight = true;
    cancelVerify(ctx);
    phaseSet(ctx, 'requesting');
    loadingSet(ctx, true);

    try {
        const res = await window.axios.post('/api/vicidial/session/login', {
            campaign: effectiveCampaign,
            phone_login: phoneLogin || null,
            phone_pass: phonePass || null,
            blended: Boolean(blended),
            ingroups: Array.isArray(ingroups) ? ingroups : [],
        });

        const iframeUrl = res.data?.iframe_url;
        if (!iframeUrl) {
            phaseSet(ctx, 'failed');
            loadingSet(ctx, false);
            state.inflight = false;
            window.Alpine.store('toast').error(
                res.data?.message || 'Could not build VICIdial login URL. Set a phone extension first.'
            );
            return false;
        }

        const frame = getFrame();
        if (!frame) {
            phaseSet(ctx, 'failed');
            loadingSet(ctx, false);
            state.inflight = false;
            window.Alpine.store('toast').error('Hidden VICIdial session frame is missing.');
            return false;
        }

        phaseSet(ctx, 'iframe_loading');
        frame.onload = () => {
            if (isIframeAgentApiOnly()) {
                verifyOnceAfterIframeLoad(effectiveCampaign, ctx).catch(() => {});
            } else {
                phaseSet(ctx, 'syncing');
                state.verifyCount = 0;
                if (ctx?.vici) {
                    ctx.vici._verifyPollCount = 0;
                }
                pollVerify(effectiveCampaign, ctx, maxAttempts).catch(() => {});
            }
        };
        frame.onerror = () => {
            cancelVerify(ctx);
            phaseSet(ctx, 'failed');
            loadingSet(ctx, false);
            state.inflight = false;
            window.Alpine.store('toast').error('Hidden VICIdial session frame failed to load. Check VICIdial URL configuration.');
        };
        frame.src = iframeUrl;

        state.verifyTimeout = setTimeout(() => {
            const phase = ctx?.vici?.phase ?? '';
            if (phase === 'iframe_loading' || phase === 'syncing' || phase === 'requesting') {
                cancelVerify(ctx);
                phaseSet(ctx, 'timeout');
                loadingSet(ctx, false);
                window.Alpine.store('toast').warning('VICIdial session timed out. Check your phone credentials and try again.');
                window.Alpine.store('vicidial').loggedIn = false;
                state.inflight = false;
            }
        }, DEFAULT_TIMEOUT_MS);

        return true;
    } catch (e) {
        phaseSet(ctx, 'failed');
        loadingSet(ctx, false);
        state.inflight = false;
        window.Alpine.store('toast').error(e.response?.data?.message || 'VICIdial login request failed.');
        return false;
    }
}

async function pause(value, campaign = null, ctx = null) {
    const effectiveCampaign = getCampaign(campaign);
    loadingSet(ctx, true);
    try {
        await window.axios.post('/api/vicidial/session/pause', {
            campaign: effectiveCampaign,
            value,
        });
        window.Alpine.store('toast').info(value === 'PAUSE' ? 'Agent paused.' : 'Agent resumed.');
        await syncStatus(effectiveCampaign, ctx);
        return true;
    } catch (e) {
        window.Alpine.store('toast').error(e.response?.data?.message || 'Unable to change pause state.');
        return false;
    } finally {
        loadingSet(ctx, false);
    }
}

async function setPauseCode(pauseCode, campaign = null, ctx = null) {
    const effectiveCampaign = getCampaign(campaign);
    if (!pauseCode) return false;

    try {
        await window.axios.post('/api/vicidial/session/pause-code', {
            campaign: effectiveCampaign,
            pause_code: pauseCode,
        });
        window.Alpine.store('toast').success('Pause code set.');
        await syncStatus(effectiveCampaign, ctx);
        return true;
    } catch (e) {
        window.Alpine.store('toast').error(e.response?.data?.message || 'Unable to set pause code.');
        return false;
    }
}

async function logout(campaign = null, ctx = null) {
    const effectiveCampaign = getCampaign(campaign);
    cancelVerify(ctx);
    loadingSet(ctx, true);

    try {
        await window.axios.post('/api/vicidial/session/logout', { campaign: effectiveCampaign });
        phaseSet(ctx, 'idle');
        clearFrame();
        if (window.TelephonyCore?.destroy) {
            window.TelephonyCore.destroy().catch(() => {});
        }
        window.Alpine.store('toast').info('VICIdial session logged out.');
        await syncStatus(effectiveCampaign, ctx);
        return true;
    } catch (e) {
        window.Alpine.store('toast').error(e.response?.data?.message || 'VICIdial logout failed.');
        return false;
    } finally {
        loadingSet(ctx, false);
        state.inflight = false;
    }
}

async function updateIngroups(action, ingroups, blended = true, campaign = null, ctx = null) {
    const effectiveCampaign = getCampaign(campaign);
    try {
        await window.axios.post('/api/vicidial/session/ingroups', {
            campaign: effectiveCampaign,
            action,
            blended: Boolean(blended),
            ingroups: Array.isArray(ingroups) ? ingroups : [],
        });
        window.Alpine.store('toast').success('In-group settings updated.');
        await syncStatus(effectiveCampaign, ctx);
        return true;
    } catch (e) {
        window.Alpine.store('toast').error(e.response?.data?.message || 'Unable to update in-groups.');
        return false;
    }
}

const VicidialSession = {
    login,
    maybeReconnectPending,
    pause,
    logout,
    setPauseCode,
    syncStatus,
    updateIngroups,
    cancelVerify,
    clearFrame,
    get inflight() {
        return state.inflight;
    },
};

window.VicidialSession = VicidialSession;

export default VicidialSession;
