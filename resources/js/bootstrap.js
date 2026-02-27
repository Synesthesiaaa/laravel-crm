import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Read CSRF token from meta tag
const tokenMeta = document.querySelector('meta[name="csrf-token"]');
if (tokenMeta) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = tokenMeta.getAttribute('content');
}

// SessionStorage cache for GET requests that don't change frequently
const CACHED_ROUTES = ['/api/disposition-codes'];
const CACHE_TTL_MS  = 5 * 60 * 1000; // 5 minutes

window.axios.interceptors.request.use((config) => {
    if (config.method === 'get' && CACHED_ROUTES.some(r => config.url?.includes(r))) {
        const cacheKey = 'axios_cache_' + config.url;
        try {
            const cached = JSON.parse(sessionStorage.getItem(cacheKey) ?? 'null');
            if (cached && Date.now() - cached.ts < CACHE_TTL_MS) {
                config._cachedData = cached.data;
            }
        } catch {}
    }
    return config;
});

window.axios.interceptors.response.use((response) => {
    const url = response.config?.url ?? '';
    if (response.config?.method === 'get' && CACHED_ROUTES.some(r => url.includes(r))) {
        const cacheKey = 'axios_cache_' + url;
        try {
            sessionStorage.setItem(cacheKey, JSON.stringify({ ts: Date.now(), data: response.data }));
        } catch {}
    }
    return response;
}, (error) => Promise.reject(error));

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
