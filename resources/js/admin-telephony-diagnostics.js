window.telephonyDiagnostics = function telephonyDiagnostics() {
    return {
        loading: false,
        checks: [],
        callUrlLinks: [],
        copiedLinkKey: null,

        async init() {
            await this.run();
        },

        async copyLink(link) {
            const text = link?.url || '';

            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.setAttribute('readonly', '');
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.focus();
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                }

                this.copiedLinkKey = link.key;
                window.setTimeout(() => {
                    if (this.copiedLinkKey === link.key) {
                        this.copiedLinkKey = null;
                    }
                }, 1200);
                window.Alpine?.store('toast')?.success(`${link.label} copied to clipboard.`);
            } catch {
                window.Alpine?.store('toast')?.error('Could not copy the Vicidial URL.');
            }
        },

        async run() {
            this.loading = true;
            this.checks = [];

            try {
                const response = await window.axios.post('/admin/configuration/telephony-diagnostics');
                this.checks = response.data?.checks ?? [];
                this.callUrlLinks = response.data?.call_url_links ?? [];

                if (response.data?.ok) {
                    window.Alpine?.store('toast')?.success('All telephony checks passed.');
                } else {
                    window.Alpine?.store('toast')?.warning('Some checks need attention. See details below.');
                }
            } catch (error) {
                window.Alpine?.store('toast')?.error(error.response?.data?.message || 'Diagnostics request failed.');
            } finally {
                this.loading = false;
            }
        },
    };
};
