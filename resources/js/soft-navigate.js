/**
 * Intercept same-origin navigations and swap #main-layout inner HTML via fetch
 * so the phone widget iframe and WebRTC shell persist across pages.
 */
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

const softNavScopes = new Map();
let currentSoftNavScope = '';
let currentSoftNavPhase = 'initial';
let navigationSequence = 0;
let navigationController = null;
let foregroundNavigationPending = false;

function normalizeSoftNavScope(value) {
    return String(value || '').trim() || 'default';
}

function getSoftNavScopeFromUrl(url) {
    try {
        return normalizeSoftNavScope(new URL(url, window.location.origin).pathname);
    } catch {
        return normalizeSoftNavScope(window.location.pathname);
    }
}

function runSoftNavHandler(scope, phase, detail) {
    const handlers = softNavScopes.get(scope);
    const handler = handlers?.[phase];
    if (typeof handler !== 'function') {
        return;
    }

    try {
        handler(detail);
    } catch (error) {
        console.warn(`[soft-navigate] ${phase} handler failed for ${scope}`, error);
    }
}

window.crmSoftNav = {
    register(scope, handlers = {}) {
        const key = normalizeSoftNavScope(scope);
        softNavScopes.set(key, {
            beforeSwap: typeof handlers.beforeSwap === 'function' ? handlers.beforeSwap : null,
            afterSwap: typeof handlers.afterSwap === 'function' ? handlers.afterSwap : null,
        });

        return () => {
            softNavScopes.delete(key);
        };
    },
    unregister(scope) {
        softNavScopes.delete(normalizeSoftNavScope(scope));
    },
    currentScope() {
        return currentSoftNavScope || normalizeSoftNavScope(window.location.pathname);
    },
    currentPhase() {
        return currentSoftNavPhase;
    },
    isRehydrating() {
        return currentSoftNavPhase === 'rehydrating';
    },
    refresh({ shouldDefer = () => false } = {}) {
        return softNavigate(window.location.href, { push: false, background: true, shouldDefer });
    },
    run(scope, phase, detail = {}) {
        runSoftNavHandler(normalizeSoftNavScope(scope), phase, detail);
    },
};

function removeInjectedPageScripts() {
    document.querySelectorAll('script[data-soft-nav-injected]').forEach((el) => el.remove());
}

/**
 * Normalize href for matching (sidebar links may be absolute or root-relative).
 */
function normalizeNavHref(href) {
    if (!href) {
        return '';
    }
    try {
        const u = new URL(href, window.location.origin);

        return u.pathname + u.search;
    } catch {
        return href;
    }
}

/**
 * Soft-nav only swaps #main-layout; the sidebar stays from the first paint.
 * Copy which `.sidebar-item` links are `active` from the fetched full HTML
 * so the magenta current-page state matches the new route.
 */
function syncSidebarActiveFromFetchedDocument(doc) {
    const nextSidebar = doc.getElementById('sidebar');
    const curSidebar = document.getElementById('sidebar');
    if (!nextSidebar || !curSidebar) {
        return;
    }

    /** @type {Map<string, boolean>} */
    const activeByHref = new Map();
    nextSidebar.querySelectorAll('a.sidebar-item').forEach((a) => {
        const href = a.getAttribute('href');
        if (!href) {
            return;
        }
        activeByHref.set(normalizeNavHref(href), a.classList.contains('active'));
    });

    curSidebar.querySelectorAll('a.sidebar-item').forEach((a) => {
        const href = a.getAttribute('href');
        if (!href) {
            return;
        }
        const key = normalizeNavHref(href);
        if (!activeByHref.has(key)) {
            a.classList.remove('active');

            return;
        }
        a.classList.toggle('active', activeByHref.get(key) === true);
    });
}

