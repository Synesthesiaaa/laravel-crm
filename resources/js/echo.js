/**
 * Laravel Echo bootstrap for real-time telephony events.
 * Initializes only when a valid broadcast driver (reverb/pusher) is configured.
 * Falls back to polling when broadcasting is disabled.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import TelephonyLogger from './telephony-logger';

/** Tear down the last subscribeAgentChannel() so layout + agent screen do not stack duplicate listeners. */
let _teardownAgentChannel = null;
/** Skip redundant subscribe when userId + handler slots match the active subscription (duplicate inits). */
let _agentChannelSig = null;
let _teardownUserNotifications = null;
let _userNotificationsSig = null;
let _teardownDashboardChannel = null;
let _dashboardChannelSig = null;
let _teardownActivityLogChannel = null;

const key = import.meta.env.VITE_REVERB_APP_KEY || import.meta.env.VITE_PUSHER_APP_KEY;
const broadcaster = import.meta.env.VITE_BROADCAST_DRIVER || 'reverb';

export const isBroadcastEnabled = () => !!key;

function resolveBroadcastAuthEndpoint() {
    const baseUrl = document.querySelector('meta[name="crm-base-url"]')?.getAttribute('content')?.trim();
    if (baseUrl) {
        return `${baseUrl}/broadcasting/auth`;
    }

    const currentPath = window.location.pathname;
    const indexPhpPosition = currentPath.indexOf('/index.php');
    if (indexPhpPosition >= 0) {
        return `${currentPath.slice(0, indexPhpPosition + '/index.php'.length)}/broadcasting/auth`;
    }

    return '/broadcasting/auth';
}

export function isEchoConnected() {
    const connection = window.Echo?.connector?.pusher?.connection;
    if (connection?.state) {
        return connection.state === 'connected';
    }

    return window.Alpine?.store?.('ws')?.state === 'connected';
}

export function initEcho() {
    if (!key) {
        TelephonyLogger.warn('TelephonyEcho', 'Broadcast key missing; Echo disabled');
        return null;
    }

    if (window.Echo) return window.Echo;

    window.Pusher = Pusher;

    const useReverb = broadcaster === 'reverb' || !!key;
    const baseConfig = {
        key,
        authEndpoint: resolveBroadcastAuthEndpoint(),
    };

    const config = useReverb
        ? {
            ...baseConfig,
            broadcaster: 'reverb',
            wsHost: import.meta.env.VITE_REVERB_HOST || '127.0.0.1',
            wsPort: parseInt(import.meta.env.VITE_REVERB_PORT || '6001', 10),
            wssPort: parseInt(import.meta.env.VITE_REVERB_PORT || '6001', 10),
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
            enabledTransports: ['ws', 'wss'],
        }
        : {
            ...baseConfig,
            broadcaster: 'pusher',
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
            forceTLS: true,
        };

    window.Echo = new Echo(config);
    TelephonyLogger.info('TelephonyEcho', 'Echo initialized', {
        broadcaster: config.broadcaster,
        host: config.wsHost || null,
        port: config.wsPort || null,
    });

    monitorConnectionState();

    return window.Echo;
}

/**
 * Monitor WebSocket connection state and surface it via Alpine store.
 */
function monitorConnectionState() {
    if (!window.Echo?.connector?.pusher?.connection) return;

    const conn = window.Echo.connector.pusher.connection;
    const update = (state) => {
        const store = window.Alpine?.store?.('ws');
        if (store) store.state = state;
    };

    conn.bind('connected',     () => update('connected'));
    conn.bind('connecting',    () => update('connecting'));
    conn.bind('disconnected',  () => update('disconnected'));
    conn.bind('unavailable',   () => update('unavailable'));
    conn.bind('failed',        () => update('failed'));

    if (conn.state) update(conn.state);
}

/**
 * Subscribe to agent's private channel for all telephony push events.
 * @param {number} userId
 * @param {object} handlers - { onCallStateChanged, onVicidialEvent, onInboundCall }
 */
