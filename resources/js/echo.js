/**
 * Laravel Echo bootstrap for real-time telephony events.
 * Initializes only when a valid broadcast driver (reverb/pusher) is configured.
 * Falls back to polling when broadcasting is disabled.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import TelephonyLogger from './telephony-logger';

const key = import.meta.env.VITE_REVERB_APP_KEY || import.meta.env.VITE_PUSHER_APP_KEY;
const broadcaster = import.meta.env.VITE_BROADCAST_DRIVER || 'reverb';

export const isBroadcastEnabled = () => !!key;

export function initEcho() {
    if (!key) {
        TelephonyLogger.warn('TelephonyEcho', 'Broadcast key missing; Echo disabled');
        return null;
    }

    if (window.Echo) return window.Echo;

    window.Pusher = Pusher;

    const useReverb = broadcaster === 'reverb' || !!key;
    const baseConfig = { key };

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

    const channel = window.Echo.private(`App.Models.User.${userId}`);
    channel.listen('.call.state.changed', onCallStateChanged);

    if (onVicidialEvent) {
        channel.listen('.vicidial.agent.event', onVicidialEvent);
    }
    if (onInboundCall) {
        channel.listen('.inbound.call.received', onInboundCall);
    }

    TelephonyLogger.info('TelephonyEcho', 'Subscribed to agent channel', { user_id: userId });

    return () => {
        channel.stopListening('.call.state.changed');
        channel.stopListening('.vicidial.agent.event');
        channel.stopListening('.inbound.call.received');
    };
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
    subscribeAgentChannel,
    subscribeSupervisorChannel,
    joinAgentsPresence,
    isBroadcastEnabled,
};
