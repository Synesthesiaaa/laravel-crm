import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../resources/js/dashboard.js', import.meta.url), 'utf8');

function setup() {
    const observers = [];
    const registrations = [];
    const summary = {};
    const activity = {};
    const main = { contains: () => true };
    const listeners = new Map();
    const document = {
        hidden: false,
        activeElement: null,
        documentElement: { getAttribute: () => 'light' },
        getElementById: (id) => ({
            'main-layout': main,
            'chart-dashboard-summary': summary,
        })[id] || ({
            'chart-daily-activity': {},
            'chart-weekly-activity': {},
            'chart-monthly-activity': {},
        })[id],
        querySelector: (selector) => selector === '[data-dashboard-section="activity"]' ? activity : null,
        addEventListener: (type, handler) => listeners.set(`${type}:${listeners.size}`, handler),
        removeEventListener: () => {},
    };
    const Observer = class {
        constructor(callback) {
            this.callback = callback;
            this.disconnected = false;
            observers.push(this);
        }

        observe() {}

        disconnect() {
            this.disconnected = true;
        }
    };
    const window = {
        location: { href: 'http://crm.test/dashboard', pathname: '/dashboard', origin: 'http://crm.test' },
        IntersectionObserver: Observer,
        crmSoftNav: {
            currentScope: () => '/dashboard',
            register: (scope, handlers) => {
                registrations.push({ scope, handlers });

                return () => {};
            },
            unregister: () => {},
        },
        addEventListener: () => {},
        removeEventListener: () => {},
        setInterval: () => 1,
        clearInterval: () => {},
        clearTimeout: () => {},
    };
    const context = vm.createContext({ window, document, URL, IntersectionObserver: Observer, console });

    vm.runInContext(source, context);

    return { context, observers, registrations };
}

test('dashboard rehydration keeps one observer per lazy chart region', () => {
    const app = setup();

    vm.runInContext("window.crmDashboard.init({ campaign: 'mbsales', dashboardPath: '/dashboard', activityEndpoint: '/api/dashboard/activity' })", app.context);
    const registration = app.registrations[0];

    assert.equal(app.observers.length, 2);
    registration.handlers.afterSwap();
    registration.handlers.afterSwap();

    assert.equal(app.observers.length, 2);
    assert.equal(app.observers.filter((observer) => observer.disconnected).length, 2);
});