export function subscribeAgentChannel(userId, onCallStateChanged, onVicidialEvent, onInboundCall) {
    if (!window.Echo || !userId) {
        TelephonyLogger.warn('TelephonyEcho', 'Agent channel subscription skipped', { has_echo: !!window.Echo, user_id: userId });
        return () => {};
    }

    const sig = `${userId}|${onVicidialEvent ? 1 : 0}|${onInboundCall ? 1 : 0}`;
    if (typeof _teardownAgentChannel === 'function' && _agentChannelSig === sig) {
        TelephonyLogger.debug('TelephonyEcho', 'Agent channel subscription unchanged (deduped)', { user_id: userId });
        return _teardownAgentChannel;
    }

    if (typeof _teardownAgentChannel === 'function') {
        try {
            _teardownAgentChannel();
        } catch (_) {}
        _teardownAgentChannel = null;
        _agentChannelSig = null;
    }

    const channel = window.Echo.private(`App.Models.User.${userId}`);
    channel.listen('.call.state.changed', onCallStateChanged);

    if (onVicidialEvent) {
        channel.listen('.vicidial.agent.event', onVicidialEvent);
    }
    if (onInboundCall) {
        channel.listen('.inbound.call.received', onInboundCall);
    }

    TelephonyLogger.info('TelephonyEcho', 'Subscribed to agent channel', { user_id: userId });

    _agentChannelSig = sig;

    const teardown = () => {
        channel.stopListening('.call.state.changed');
        channel.stopListening('.vicidial.agent.event');
        channel.stopListening('.inbound.call.received');
        if (_teardownAgentChannel === teardown) {
            _teardownAgentChannel = null;
            _agentChannelSig = null;
        }
    };
    _teardownAgentChannel = teardown;

    return teardown;
}

export function subscribeUserNotifications(userId, handler) {
    if (!window.Echo || !userId || typeof handler !== 'function') {
        TelephonyLogger.warn('TelephonyEcho', 'User notification subscription skipped', { has_echo: !!window.Echo, user_id: userId });
        return () => {};
    }

    const sig = `${userId}`;
    if (typeof _teardownUserNotifications === 'function' && _userNotificationsSig === sig) {
        TelephonyLogger.debug('TelephonyEcho', 'User notification subscription unchanged (deduped)', { user_id: userId });
        return _teardownUserNotifications;
    }

    if (typeof _teardownUserNotifications === 'function') {
        try {
            _teardownUserNotifications();
        } catch (_) {}
        _teardownUserNotifications = null;
        _userNotificationsSig = null;
    }

    const channel = window.Echo.private(`App.Models.User.${userId}`);
    channel.notification(handler);

    TelephonyLogger.info('TelephonyEcho', 'Subscribed to user notifications', { user_id: userId });

    _userNotificationsSig = sig;

    const teardown = () => {
        if (typeof channel.stopListeningForNotification === 'function') {
            channel.stopListeningForNotification(handler);
        }
        if (_teardownUserNotifications === teardown) {
            _teardownUserNotifications = null;
            _userNotificationsSig = null;
        }
    };
    _teardownUserNotifications = teardown;

    return teardown;
}

/**
 * Subscribe to supervisor channel for telephony and disposition updates.
 */
export function subscribeSupervisorChannel(onCallStateChanged, onDispositionSaved, onTelephonyEventLogged) {
    if (!window.Echo) {
        TelephonyLogger.warn('TelephonyEcho', 'Supervisor channel subscription skipped: Echo not initialized');
        return () => {};
    }

    const channel = window.Echo.private('telephony.supervisor');
    channel.listen('.call.state.changed', onCallStateChanged);
    if (onDispositionSaved) {
        channel.listen('.disposition.saved', onDispositionSaved);
    }
    if (onTelephonyEventLogged) {
        channel.listen('.telephony.event.logged', onTelephonyEventLogged);
    }
    TelephonyLogger.info('TelephonyEcho', 'Subscribed to supervisor channel');

    return () => {
        channel.stopListening('.call.state.changed');
        channel.stopListening('.disposition.saved');
        channel.stopListening('.telephony.event.logged');
    };
}

