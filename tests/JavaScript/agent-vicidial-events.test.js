import assert from 'node:assert/strict';
import test from 'node:test';

import { shouldReleaseWrapupForVicidialDisposition } from '../../resources/js/agent-vicidial-events.js';

test('releases wrap-up when VICIdial disposes the current lead', () => {
    assert.equal(shouldReleaseWrapupForVicidialDisposition({
        event: 'dispo_set',
        extra: { lead_id: 321 },
    }, {
        leadId: '321',
        callState: 'wrapup',
        hasDispositionPending: true,
        dialBlocked: true,
    }), true);
});

test('does not release a newer lead for a stale VICIdial disposition event', () => {
    assert.equal(shouldReleaseWrapupForVicidialDisposition({
        event: 'dispo_set',
        extra: { lead_id: 321 },
    }, {
        leadId: '654',
        callState: 'connected',
        hasDispositionPending: false,
        dialBlocked: false,
    }), false);
});

test('releases pending wrap-up when VICIdial omits the lead id', () => {
    assert.equal(shouldReleaseWrapupForVicidialDisposition({
        event: 'dispo_set',
        extra: {},
    }, {
        leadId: '321',
        callState: 'wrapup',
        hasDispositionPending: true,
        dialBlocked: true,
    }), true);
});
