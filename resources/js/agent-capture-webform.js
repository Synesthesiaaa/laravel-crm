window.agentCaptureWebform = function agentCaptureWebform(initial = {}) {
    const visibilityState = window.formVisibility({ autosave: false });

    return {
        ...visibilityState,
        campaignCode: String(initial.campaignCode ?? ''),
        leadId: String(initial.leadId ?? ''),
        phoneNumber: String(initial.phoneNumber ?? ''),
        saving: false,
        feedback: {
            message: '',
            success: false,
        },
        errors: {},

        init(seed = {}) {
            visibilityState.init.call(this, seed);
        },

        visibleCaptureFields(form) {
            return Array.from(form.querySelectorAll('[data-capture-field]'))
                .filter((element) => element.style.display !== 'none')
                .map((element) => element.querySelector('[name]')?.name)
                .filter((name) => Boolean(name));
        },

        async submitCapture() {
            const form = this.getFormElement();
            if (!form || this.saving) {
                return;
            }

            this.saving = true;
            this.feedback = { message: '', success: false };
            this.errors = {};

            const values = this.collectFormValues(form);
            const visibleFields = this.visibleCaptureFields(form);
            const captureData = {};

            visibleFields.forEach((fieldKey) => {
                captureData[fieldKey] = values[fieldKey] ?? '';
            });

            try {
                await window.axios.post('/api/agent/capture', {
                    campaign_code: this.campaignCode,
                    lead_id: this.leadId || null,
                    phone_number: this.phoneNumber || null,
                    capture_data: captureData,
                    visible_fields: visibleFields,
                });
                this.feedback = { message: 'Capture saved successfully.', success: true };
                this.isDirty = false;
            } catch (error) {
                const responseErrors = error?.response?.data?.errors ?? {};
                this.errors = Object.fromEntries(
                    Object.entries(responseErrors).map(([key, messages]) => [
                        key.replace(/^capture_data\./, ''),
                        Array.isArray(messages) ? messages[0] : String(messages),
                    ]),
                );
                this.feedback = {
                    message: error?.response?.data?.message || 'Unable to save capture. Check the highlighted fields.',
                    success: false,
                };
            } finally {
                this.saving = false;
            }
        },
    };
};
