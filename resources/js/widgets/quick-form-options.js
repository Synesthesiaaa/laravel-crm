export function normalizeQuickFormOptions(forms) {
    if (!Array.isArray(forms)) {
        return [];
    }

    const seen = new Set();

    return forms.reduce((options, form) => {
        if (!form || typeof form !== 'object') {
            return options;
        }

        const type = typeof form.type === 'string' ? form.type.trim() : '';
        if (!type || seen.has(type)) {
            return options;
        }

        const name = typeof form.name === 'string' ? form.name.trim() : '';
        seen.add(type);
        options.push({
            type,
            name: name || type,
        });

        return options;
    }, []);
}

export function hasQuickFormOption(options, formType) {
    return typeof formType === 'string'
        && Array.isArray(options)
        && options.some((option) => option?.type === formType);
}