function syncCampaignStateFromFetchedDocument(doc) {
    const nextBody = doc?.body;
    const nextCampaign = String(nextBody?.dataset?.campaign || '').trim();
    if (!nextCampaign || !document.body) {
        return null;
    }

    const currentCampaign = String(document.body.dataset.campaign || '').trim();
    const currentTelephonyCampaign = String(document.body.dataset.telephonyCampaign || '').trim();
    const campaignName = String(nextBody.dataset.campaignName || nextCampaign).trim();

    document.body.dataset.campaign = nextCampaign;
    document.body.dataset.campaignName = campaignName;
    document.body.dataset.telephonyCampaign = nextCampaign;

    if (nextCampaign === currentCampaign && nextCampaign === currentTelephonyCampaign) {
        return null;
    }

    return {
        campaign: nextCampaign,
        campaignName,
    };
}

function executeScriptsAfterMarker(doc) {
    const marker = doc.getElementById('soft-nav-scripts-marker');
    if (!marker) {
        return;
    }
    let el = marker.nextElementSibling;
    while (el) {
        const next = el.nextElementSibling;
        if (el.tagName === 'SCRIPT') {
            const s = document.createElement('script');
            s.setAttribute('data-soft-nav-injected', '1');
            if (el.src) {
                s.src = el.src;
                s.async = el.async;
            } else {
                s.textContent = el.textContent;
            }
            document.body.appendChild(s);
        }
        el = next;
    }
}

async function softNavigate(url, { push = true, background = false, shouldDefer = () => false } = {}) {
    if (background && (foregroundNavigationPending || shouldDefer())) {
        return false;
    }

    const sequence = ++navigationSequence;
    navigationController?.abort();
    const controller = new AbortController();
    navigationController = controller;
    foregroundNavigationPending = !background;
    const mainLayout = document.getElementById('main-layout');
    if (!mainLayout) {
        window.location.href = url;
        return;
    }

    const Alpine = window.Alpine;
    if (!Alpine) {
        window.location.href = url;
        return;
    }

    let res;
    let html;
    const timeout = window.setTimeout(() => controller.abort(), 15000);
    try {
        res = await fetch(url, {
            signal: controller.signal,
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });
        html = await res.text();
    } catch (_) {
        if (sequence === navigationSequence && !background) {
            window.Alpine?.store('toast')?.error?.('Could not load page. Please try again.');
        }
        return null;
    } finally {
        window.clearTimeout(timeout);
        if (sequence === navigationSequence) {
            foregroundNavigationPending = false;
        }
    }

    if (sequence !== navigationSequence || (background && shouldDefer())) {
        return false;
    }

    if (res.redirected || res.status === 401 || res.status === 403) {
        window.location.href = res.url || url;
        return;
    }

    if (!res.ok) {
        window.Alpine?.store('toast')?.error?.('Could not load page.');
        return;
    }

    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');

    const nextMain = doc.getElementById('main-layout');
    if (!nextMain) {
        window.location.href = url;
        return;
    }

    const campaignChange = syncCampaignStateFromFetchedDocument(doc);

    const previousScope = currentSoftNavScope || normalizeSoftNavScope(window.location.pathname);
    const nextScope = getSoftNavScopeFromUrl(url);
    const scrollPosition = { x: window.scrollX, y: window.scrollY };
    const scrollContainers = background
        ? Array.from(mainLayout.querySelectorAll('[id]')).filter((element) => element.scrollTop || element.scrollLeft)
            .map((element) => ({ id: element.id, top: element.scrollTop, left: element.scrollLeft }))
        : [];

    Alpine.store('modal')?.hide?.();
    await Alpine.nextTick();
    if (sequence !== navigationSequence) {
        return false;
    }

    try {
        window.dispatchEvent(new CustomEvent('soft-navigate:before', {
            detail: { url, scope: previousScope },
        }));
        runSoftNavHandler(previousScope, 'beforeSwap', { url, scope: previousScope, nextScope });

        if (typeof Alpine.destroyTree === 'function') {
            Alpine.destroyTree(mainLayout);
        }
    } catch (_) {}

    window.crmSoftNav?.unregister?.(previousScope);

    removeInjectedPageScripts();

    mainLayout.innerHTML = nextMain.innerHTML;

    syncSidebarActiveFromFetchedDocument(doc);

    currentSoftNavScope = nextScope;
    currentSoftNavPhase = 'rehydrating';

    const titleEl = doc.querySelector('title');
    if (titleEl?.textContent) {
        document.title = titleEl.textContent;
    }

    // Run @stack('scripts') (e.g. window.agentScreen) before initTree so x-data
    // expressions resolve against defined component factories.
    executeScriptsAfterMarker(doc);

    try {
        if (typeof Alpine.initTree === 'function') {
            Alpine.initTree(mainLayout);
        }
    } catch (e) {
        console.warn('[soft-navigate] Alpine.initTree failed', e);
    }

    if (campaignChange) {
        window.dispatchEvent(new CustomEvent('crm-campaign-changed', { detail: campaignChange }));
    }

    runSoftNavHandler(nextScope, 'afterSwap', { url, scope: nextScope, previousScope });

    window.dispatchEvent(new CustomEvent('soft-navigate:after', {
        detail: { url, scope: nextScope, previousScope },
    }));

    const nextMainContent = mainLayout.querySelector('#main-content');
    if (!background && nextMainContent && typeof nextMainContent.focus === 'function') {
        nextMainContent.focus({ preventScroll: true });
    }

    currentSoftNavPhase = 'idle';

    if (push) {
        try {
            window.history.pushState({ softNav: true }, '', url);
        } catch (_) {}
    }

    window.dispatchEvent(new CustomEvent('soft-navigate', { detail: { url } }));

    if (background) {
        scrollContainers.forEach(({ id, top, left }) => {
            const element = document.getElementById(id);
            if (element) {
                element.scrollTop = top;
                element.scrollLeft = left;
            }
        });
        window.scrollTo(scrollPosition.x, scrollPosition.y);
    } else {
        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;
    }

    return true;
}

