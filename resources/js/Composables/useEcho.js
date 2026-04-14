import { onUnmounted } from 'vue';

/**
 * Subscribe to a private Echo channel; cleans up on unmount.
 * @param {string} channelName - e.g. 'telephony.supervisor' (no private- prefix)
 * @param {Record<string, (payload: unknown) => void>} events - keys like '.call.state.changed'
 */
export function usePrivateChannel(channelName, events = {}) {
    const cleanups = [];

    if (!window.Echo) {
        return () => {};
    }

    const channel = window.Echo.private(channelName);
    for (const [eventName, handler] of Object.entries(events)) {
        if (typeof handler !== 'function') {
            continue;
        }
        channel.listen(eventName, handler);
        cleanups.push(() => channel.stopListening(eventName));
    }

    onUnmounted(() => {
        cleanups.forEach((fn) => fn());
    });

    return () => cleanups.forEach((fn) => fn());
}
