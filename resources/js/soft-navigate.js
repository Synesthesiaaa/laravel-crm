/**
 * Intercept same-origin navigations and swap #main-layout inner HTML via fetch
 * so the phone widget iframe and WebRTC shell persist across pages.
 */
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function removeInjectedPageScripts() {
    document.querySelectorAll('script[data-soft-nav-injected]').forEach((el) => el.remove());
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

async function softNavigate(url, { push = true } = {}) {
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
    try {
        res = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });
    } catch (_) {
        window.location.href = url;
        return;
    }

    if (res.redirected || res.status === 401 || res.status === 403) {
        window.location.href = res.url || url;
        return;
    }

    if (!res.ok) {
        window.Alpine?.store('toast')?.error?.('Could not load page.');
        return;
    }

    const html = await res.text();
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');

    const nextMain = doc.getElementById('main-layout');
    if (!nextMain) {
        window.location.href = url;
        return;
    }

    try {
        if (typeof Alpine.destroyTree === 'function') {
            Alpine.destroyTree(mainLayout);
        }
    } catch (_) {}

    removeInjectedPageScripts();

    mainLayout.innerHTML = nextMain.innerHTML;

    const titleEl = doc.querySelector('title');
    if (titleEl?.textContent) {
        document.title = titleEl.textContent;
    }

    try {
        if (typeof Alpine.initTree === 'function') {
            Alpine.initTree(mainLayout);
        }
    } catch (e) {
        console.warn('[soft-navigate] Alpine.initTree failed', e);
    }

    executeScriptsAfterMarker(doc);

    if (push) {
        try {
            window.history.pushState({ softNav: true }, '', url);
        } catch (_) {}
    }

    window.dispatchEvent(new CustomEvent('soft-navigate', { detail: { url } }));

    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;
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

function initSoftNavigate() {
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