function shouldInterceptAnchor(anchor, event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return false;
    }
    if (anchor.hasAttribute('data-no-soft-nav')) {
        return false;
    }
    if (anchor.target && anchor.target !== '' && anchor.target !== '_self') {
        return false;
    }
    if (anchor.hasAttribute('download')) {
        return false;
    }
    const href = anchor.getAttribute('href');
    if (!href || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
        return false;
    }
    if (href === '#' || href.startsWith('#')) {
        return false;
    }

    let url;
    try {
        url = new URL(anchor.href, window.location.href);
    } catch (_) {
        return false;
    }
    if (url.origin !== window.location.origin) {
        return false;
    }
    if (url.pathname === window.location.pathname && url.search === window.location.search) {
        return false;
    }

    return true;
}

function getSoftNavFormUrl(form) {
    const action = form.getAttribute('action') || window.location.href;
    const url = new URL(action, window.location.href);
    const params = new URLSearchParams();

    new FormData(form).forEach((value, key) => {
        if (typeof value === 'string') {
            params.append(key, value);
        }
    });

    url.search = params.toString();

    return url;
}

function initSoftNavigate() {
    currentSoftNavScope = normalizeSoftNavScope(window.location.pathname);
    currentSoftNavPhase = 'idle';

    document.addEventListener(
        'click',
        (event) => {
            const anchor = event.target.closest?.('a');
            if (!anchor || !shouldInterceptAnchor(anchor, event)) {
                return;
            }
            event.preventDefault();
            softNavigate(anchor.href, { push: true });
        },
        true,
    );

    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target.closest?.('form[data-soft-nav]');
            const method = (form?.getAttribute('method') || 'get').toLowerCase();

            if (!form || method !== 'get' || event.defaultPrevented) {
                return;
            }

            let url;
            try {
                url = getSoftNavFormUrl(form);
            } catch (_) {
                return;
            }

            if (url.origin !== window.location.origin) {
                return;
            }

            event.preventDefault();
            softNavigate(url.href, { push: true });
        },
        true,
    );

    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.softNav === false) {
            return;
        }
        softNavigate(window.location.href, { push: false });
    });

    try {
        if (!window.history.state || window.history.state.softNav === undefined) {
            window.history.replaceState({ softNav: true }, '', window.location.href);
        }
    } catch (_) {}
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSoftNavigate);
} else {
    initSoftNavigate();
}