/**
 * Subscribe to campaign-scoped dashboard data invalidations.
 * @param {string} campaignCode
 * @param {(event: object) => void} handler
 * @returns {() => void}
 */
export function subscribeDashboardChannel(campaignCode, handler) {
    if (!window.Echo || !campaignCode || typeof handler !== 'function') {
        TelephonyLogger.warn('TelephonyEcho', 'Dashboard channel subscription skipped', {
            has_echo: !!window.Echo,
            campaign: campaignCode,
        });

        return () => {};
    }

    const sig = String(campaignCode);
    if (typeof _teardownDashboardChannel === 'function' && _dashboardChannelSig === sig) {
        return _teardownDashboardChannel;
    }

    if (typeof _teardownDashboardChannel === 'function') {
        try {
            _teardownDashboardChannel();
        } catch (_) {}
        _teardownDashboardChannel = null;
        _dashboardChannelSig = null;
    }

    const channel = window.Echo.private(`dashboard.${campaignCode}`);
    channel.listen('.dashboard.data.updated', handler);
    channel.listen('.dashboard.layout.updated', handler);
    TelephonyLogger.info('TelephonyEcho', 'Subscribed to dashboard data channel', { campaign: campaignCode });

    const teardown = () => {
        channel.stopListening('.dashboard.data.updated');
        channel.stopListening('.dashboard.layout.updated');
        if (_teardownDashboardChannel === teardown) {
            _teardownDashboardChannel = null;
            _dashboardChannelSig = null;
        }
    };

    _dashboardChannelSig = sig;
    _teardownDashboardChannel = teardown;

    return teardown;
}

/**
 * Subscribe to the Super Admin activity stream.
 * @param {(entry: object) => void} handler
 * @returns {() => void}
 */
export function subscribeActivityLog(handler) {
    if (!window.Echo || typeof handler !== 'function') {
        TelephonyLogger.warn('TelephonyEcho', 'Activity log subscription skipped', { has_echo: !!window.Echo });

        return () => {};
    }

    if (typeof _teardownActivityLogChannel === 'function') {
        _teardownActivityLogChannel();
    }

    const channel = window.Echo.private('activity-log');
    channel.listen('.activity.log.created', (payload) => handler(payload?.entry || payload));
    TelephonyLogger.info('TelephonyEcho', 'Subscribed to activity log channel');

    const teardown = () => {
        channel.stopListening('.activity.log.created');
        if (_teardownActivityLogChannel === teardown) _teardownActivityLogChannel = null;
    };
    _teardownActivityLogChannel = teardown;

    return teardown;
}

/**
 * Join the agents presence channel for real-time online/offline tracking.
 * @param {object} handlers - { onHere, onJoining, onLeaving }
 */
export function joinAgentsPresence(handlers = {}) {
    if (!window.Echo) return () => {};

    const channel = window.Echo.join('agents.online');
    if (handlers.onHere)    channel.here(handlers.onHere);
    if (handlers.onJoining) channel.joining(handlers.onJoining);
    if (handlers.onLeaving) channel.leaving(handlers.onLeaving);

    TelephonyLogger.info('TelephonyEcho', 'Joined agents presence channel');
    return () => window.Echo.leave('agents.online');
}

// Expose for inline scripts (agent/supervisor blade)
window.TelephonyEcho = {
    initEcho,
    isEchoConnected,
    subscribeAgentChannel,
    subscribeUserNotifications,
    subscribeSupervisorChannel,
    subscribeDashboardChannel,
    subscribeActivityLog,
    joinAgentsPresence,
    isBroadcastEnabled,
};

window.dispatchEvent(new CustomEvent('telephony-echo:ready'));
