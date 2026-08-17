function splitValues(input) {
    if (Array.isArray(input)) {
        return input
            .flatMap((item) => splitValues(item))
            .filter((item, index, arr) => item !== '' && arr.indexOf(item) === index);
    }

    return String(input ?? '')
        .split(/[\r\n,]+/)
        .map((item) => item.trim())
        .filter((item) => item !== '');
}

function evaluateRule(rule, values) {
    if (!rule || typeof rule !== 'object') {
        return true;
    }

    const sourceField = String(rule.field ?? '').trim();
    const operator = String(rule.operator ?? '').trim();
    const targets = splitValues(rule.values ?? []);

    if (!sourceField || !operator || targets.length === 0) {
        return true;
    }

    const currentValue = values?.[sourceField];
    const currentValueString = String(currentValue ?? '').trim();

    if (Array.isArray(currentValue)) {
        const currentValues = splitValues(currentValue);

        if (operator === 'equals') {
            return currentValues.includes(targets[0]);
        }

        if (operator === 'not_equals') {
            return !currentValues.includes(targets[0]);
        }

        if (operator === 'in') {
            return targets.some((target) => currentValues.includes(target));
        }

        if (operator === 'not_in') {
            return !targets.some((target) => currentValues.includes(target));
        }

        return true;
    }

    if (operator === 'equals') {
        return currentValueString === targets[0];
    }

    if (operator === 'not_equals') {
        return currentValueString !== targets[0];
    }

    if (operator === 'in') {
        return targets.includes(currentValueString);
    }

    if (operator === 'not_in') {
        return !targets.includes(currentValueString);
    }

    return true;
}

function cloneDraftValue(value) {
    if (Array.isArray(value)) {
        return value.map((item) => cloneDraftValue(item));
    }

    if (value && typeof value === 'object') {
        return JSON.parse(JSON.stringify(value));
    }

    return value;
}

function normalizeFieldName(name) {
    return String(name ?? '').replace(/\[\]$/, '');
}

function isArrayFieldName(name) {
    return /\[\]$/.test(String(name ?? ''));
}

const reviewExcludedFields = new Set([
    '_token',
    'campaign',
    'form_type',
    'lead_id',
    'phone_number',
    'request_id',
    'agent',
    'id',
    'created_at',
    'updated_at',
]);

const emptyReviewValue = '—';

function cleanReviewText(value) {
    return String(value ?? '')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\s*\*$/, '')
        .trim();
}

function reviewOptionLabel(option) {
    return cleanReviewText(option?.textContent || option?.label || option?.value || '');
}

function reviewElementLabel(form, element) {
    const labels = Array.from(form?.querySelectorAll?.('label') ?? []);
    const associatedLabel = labels.find((label) => {
        const labelFor = label.htmlFor || label.getAttribute?.('for') || '';

        return element.id && String(labelFor) === String(element.id);
    });

    if (associatedLabel) {
        return cleanReviewText(associatedLabel.textContent);
    }

    const fieldContainer = element.closest?.('.form-field, fieldset');
    const fieldLabel = fieldContainer?.querySelector?.('label, legend');
    if (fieldLabel) {
        return cleanReviewText(fieldLabel.textContent);
    }

    const enclosingLabel = element.closest?.('label');
    if (enclosingLabel) {
        return cleanReviewText(enclosingLabel.textContent);
    }

    return cleanReviewText(element.name);
}

function reviewOptionValue(element) {
    const label = element.closest?.('label');

    return cleanReviewText(label?.textContent || element.value);
}

function isReviewElementVisible(element, form) {
    let current = element;

    while (current && current !== form) {
        if (current.hidden || current.style?.display === 'none') {
            return false;
        }

        current = current.parentElement;
    }

    return true;
}

