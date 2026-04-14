import { ref, onMounted, onUnmounted, readonly } from 'vue';

/**
 * Bridge Alpine global stores into Vue (telephony shell keeps stores in Alpine).
 */
export function useAlpineStore(storeName) {
    const state = ref({});
    let intervalId;

    const pull = () => {
        const store = window.Alpine?.store?.(storeName);
        if (!store) {
            return;
        }
        try {
            state.value = JSON.parse(JSON.stringify(store));
        } catch {
            state.value = { ...store };
        }
    };

    onMounted(() => {
        pull();
        intervalId = window.setInterval(pull, 400);
    });

    onUnmounted(() => {
        if (intervalId) {
            window.clearInterval(intervalId);
        }
    });

    const dispatch = (method, ...args) => {
        const store = window.Alpine?.store?.(storeName);
        if (store && typeof store[method] === 'function') {
            return store[method](...args);
        }
        return undefined;
    };

    return { state: readonly(state), dispatch, refresh: pull };
}
