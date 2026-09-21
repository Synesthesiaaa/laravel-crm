function normalizedLeadId(value) {
    return String(value ?? '').trim();
}

export function shouldReleaseWrapupForVicidialDisposition(payload, state = {}) {
    if (payload?.event !== 'dispo_set') {
        return false;
    }

    const eventLeadId = normalizedLeadId(payload?.extra?.lead_id);
    const currentLeadId = normalizedLeadId(state.leadId);

    if (eventLeadId && currentLeadId && eventLeadId !== currentLeadId) {
        return false;
    }

    if (eventLeadId && currentLeadId === eventLeadId) {
        return true;
    }

    return state.callState === 'wrapup'
        || state.hasDispositionPending === true
        || state.dialBlocked === true;
}
