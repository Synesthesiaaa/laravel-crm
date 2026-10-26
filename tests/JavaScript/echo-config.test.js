import assert from 'node:assert/strict';
import test from 'node:test';
import { resolveEchoConfiguration } from '../../resources/js/echo-config.js';

test('Echo chooses only the selected Reverb key and settings', () => {
    const settings = resolveEchoConfiguration({
        VITE_BROADCAST_DRIVER: 'reverb',
        VITE_REVERB_APP_KEY: 'reverb-key',
        VITE_PUSHER_APP_KEY: 'pusher-key',
        VITE_REVERB_HOST: 'events.example.test',
        VITE_REVERB_PORT: '7443',
        VITE_REVERB_SCHEME: 'https',
    });

    assert.equal(settings.enabled, true);
    assert.equal(settings.config.broadcaster, 'reverb');
    assert.equal(settings.config.key, 'reverb-key');
    assert.equal(settings.config.wsHost, 'events.example.test');
    assert.equal(settings.config.forceTLS, true);
});

test('Echo selects Pusher explicitly and does not fall back across driver keys', () => {
    const pusher = resolveEchoConfiguration({
        VITE_BROADCAST_DRIVER: 'pusher',
        VITE_REVERB_APP_KEY: 'reverb-key',
        VITE_PUSHER_APP_KEY: 'pusher-key',
        VITE_PUSHER_APP_CLUSTER: 'eu',
    });
    assert.equal(pusher.config.broadcaster, 'pusher');
    assert.equal(pusher.config.key, 'pusher-key');
    assert.equal(pusher.config.cluster, 'eu');

    assert.equal(resolveEchoConfiguration({
        VITE_BROADCAST_DRIVER: 'reverb',
        VITE_PUSHER_APP_KEY: 'pusher-key',
    }).reason, 'missing-key');
    assert.equal(resolveEchoConfiguration({
        VITE_BROADCAST_DRIVER: 'invalid',
        VITE_REVERB_APP_KEY: 'reverb-key',
    }).reason, 'unsupported-driver');
});