function formatReviewGroup(group) {
    const element = group.elements[0];

    if (group.type === 'checkbox') {
        if (group.isArray) {
            const selected = group.elements
                .filter((control) => control.checked)
                .map((control) => reviewOptionValue(control))
                .filter((value) => value !== '');

            return selected.join(', ') || emptyReviewValue;
        }

        return element.checked ? 'Yes' : 'No';
    }

    if (group.type === 'radio') {
        const selected = group.elements.find((control) => control.checked);

        return selected ? reviewOptionValue(selected) : emptyReviewValue;
    }

    if (element instanceof HTMLSelectElement) {
        const selected = Array.from(element.selectedOptions ?? [])
            .filter((option) => String(option?.value ?? '') !== '')
            .map((option) => reviewOptionLabel(option))
            .filter((value) => value !== '');

        return selected.join(', ') || emptyReviewValue;
    }

    const value = cleanReviewText(element.value);
    if (!value) {
        return emptyReviewValue;
    }

    const suffix = element.closest?.('.relative')?.querySelector?.('span');
    if (suffix && cleanReviewText(suffix.textContent) === '%') {
        return value.endsWith('%') ? value : `${value}%`;
    }

    return value;
}

function storageAvailable() {
    try {
        return typeof window.localStorage !== 'undefined';
    } catch {
        return false;
    }
}

