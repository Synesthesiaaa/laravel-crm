export function resolveEchoConfiguration(env = {}) {
    const driver = String(env.VITE_BROADCAST_DRIVER || 'reverb').trim().toLowerCase();
    if (driver !== 'reverb' && driver !== 'pusher') {
        return { driver, enabled: false, reason: 'unsupported-driver', config: null };
    }

    const key = String(driver === 'reverb'
        ? env.VITE_REVERB_APP_KEY || ''
        : env.VITE_PUSHER_APP_KEY || '').trim();
    if (!key) {
        return { driver, enabled: false, reason: 'missing-key', config: null };
    }

    const config = driver === 'reverb'
        ? {
            broadcaster: 'reverb',
            key,
            wsHost: env.VITE_REVERB_HOST || '127.0.0.1',
            wsPort: parseInt(env.VITE_REVERB_PORT || '6001', 10),
            wssPort: parseInt(env.VITE_REVERB_PORT || '6001', 10),
            forceTLS: (env.VITE_REVERB_SCHEME || 'http') === 'https',
            enabledTransports: ['ws', 'wss'],
        }
        : {
            broadcaster: 'pusher',
            key,
            cluster: env.VITE_PUSHER_APP_CLUSTER || 'mt1',
            forceTLS: true,
        };

    return { driver, key, enabled: true, reason: null, config };
}
