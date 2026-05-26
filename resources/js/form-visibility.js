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

    const currentValue = String(values?.[sourceField] ?? '').trim();

    if (operator === 'equals') {
        return currentValue === targets[0];
    }

    if (operator === 'not_equals') {
        return currentValue !== targets[0];
    }

    if (operator === 'in') {
        return targets.includes(currentValue);
    }

    if (operator === 'not_in') {
        return !targets.includes(currentValue);
    }

    return true;
}

window.formVisibility = function formVisibility(extraState = {}) {
    return {
        values: {},

        ...extraState,

        init(seed = {}) {
            this.values = { ...(seed || {}) };
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

