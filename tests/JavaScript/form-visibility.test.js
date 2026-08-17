import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const formVisibilitySource = fs.readFileSync(
    path.join(projectRoot, 'resources', 'js', 'form-visibility.js'),
    'utf8',
);

class FakeInputElement {
    constructor({ name, label, value = '', type = 'text', id = name, disabled = false, checked = false, parent = null }) {
        this.name = name;
        this.label = label;
        this.value = value;
        this.type = type;
        this.id = id;
        this.disabled = disabled;
        this.checked = checked;
        this.parentElement = parent;
        this.style = { display: '' };
    }

    closest(selector) {
        if (selector === 'label') {
            return { textContent: this.label };
        }

        return this.parentElement;
    }
}

class FakeFieldContainer {
    constructor(label, suffix = '') {
        this.label = { textContent: label };
        this.suffix = { textContent: suffix };
        this.parentElement = null;
        this.style = { display: '' };
    }

    querySelector(selector) {
        if (selector === 'label, legend') {
            return this.label;
        }

        if (selector === 'span') {
            return this.suffix;
        }

        return null;
    }
}

class FakeSelectElement {
    constructor({ name, label, value, options, multiple = false, disabled = false, parent = null }) {
        this.name = name;
        this.label = label;
        this.value = value;
        this.id = name;
        this.type = 'select-one';
        this.options = options.map(([optionValue, text]) => ({ value: optionValue, textContent: text, label: text }));
        const selectedValues = Array.isArray(value) ? value : [value];
        this.selectedOptions = this.options.filter((option) => selectedValues.includes(option.value));
        this.multiple = multiple;
        this.disabled = disabled;
        this.parentElement = parent;
        this.style = { display: '' };
    }

    closest() {
        return this.parentElement;
    }
}

class FakeTextAreaElement extends FakeInputElement {
    constructor(options) {
        super({ ...options, type: 'textarea' });
    }
}

class FakeFormElement {
    constructor(controls, { valid = true } = {}) {
        this.controls = controls;
        this.valid = valid;
        this.reported = false;
    }

    querySelectorAll(selector) {
        if (selector === 'input, select, textarea') {
            return this.controls;
        }

        if (selector === 'label') {
            return this.controls.map((control) => ({ htmlFor: control.id, textContent: control.label }));
        }

        return [];
    }

    checkValidity() {
        return this.valid;
    }

    reportValidity() {
        this.reported = true;

        return this.valid;
    }
}

function loadFormVisibility(form) {
    const context = {
        HTMLFormElement: FakeFormElement,
        HTMLInputElement: FakeInputElement,
        HTMLSelectElement: FakeSelectElement,
        HTMLTextAreaElement: FakeTextAreaElement,
        window: {},
    };

    vm.runInNewContext(formVisibilitySource, context);

    return {
        ...context.window.formVisibility({ submitting: false }),
        $el: form,
    };
}

function textInput(name, label, value, options = {}) {
    return new FakeInputElement({ name, label, value, ...options });
}

function selectInput(name, label, value, options, config = {}) {
    return new FakeSelectElement({ name, label, value, options, ...config });
}

function checkboxInput(name, label, checked, options = {}) {
    return new FakeInputElement({ name, label, type: 'checkbox', checked, value: '1', ...options });
}

test('opens review rows without submitting and excludes internal or disabled controls', () => {
    const form = new FakeFormElement([
        textInput('cardholder_name', 'Cardholder Name', 'Ada Lovelace'),
        selectInput('account_type', 'Account Type', 'savings', [['savings', 'Savings']]),
        checkboxInput('marketing_opt_in', 'Marketing Opt In', false),
        textInput('phone_number', 'Phone Number', '15551234567', { type: 'hidden' }),
        textInput('hidden_field', 'Hidden Field', 'secret', { disabled: true }),
    ]);
    const component = loadFormVisibility(form);

    component.openReview();

    assert.equal(component.reviewOpen, true);
    assert.deepEqual(JSON.parse(JSON.stringify(component.reviewFields)), [
        { label: 'Cardholder Name', value: 'Ada Lovelace' },
        { label: 'Account Type', value: 'Savings' },
        { label: 'Marketing Opt In', value: 'No' },
    ]);
});

test('cancel preserves values and confirm delegates the save', async () => {
    const form = new FakeFormElement([textInput('customer_name', 'Customer Name', 'Ada')]);
    const component = loadFormVisibility(form);
    let submits = 0;
    component.submitForm = async () => {
        submits += 1;
    };

    component.openReview();
    component.closeReview();
    assert.equal(component.reviewOpen, false);
    assert.equal(form.controls[0].value, 'Ada');

    component.openReview();
    await component.confirmReview();
    assert.equal(submits, 1);
});

test('invalid forms stay closed and report native validation', () => {
    const form = new FakeFormElement([textInput('customer_name', 'Customer Name', '')], { valid: false });
    const component = loadFormVisibility(form);

    component.openReview();

    assert.equal(component.reviewOpen, false);
    assert.equal(form.reported, true);
});

test('formats multiselect options and percentage values', () => {
    const benefitsContainer = new FakeFieldContainer('Benefits');
    const rateContainer = new FakeFieldContainer('Rate', '%');
    const form = new FakeFormElement([
        checkboxInput('benefits[]', 'Email', true, { id: '', parent: benefitsContainer }),
        checkboxInput('benefits[]', 'SMS', false, { id: '', parent: benefitsContainer }),
        textInput('rate', 'Rate', '25', { parent: rateContainer }),
    ]);
    const component = loadFormVisibility(form);

    component.openReview();

    assert.deepEqual(JSON.parse(JSON.stringify(component.reviewFields)), [
        { label: 'Benefits', value: 'Email' },
        { label: 'Rate', value: '25%' },
    ]);
});
