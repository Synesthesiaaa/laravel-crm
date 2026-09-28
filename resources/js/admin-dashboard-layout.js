function normalizeSalesRules(rules) {
    return Array.isArray(rules)
        ? rules.map((rule) => ({
            form_code: String(rule?.form_code || ''),
            amount_field: String(rule?.amount_field || ''),
            trigger: String(rule?.trigger || ''),
            conditions: Array.isArray(rule?.conditions)
                ? rule.conditions.map((condition) => ({
                    field_name: String(condition?.field_name || ''),
                    accepted_values: Array.isArray(condition?.accepted_values)
                        ? [...condition.accepted_values]
                        : [''],
                }))
                : [],
        }))
        : [];
}

window.adminDashboardLayoutEditor = function adminDashboardLayoutEditor(config = {}) {
    return {
        sections: Array.isArray(config.sections) ? [...config.sections] : [],
        visible: Array.isArray(config.visible) ? [...config.visible] : [],
        labels: config.labels || {},
        salesMode: config.salesMode || 'legacy',
        salesRules: normalizeSalesRules(config.salesRules),
        formOptions: Array.isArray(config.formOptions) ? config.formOptions : [],

        initialiseRules() {
            this.salesRules = this.salesRules.map((rule) => {
                const trigger = rule.trigger || (rule.conditions.length > 0
                    ? 'tag'
                    : (this.markedAmountFields(rule.form_code).some((field) => field.name === rule.amount_field)
                        ? 'marked_amount'
                        : 'form'));

                return { ...rule, trigger };
            });
        },

        move(index, direction) {
            const next = index + direction;
            if (next < 0 || next >= this.sections.length) return;

            [this.sections[index], this.sections[next]] = [this.sections[next], this.sections[index]];
        },

        formByCode(code) {
            return this.formOptions.find((form) => form.code === code) || null;
        },

        tagFields(code) {
            return (this.formByCode(code)?.fields || []).filter((field) => field.is_tag);
        },

        markedAmountFields(code) {
            return (this.formByCode(code)?.fields || []).filter((field) => field.is_sale_amount);
        },

        tagForms() {
            return this.formOptions;
        },

        amountFields(code) {
            return (this.formByCode(code)?.fields || []).filter((field) => field.is_amount);
        },

        ruleUsesMarkedAmount(rule) {
            return rule.trigger === 'marked_amount';
        },

        ruleUsesTag(rule) {
            return rule.trigger === 'tag';
        },

        ruleUsesForm(rule) {
            return rule.trigger === 'form';
        },

        ruleAmountFields(rule) {
            return this.ruleUsesMarkedAmount(rule)
                ? this.markedAmountFields(rule.form_code)
                : this.amountFields(rule.form_code);
        },

        addRule() {
            const form = this.tagForms()[0];
            if (!form) return;

            this.salesRules.push({
                form_code: form.code,
                amount_field: '',
                trigger: 'form',
                conditions: [],
            });
        },

        removeRule(index) {
            this.salesRules.splice(index, 1);
        },

        addCondition(rule) {
            const tag = this.tagFields(rule.form_code)[0];
            rule.conditions.push({ field_name: tag?.name || '', accepted_values: [''] });
        },

        removeCondition(rule, index) {
            rule.conditions.splice(index, 1);
        },

        addAcceptedValue(condition) {
            condition.accepted_values.push('');
        },

        removeAcceptedValue(condition, index) {
            if (condition.accepted_values.length === 1) {
                condition.accepted_values[0] = '';
                return;
            }

            condition.accepted_values.splice(index, 1);
        },

        changeRuleForm(rule) {
            this.changeRuleTrigger(rule);
        },

        changeRuleTrigger(rule) {
            if (rule.trigger === 'tag') {
                const tag = this.tagFields(rule.form_code)[0];
                rule.amount_field = '';
                rule.conditions = tag ? [{ field_name: tag.name, accepted_values: [''] }] : [];
                return;
            }

            if (rule.trigger === 'marked_amount') {
                rule.amount_field = this.markedAmountFields(rule.form_code)[0]?.name || '';
                rule.conditions = [];
                return;
            }

            rule.amount_field = '';
            rule.conditions = [];
        },

        switchToTagRule(rule) {
            const tag = this.tagFields(rule.form_code)[0];
            if (!tag) return;

            rule.trigger = 'tag';
            this.changeRuleTrigger(rule);
        },
    };
};
