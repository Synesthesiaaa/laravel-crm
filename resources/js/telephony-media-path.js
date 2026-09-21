const DEFAULT_MEDIA_PATH = 'sipjs';
const SIP_MEDIA_PATHS = ['sipjs', 'both'];

function normalizeMediaPath(value) {
    const mediaPath = String(value || DEFAULT_MEDIA_PATH).trim().toLowerCase();

    return mediaPath || DEFAULT_MEDIA_PATH;
}

export function currentMediaPath() {
    return normalizeMediaPath(window.__telephonyMediaPath);
}

export function shouldUseSipMedia() {
    return SIP_MEDIA_PATHS.includes(currentMediaPath());
}

export function shouldRegisterSip() {
    return shouldUseSipMedia();
}

export function shouldDestroySip() {
    return shouldUseSipMedia();
}

export function isDualMediaPath() {
    return currentMediaPath() === 'both';
}

window.TelephonyMediaPath = {
    current: currentMediaPath,
    shouldUseSipMedia,
    shouldRegisterSip,
    shouldDestroySip,
    isDual: isDualMediaPath,
};
