@extends('layouts.app')

@section('title', 'Forms - Admin')
@section('header-icon')
    <x-icon name="document-text" class="w-5 h-5 text-[var(--color-primary)]" />
@endsection
@section('header-title', 'Forms')

@section('content')
    @if(session('success'))
        <x-alert type="success" class="mb-4">{{ session('success') }}</x-alert>
    @endif
    @if(session('error'))
        <x-alert type="error" class="mb-4">{{ session('error') }}</x-alert>
    @endif
    <x-validation-errors />

    <x-page-header title="Forms" description="Manage the form definitions available within each CRM campaign."
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Forms' => null]" />

    <div class="md-card mb-6 md-card--static">
        <div class="p-4">
            <form method="GET" action="{{ route('admin.forms.index') }}" class="filter-row">
                <div class="form-field">
                    <label class="form-label" for="campaign-filter">Campaign</label>
                    <select id="campaign-filter" name="campaign" class="form-select">
                        @foreach($campaigns as $c)
                            <option value="{{ $c->code }}" {{ $selectedCampaign === $c->code ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-actions-bottom">
                    <button type="submit" class="btn-primary">
                        <x-icon name="funnel" class="w-4 h-4" />
                        Load
                    </button>
                </div>
            </form>
        </div>
    </div>

    <x-admin.panel title="Add Form" description="Create a form for the selected campaign and bind it to its storage table." class="mb-6">
            <form method="POST" action="{{ route('admin.forms.store') }}"
                  x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                <input type="hidden" name="campaign_code" value="{{ $selectedCampaign }}">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="form-field">
                        <label class="form-label" for="add-form-code">Form Code</label>
                        <input id="add-form-code" type="text" name="form_code" value="{{ old('form_code') }}" required pattern="[a-z0-9_]+" class="form-input @error('form_code') error @enderror" placeholder="e.g. ezycash">
                        <p class="form-help">Use lowercase letters, numbers, and underscores.</p>
                    </div>
                    <x-form.input name="name" label="Name" :value="old('name')" required />
                    <x-form.input name="table_name" label="Table Name" :value="old('table_name')" required />
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn-primary" :disabled="submitting">
                        <x-icon name="plus" class="w-4 h-4" />
                        Add
                    </button>
                </div>
            </form>
    </x-admin.panel>

    @if($salesConfiguration['warnings'] ?? [])
        <x-alert type="warning" class="mb-4">
            Some saved sales attribution references need attention. Open the affected form and review its Sales Attribution settings.
        </x-alert>
    @endif

    @php
        $salesTriggerLabels = [
            'form' => 'Every submission',
            'tag' => 'Tag match',
            'marked_amount' => 'Marked amount',
        ];
    @endphp

    <x-table.index caption="Campaign forms">
        <x-table.head :columns="[
            ['label' => 'Form Code'],
            ['label' => 'Name'],
            ['label' => 'Table'],
            ['label' => 'Sales Attribution'],
            ['label' => 'Actions', 'align' => 'right'],
        ]" />
        @forelse($forms as $f)
            @php
                $savedSalesRule = $salesMode === 'custom' ? ($salesRulesByForm[$f->form_code] ?? null) : null;
                $salesRuleEnabled = is_array($savedSalesRule);
                $salesStatus = $salesRuleEnabled
                    ? ($salesTriggerLabels[$savedSalesRule['trigger'] ?? 'form'] ?? 'Custom rule')
                    : ($salesMode === 'custom' ? 'Not included' : 'Default markers');
            @endphp
            <tbody>
                <tr>
                    <td><span class="font-mono font-semibold text-sm text-[var(--color-on-surface)]">{{ $f->form_code }}</span></td>
                    <td>{{ $f->name }}</td>
                    <td class="font-mono text-sm">{{ $f->table_name }}</td>
                    <td>
                        <x-badge :type="$salesRuleEnabled ? 'active' : 'muted'" :dot="false">
                            {{ $salesStatus }}
                        </x-badge>
                    </td>
                    <td>
                        <div class="table-actions">
                            <button type="button" class="btn-secondary text-xs px-2 py-1"
                                    @click="$store.modal.show('edit-form-{{ $f->id }}')">
                                <x-icon name="pencil" class="w-3.5 h-3.5" />
                                Edit
                            </button>
                            <a href="{{ route('admin.field-logic.index', ['form' => $f->form_code]) }}" class="btn-ghost text-xs px-2 py-1">
                                <x-icon name="cog-6-tooth" class="w-3.5 h-3.5" />
                                Fields
                            </a>
                            <div x-data="{ async del(form) {
                                const ok = await Alpine.store('confirm').ask('Deactivate form?', 'Deactivate {{ $f->name }}?');
                                if (ok) form.submit();
                            }}">
                                <form method="POST" action="{{ route('admin.forms.destroy') }}" x-ref="delForm{{ $f->id }}">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $f->id }}">
                                    <button type="button" class="btn-danger text-xs px-2 py-1"
                                            @click="del($refs['delForm{{ $f->id }}'])">
                                        <x-icon name="trash" class="w-3.5 h-3.5" />
                                        Deactivate
                                    </button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        @empty
            <x-table.empty :colspan="5" message="No forms for this campaign." description="Add a form above to get started." />
        @endforelse
    </x-table.index>

    @foreach($forms as $f)
        @php
            $isEditing = $errors->isNotEmpty() && (int) old('_editing') === $f->id;
            $savedSalesRule = $salesMode === 'custom' ? ($salesRulesByForm[$f->form_code] ?? null) : null;
            $salesEditorForm = $salesEditorForms[$f->form_code] ?? null;
            $salesAvailable = is_array($salesEditorForm);
            $modalSalesEnabled = $isEditing
                ? (bool) old('sales_rule.enabled', false)
                : is_array($savedSalesRule);
            $modalSalesRule = $isEditing
                ? [
                    'form_code' => $f->form_code,
                    'amount_field' => (string) old('sales_rule.amount_field', ''),
                    'trigger' => (string) old('sales_rule.trigger', 'form'),
                    'conditions' => old('sales_rule.conditions', []),
                ]
                : (is_array($savedSalesRule)
                    ? $savedSalesRule
                    : [
                        'form_code' => $f->form_code,
                        'amount_field' => '',
                        'trigger' => 'form',
                        'conditions' => [],
                    ]);
        @endphp

        <x-modal name="edit-form-{{ $f->id }}" title="Edit Form: {{ $f->name }}" maxWidth="xl" :closeOnBackdrop="true">
            <div
                x-data="adminSalesAttributionEditor({
                    salesEnabled: @js($modalSalesEnabled),
                    salesRules: @js([$modalSalesRule]),
                    formOptions: @js($salesAvailable ? [$salesEditorForm] : []),
                })"
                x-init="initialiseRule()">
                <form method="POST" action="{{ route('admin.forms.update', $f) }}"
                      x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="campaign_code" value="{{ $f->campaign_code }}">
                    <input type="hidden" name="_editing" value="{{ $f->id }}">
                    <input type="hidden" name="sales_rule[enabled]" :value="salesEnabled ? 1 : 0">
                    <input type="hidden" name="sales_rule[form_code]" value="{{ $f->form_code }}">

                    <div class="space-y-6">
                        <section>
                            <div class="mb-3">
                                <h4 class="text-sm font-semibold text-[var(--color-on-surface)]">Form details</h4>
                                <p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Update the form identity and storage table.</p>
                            </div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="form-field">
                                    <label class="form-label" for="edit-form-code-{{ $f->id }}">Form Code</label>
                                    <input id="edit-form-code-{{ $f->id }}" type="text" name="form_code"
                                           value="{{ $isEditing ? old('form_code', $f->form_code) : $f->form_code }}"
                                           required pattern="[a-z0-9_]+"
                                           class="form-input @error('form_code') error @enderror">
                                </div>
                                <div class="form-field">
                                    <label class="form-label" for="edit-form-name-{{ $f->id }}">Name <span class="text-[var(--color-danger)] ml-0.5">*</span></label>
                                    <input id="edit-form-name-{{ $f->id }}" type="text" name="name"
                                           value="{{ $isEditing ? old('name', $f->name) : $f->name }}"
                                           required
                                           class="form-input {{ $isEditing && $errors->has('name') ? 'error' : '' }}"
                                           @if($isEditing && $errors->has('name')) aria-invalid="true" aria-describedby="edit-form-name-error-{{ $f->id }}" @endif>
                                    @if($isEditing && $errors->has('name'))
                                        <span id="edit-form-name-error-{{ $f->id }}" class="form-error-msg" role="alert">
                                            <x-icon name="exclamation-circle" class="w-3.5 h-3.5 shrink-0" />
                                            {{ $errors->first('name') }}
                                        </span>
                                    @endif
                                </div>
                                <div class="form-field">
                                    <label class="form-label" for="edit-form-table-{{ $f->id }}">Table Name <span class="text-[var(--color-danger)] ml-0.5">*</span></label>
                                    <input id="edit-form-table-{{ $f->id }}" type="text" name="table_name"
                                           value="{{ $isEditing ? old('table_name', $f->table_name) : $f->table_name }}"
                                           required
                                           class="form-input {{ $isEditing && $errors->has('table_name') ? 'error' : '' }}"
                                           @if($isEditing && $errors->has('table_name')) aria-invalid="true" aria-describedby="edit-form-table-error-{{ $f->id }}" @endif>
                                    @if($isEditing && $errors->has('table_name'))
                                        <span id="edit-form-table-error-{{ $f->id }}" class="form-error-msg" role="alert">
                                            <x-icon name="exclamation-circle" class="w-3.5 h-3.5 shrink-0" />
                                            {{ $errors->first('table_name') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </section>

                        <section class="border-t border-[var(--color-border)] pt-5">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="max-w-2xl">
                                    <h4 class="text-sm font-semibold text-[var(--color-on-surface)]">Sales Attribution</h4>
                                    <p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">
                                        Choose when submissions from this form count as sales. Custom rules here are used by dashboard sales totals and reports for {{ $campaignName }}.
                                    </p>
                                </div>
                                <label class="inline-flex items-center gap-2 text-sm text-[var(--color-on-surface)]">
                                    <input type="checkbox" class="form-checkbox" x-model="salesEnabled" @disabled(! $salesAvailable)>
                                    Use custom attribution
                                </label>
                            </div>

                            @if(! $salesAvailable)
                                <div class="mt-4 rounded-lg border border-[var(--color-warning)]/40 bg-[var(--color-warning-muted)] p-3 text-xs text-[var(--color-warning-fg)]">
                                    Sales Attribution becomes available when this form has a registered storage table and fields.
                                </div>
                            @else
                                <div x-show="!salesEnabled" x-cloak class="mt-4 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] p-3 text-xs text-[var(--color-on-surface-muted)]">
                                    @if($salesMode === 'custom')
                                        This form is not included in the campaign's custom sales attribution. Enable it to count submissions from this form.
                                    @else
                                        This form uses the campaign's default sale markers. Enable custom attribution to count every submission, match a tag, or use a marked sale amount.
                                    @endif
                                </div>

                                <div x-show="salesEnabled && salesRules[0]" x-cloak class="mt-4 space-y-4">
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="form-field">
                                            <label class="form-label" for="sales-trigger-{{ $f->id }}">Sales trigger</label>
                                            <select id="sales-trigger-{{ $f->id }}" name="sales_rule[trigger]"
                                                    class="form-select @error('sales_rule.trigger') error @enderror"
                                                    x-model="salesRules[0].trigger"
                                                    @change="changeRuleTrigger(salesRules[0])"
                                                    :disabled="!salesEnabled">
                                                <option value="form">Any form submission</option>
                                                <option value="tag" :disabled="tagFields(salesRules[0].form_code).length === 0">Match tag value</option>
                                                <option value="marked_amount" :disabled="markedAmountFields(salesRules[0].form_code).length === 0">Marked sale amount</option>
                                            </select>
                                        </div>

                                        <div class="form-field">
                                            <label class="form-label" for="sales-amount-{{ $f->id }}"
                                                   x-text="ruleUsesMarkedAmount(salesRules[0]) ? 'Marked sale amount field' : 'Amount field (optional)'"></label>
                                            <select id="sales-amount-{{ $f->id }}" name="sales_rule[amount_field]"
                                                    class="form-select @error('sales_rule.amount_field') error @enderror"
                                                    x-model="salesRules[0].amount_field"
                                                    :disabled="!salesEnabled">
                                                <option value="" x-show="!ruleUsesMarkedAmount(salesRules[0])">Count only</option>
                                                <template x-for="field in ruleAmountFields(salesRules[0])" :key="field.name">
                                                    <option :value="field.name" x-text="field.label"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </div>

                                    <div x-show="ruleUsesForm(salesRules[0])" x-cloak class="rounded-lg border border-[var(--color-primary)]/30 bg-[var(--color-primary-muted)] p-3 text-xs text-[var(--color-on-surface-muted)]">
                                        Every submission of this form counts as one sale. An amount field is optional and only contributes to the total sales amount.
                                    </div>

                                    <div x-show="ruleUsesMarkedAmount(salesRules[0])" x-cloak class="rounded-lg border border-[var(--color-primary)]/30 bg-[var(--color-primary-muted)] p-3 text-xs text-[var(--color-on-surface-muted)]">
                                        A submission counts when the selected marked numeric field has a value, and that value is added to the sales amount.
                                    </div>

                                    <div x-show="ruleUsesTag(salesRules[0])" x-cloak class="space-y-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <p class="text-xs font-semibold text-[var(--color-on-surface)]">Tag conditions</p>
                                                <p class="text-[11px] text-[var(--color-on-surface-dim)]">A submission counts when any condition matches.</p>
                                            </div>
                                            <button type="button" class="btn-ghost text-xs"
                                                    @click="addCondition(salesRules[0])"
                                                    :disabled="tagFields(salesRules[0].form_code).length === 0">
                                                + Add condition
                                            </button>
                                        </div>

                                        <template x-for="(condition, conditionIndex) in salesRules[0].conditions" :key="conditionIndex">
                                            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] p-3 space-y-3">
                                                <div class="flex items-center justify-between gap-2">
                                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[var(--color-on-surface-dim)]"
                                                       x-text="'Condition ' + (conditionIndex + 1)"></p>
                                                    <button type="button" class="text-[11px] text-[var(--color-danger-fg)] hover:underline"
                                                            @click="removeCondition(salesRules[0], conditionIndex)">
                                                        Remove
                                                    </button>
                                                </div>
                                                <div class="form-field">
                                                    <label class="form-label">Tag field</label>
                                                    <select class="form-select" :name="'sales_rule[conditions][' + conditionIndex + '][field_name]'"
                                                            x-model="condition.field_name" :disabled="!salesEnabled">
                                                        <template x-for="field in tagFields(salesRules[0].form_code)" :key="field.name">
                                                            <option :value="field.name" x-text="field.label"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div class="space-y-2">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <label class="form-label mb-0">Accepted values</label>
                                                        <button type="button" class="text-[11px] text-[var(--color-action)] hover:underline"
                                                                @click="addAcceptedValue(condition)">Add value</button>
                                                    </div>
                                                    <template x-for="(value, valueIndex) in condition.accepted_values" :key="valueIndex">
                                                        <div class="flex items-center gap-2">
                                                            <input type="text" class="form-input flex-1"
                                                                   :name="'sales_rule[conditions][' + conditionIndex + '][accepted_values][]'"
                                                                   x-model="condition.accepted_values[valueIndex]"
                                                                   :disabled="!salesEnabled"
                                                                   placeholder="e.g. Yes">
                                                            <button type="button" class="btn-icon"
                                                                    @click="removeAcceptedValue(condition, valueIndex)"
                                                                    :aria-label="'Remove accepted value ' + (valueIndex + 1)">&times;</button>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            @endif
                        </section>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--color-border)] pt-4">
                            <label class="inline-flex items-center gap-2 cursor-pointer" for="edit-form-active-{{ $f->id }}">
                                <input type="hidden" name="is_active" value="0">
                                <input id="edit-form-active-{{ $f->id }}" type="checkbox" name="is_active" value="1"
                                       class="w-4 h-4 rounded border-[var(--color-border)] accent-[var(--color-primary)] cursor-pointer"
                                       @checked($isEditing ? (bool) old('is_active', $f->is_active) : $f->is_active)>
                                <span class="text-sm text-[var(--color-on-surface)]">Active</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <button type="button" class="btn-secondary" @click="$store.modal.hide()">Cancel</button>
                                <button type="submit" class="btn-primary" :disabled="submitting">
                                    <x-icon name="check" class="w-4 h-4" />
                                    <span x-text="submitting ? 'Saving...' : 'Save changes'">Save changes</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </x-modal>

        @if($isEditing)
            <div x-data x-init="$nextTick(() => $store.modal.show('edit-form-{{ $f->id }}'))"></div>
        @endif
    @endforeach
@endsection
