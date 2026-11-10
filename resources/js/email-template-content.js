export class CompositionMode {
    constructor() {
        this.mode = 'source';
        this.version = 0;
    }

    async select(mode, prepareVisual, apply) {
        const version = ++this.version;
        const prepared = mode === 'visual' ? await prepareVisual() : null;
        if (version !== this.version) return false;
        this.mode = mode === 'visual' && prepared ? 'visual' : 'source';
        apply(this.mode, prepared);
        return true;
    }

    dispose() {
        this.version += 1;
    }
}

export function emailFramePolicy(hostnames) {
    const hosts = Array.isArray(hostnames) ? hostnames : [];
    const allowed = [...new Set(hosts.filter((host) => typeof host === 'string' && /^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/i.test(host)).map((host) => `https://${host.toLowerCase()}`))];
    return `default-src 'none'; style-src 'unsafe-inline'; img-src ${allowed.length ? allowed.join(' ') : "'none'"}; base-uri 'none'; form-action 'none'`;
}
