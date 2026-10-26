function getStorage() {
    try {
        return window.localStorage;
    } catch {
        return null;
    }
}

export function readRecentSearches() {
    try {
        const raw = getStorage()?.getItem('crm_recent_searches');
        const parsed = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed)
            ? parsed.filter((item) => typeof item === 'string').map((item) => item.trim()).filter(Boolean).slice(0, 6)
            : [];
    } catch {
        return [];
    }
}

export function saveRecentSearches(values) {
    try {
        getStorage()?.setItem('crm_recent_searches', JSON.stringify(
            Array.isArray(values) ? values.filter((item) => typeof item === 'string').slice(0, 6) : [],
        ));
    } catch {
        // Preferences must not prevent application startup or interaction.
    }
}

export function readSidebarCollapsed() {
    try {
        return getStorage()?.getItem('sidebar_collapsed') === 'true';
    } catch {
        return false;
    }
}

export function saveSidebarCollapsed(collapsed) {
    try {
        getStorage()?.setItem('sidebar_collapsed', collapsed ? 'true' : 'false');
    } catch {
        // Preferences must not prevent application startup or interaction.
    }
}