window.formVisibility = function formVisibility(extraState = {}) {
    return {
        values: {},
        initialValues: {},
        reviewOpen: false,
        reviewFields: [],
        saveStatus: '',
        saveMessage: '',
        saveErrors: [],
        draftStorageKey: '',
        draftSaveTimer: null,
        isDirty: false,
        hydrating: false,
        autosave: false,
        ...extraState,

        init(seed = {}) {
            const form = this.getFormElement();
            const seedValues = this.normalizeSeed(seed);
            const baseValues = this.collectFormValues(form);

            this.initialValues = this.cloneValues({ ...baseValues, ...seedValues });
            this.values = this.cloneValues(this.initialValues);
            this.applyValuesToForm(form, this.values);

            if (this.autosave === true) {
                this.draftStorageKey = this.buildDraftStorageKey(form);
                this.attachAutosaveListeners();

                let restoreAttempts = 0;
                const tryRestoreDraft = () => {
                    const restored = this.restoreDraft();
                    restoreAttempts += 1;

                    if (restored && this.isDirty) {
                        this.hydrating = false;
                    }

                    if (restoreAttempts < 5) {
                        window.setTimeout(tryRestoreDraft, 250);
                    }
                };

                window.setTimeout(tryRestoreDraft, 0);
            }
        },

        destroy() {
            this.detachAutosaveListeners();
            this.clearDraftTimer();
        },

        getFormElement() {
            return this.$el instanceof HTMLFormElement
                ? this.$el
                : this.$el?.closest?.('form') ?? null;
        },

        normalizeSeed(seed = {}) {
            if (!seed || Array.isArray(seed) || typeof seed !== 'object') {
                return {};
            }

            return seed;
        },

        cloneValues(values = {}) {
            return JSON.parse(JSON.stringify(values ?? {}));
        },

        clearFeedback() {
            this.saveStatus = '';
            this.saveMessage = '';
            this.saveErrors = [];
        },

        collectFormValues(form = this.getFormElement()) {
            const values = {};
            if (!form) {
                return values;
            }

            form.querySelectorAll('input, select, textarea').forEach((element) => {
                if (!element.name || element.disabled || element.name === '_token') {
                    return;
                }

                const fieldName = normalizeFieldName(element.name);
                if (!fieldName) {
                    return;
                }

                if (element instanceof HTMLInputElement) {
                    if (element.type === 'checkbox') {
                        if (isArrayFieldName(element.name)) {
                            if (!Array.isArray(values[fieldName])) {
                                values[fieldName] = [];
                            }
                            if (element.checked) {
                                values[fieldName].push(element.value ?? '');
                            }

                            return;
                        }

                        values[fieldName] = element.checked;

                        return;
                    }

                    if (element.type === 'radio') {
                        if (element.checked) {
                            values[fieldName] = element.value ?? '';
                        }

                        return;
                    }
                }

                if (element instanceof HTMLSelectElement && element.multiple) {
                    values[fieldName] = Array.from(element.selectedOptions).map((option) => option.value ?? '');

                    return;
                }

                values[fieldName] = element.value ?? '';
            });

            return values;
        },

        collectReviewFields(form = this.getFormElement()) {
            const groups = new Map();
            if (!form) {
                return [];
            }

            form.querySelectorAll('input, select, textarea').forEach((element) => {
                const fieldName = normalizeFieldName(element.name);
                if (!fieldName
                    || reviewExcludedFields.has(fieldName)
                    || element.disabled
                    || element.type === 'hidden'
                    || !isReviewElementVisible(element, form)) {
                    return;
                }

                if (!groups.has(fieldName)) {
                    groups.set(fieldName, {
                        name: fieldName,
                        elements: [],
                        isArray: isArrayFieldName(element.name),
                        type: element.type === 'checkbox' || element.type === 'radio'
                            ? element.type
                            : element instanceof HTMLSelectElement
                                ? 'select'
                                : 'value',
                    });
                }

                groups.get(fieldName).elements.push(element);
            });

            return Array.from(groups.values()).map((group) => ({
                label: reviewElementLabel(form, group.elements[0]),
                value: formatReviewGroup(group),
            }));
        },

        openReview() {
            if (this.submitting) {
                return false;
            }

            const form = this.getFormElement();
            if (!form) {
                return false;
            }

            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                form.reportValidity?.();

                return false;
            }

            this.values = this.cloneValues(this.collectFormValues(form));
            this.reviewFields = this.collectReviewFields(form);
            this.reviewOpen = true;

            return true;
        },

        closeReview() {
            this.reviewOpen = false;
        },

        async confirmReview() {
            if (this.submitting) {
                return false;
            }

            const form = this.getFormElement();
            if (!form) {
                return false;
            }

            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                this.closeReview();
                form.reportValidity?.();

                return false;
            }

            this.closeReview();

            return this.submitForm();
        },

        applyValuesToForm(form = this.getFormElement(), values = {}) {
            if (!form) {
                return;
            }

            form.querySelectorAll('input, select, textarea').forEach((element) => {
                if (!element.name || element.name === '_token') {
                    return;
                }

                const fieldName = normalizeFieldName(element.name);
                const storedValue = values?.[fieldName];

                if (element instanceof HTMLInputElement) {
                    if (element.type === 'checkbox') {
                        if (isArrayFieldName(element.name)) {
                            const selected = Array.isArray(storedValue) ? storedValue.map((item) => String(item ?? '')) : [];
                            element.checked = selected.includes(String(element.value ?? ''));

                            return;
                        }

                        element.checked = Boolean(storedValue);

                        return;
                    }

                    if (element.type === 'radio') {
                        element.checked = String(storedValue ?? '') === String(element.value ?? '');

                        return;
                    }
                }

                if (element instanceof HTMLSelectElement && element.multiple) {
                    const selected = Array.isArray(storedValue) ? storedValue.map((item) => String(item ?? '')) : [];
                    Array.from(element.options).forEach((option) => {
                        option.selected = selected.includes(String(option.value ?? ''));
                    });

                    return;
                }

                element.value = storedValue ?? '';
            });
        },

        syncValueFromElement(element) {
            if (!element || !element.name || element.name === '_token') {
                return false;
            }

            const fieldName = normalizeFieldName(element.name);
            if (!fieldName) {
                return false;
            }

            if (element instanceof HTMLInputElement) {
                if (element.type === 'checkbox') {
                    if (isArrayFieldName(element.name)) {
                        const current = Array.isArray(this.values[fieldName]) ? [...this.values[fieldName]] : [];
                        const normalized = String(element.value ?? '');
                        const index = current.findIndex((item) => String(item ?? '') === normalized);

                        if (element.checked && index === -1) {
                            current.push(normalized);
                        } else if (!element.checked && index !== -1) {
                            current.splice(index, 1);
                        }

                        this.values[fieldName] = current;
                    } else {
                        this.values[fieldName] = element.checked;
                    }

                    const snapshot = this.collectFormValues(this.getFormElement());
                    this.values = snapshot;
                    this.isDirty = !this.areValuesEqual(snapshot, this.initialValues);

                    return this.isDirty;
                }

                if (element.type === 'radio') {
                    if (element.checked) {
                        this.values[fieldName] = element.value ?? '';
                    }
                    const snapshot = this.collectFormValues(this.getFormElement());
                    this.values = snapshot;
                    this.isDirty = !this.areValuesEqual(snapshot, this.initialValues);

                    return this.isDirty;
                }
            }

            if (element instanceof HTMLSelectElement && element.multiple) {
                this.values[fieldName] = Array.from(element.selectedOptions).map((option) => option.value ?? '');

                const snapshot = this.collectFormValues(this.getFormElement());
                this.values = snapshot;
                this.isDirty = !this.areValuesEqual(snapshot, this.initialValues);

                return this.isDirty;
            }

            this.values[fieldName] = element.value ?? '';

            const snapshot = this.collectFormValues(this.getFormElement());
            this.values = snapshot;
            this.isDirty = !this.areValuesEqual(snapshot, this.initialValues);

            return this.isDirty;
        },

        buildDraftStorageKey(form = this.getFormElement()) {
            const context = this.getDraftContext(form);
            const segments = [
                'crm-form-draft:v1',
                `path:${window.location.pathname}`,
                `user:${context.userId || 'guest'}`,
                `campaign:${context.campaign || ''}`,
                `form:${context.formType || ''}`,
                `lead:${context.leadId || ''}`,
                `phone:${context.phoneNumber || ''}`,
            ];

            return segments.join('|');
        },

        getDraftContext(form = this.getFormElement()) {
            const dataset = this.$el?.dataset || {};

            return {
                userId: String(dataset.userId || '').trim(),
                campaign: String(dataset.campaign || '').trim(),
                formType: String(dataset.formType || '').trim(),
                leadId: String(dataset.leadId || '').trim(),
                phoneNumber: String(dataset.phoneNumber || '').trim(),
                formId: form?.id ? String(form.id).trim() : '',
            };
        },

        readDraftPayload() {
            if (!this.autosave || !storageAvailable() || !this.draftStorageKey) {
                return null;
            }

            try {
                const raw = window.localStorage.getItem(this.draftStorageKey);
                if (!raw) {
                    return null;
                }

                const parsed = JSON.parse(raw);
                if (!parsed || typeof parsed !== 'object' || !parsed.values || typeof parsed.values !== 'object') {
                    return null;
                }

                return parsed;
            } catch {
                return null;
            }
        },

        restoreDraft() {
            const draft = this.readDraftPayload();
            if (!draft) {
                return false;
            }

            const nextValues = this.cloneValues(this.initialValues);
            Object.entries(draft.values || {}).forEach(([key, value]) => {
                if (Object.prototype.hasOwnProperty.call(nextValues, key)) {
                    nextValues[key] = cloneDraftValue(value);
                }
            });

            this.values = nextValues;
            this.applyValuesToForm(this.getFormElement(), nextValues);
            this.isDirty = !this.areValuesEqual(nextValues, this.initialValues);

            return true;
        },

        persistDraft() {
            if (!this.autosave || this.hydrating || !storageAvailable() || !this.draftStorageKey) {
                return;
            }

            const form = this.getFormElement();
            if (!form) {
                return;
            }

            try {
                const payload = this.collectFormValues(form);
                this.values = this.cloneValues(payload);
                if (this.areValuesEqual(payload, this.initialValues)) {
                    window.localStorage.removeItem(this.draftStorageKey);
                    this.isDirty = false;

                    return;
                }
                window.localStorage.setItem(this.draftStorageKey, JSON.stringify({
                    version: 1,
                    saved_at: new Date().toISOString(),
                    values: payload,
                }));
                this.isDirty = true;
            } catch (error) {
                console.warn('[form-visibility] Unable to persist CRM form draft.', error);
            }
        },

        clearDraft() {
            if (!this.autosave || !storageAvailable() || !this.draftStorageKey) {
                return;
            }

            try {
                window.localStorage.removeItem(this.draftStorageKey);
            } catch (error) {
                console.warn('[form-visibility] Unable to clear CRM form draft.', error);
            }
        },

        clearDraftTimer() {
            if (this.draftSaveTimer) {
                clearTimeout(this.draftSaveTimer);
                this.draftSaveTimer = null;
            }
        },

        scheduleDraftSave() {
            if (!this.autosave || this.hydrating) {
                return;
            }

            this.clearDraftTimer();
            this.draftSaveTimer = setTimeout(() => {
                this.persistDraft();
            }, 250);
        },

        flushDraftSave() {
            if (!this.autosave || this.hydrating) {
                return;
            }

            this.clearDraftTimer();
            this.persistDraft();
        },

        attachAutosaveListeners() {
            if (!this.autosave || this._autosaveListenersAttached) {
                return;
            }

            this._autosaveListenersAttached = true;

            this._autosaveInputHandler = (event) => {
                const target = event?.target;
                if (!event?.isTrusted || !(target instanceof HTMLElement) || !target.name || target.name === '_token') {
                    return;
                }

                const dirty = this.syncValueFromElement(target);
                if (dirty) {
                    this.scheduleDraftSave();
                    if (this.saveStatus) {
                        this.clearFeedback();
                    }
                }
            };

            this._autosaveBeforeNavigateHandler = () => {
                this.flushDraftSave();
            };

            this.getFormElement()?.addEventListener('input', this._autosaveInputHandler);
            this.getFormElement()?.addEventListener('change', this._autosaveInputHandler);
            window.addEventListener('soft-navigate:before', this._autosaveBeforeNavigateHandler);
        },

        detachAutosaveListeners() {
            if (!this._autosaveListenersAttached) {
                return;
            }

            this._autosaveListenersAttached = false;

            const form = this.getFormElement();
            if (form && this._autosaveInputHandler) {
                form.removeEventListener('input', this._autosaveInputHandler);
                form.removeEventListener('change', this._autosaveInputHandler);
            }
            if (this._autosaveBeforeNavigateHandler) {
                window.removeEventListener('soft-navigate:before', this._autosaveBeforeNavigateHandler);
            }
        },

        setSaveFeedback(type, message, errors = []) {
            this.saveStatus = type;
            this.saveMessage = message || '';
            this.saveErrors = Array.isArray(errors) ? errors : [];
        },

        areValuesEqual(left = {}, right = {}) {
            return JSON.stringify(left ?? {}) === JSON.stringify(right ?? {});
        },

        flattenErrorMessages(errors = {}) {
            if (!errors || typeof errors !== 'object') {
                return [];
            }

            return Object.values(errors).flatMap((value) => {
                if (Array.isArray(value)) {
                    return value.map((item) => String(item ?? '').trim()).filter((item) => item !== '');
                }

                const message = String(value ?? '').trim();
                return message ? [message] : [];
            });
        },

        async submitForm() {
            if (this.submitting) {
                return false;
            }

            const form = this.getFormElement();
            if (!form) {
                return false;
            }

            this.submitting = true;
            this.clearFeedback();

            const payload = this.collectFormValues(form);
            this.values = this.cloneValues(payload);
            this.flushDraftSave();

            try {
                const response = await window.axios.post(form.action || window.location.href, payload);
                const data = response?.data || {};

                if (data?.success === false) {
                    const message = data?.message || 'Unable to save the form.';
                    this.setSaveFeedback('error', message, this.flattenErrorMessages(data?.errors));
                    this.persistDraft();

                    return false;
                }

                this.clearDraft();
                this.values = this.cloneValues(this.initialValues);
                this.applyValuesToForm(form, this.values);
                this.setSaveFeedback('success', data?.message || 'Record saved successfully.');

                if (window.Alpine?.store('toast')) {
                    window.Alpine.store('toast').success(data?.message || 'Record saved successfully.');
                }

                return true;
            } catch (error) {
                const status = error?.response?.status;
                const responseData = error?.response?.data || {};

                if (status === 422) {
                    const message = responseData?.message || 'Please review the highlighted fields.';
                    const errors = this.flattenErrorMessages(responseData?.errors);
                    this.setSaveFeedback('error', message, errors);
                } else {
                    const message = responseData?.message || 'Failed to save the form. Please try again.';
                    this.setSaveFeedback('error', message);
                }

                if (window.Alpine?.store('toast')) {
                    window.Alpine.store('toast').error(this.saveMessage || 'Failed to save the form. Please try again.');
                }

                this.persistDraft();

                return false;
            } finally {
                this.submitting = false;
            }
        },

        isVisible(rule) {
            return evaluateRule(rule, this.values);
        },

        shouldShow(fieldName, rule) {
            const visible = this.isVisible(rule);
            if (!visible && fieldName) {
                delete this.values[fieldName];
            }

            return visible;
        },
    };
};
