@extends('layouts.app')

@section('title', 'Management Dashboard')
@section('header-icon')<x-icon name="shield-check" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Management Dashboard')

@section('content')
@php
    $agentScreenVisible = app(\App\Services\TelephonyFeatureService::class)->isEnabled('agent_screen_access');
@endphp
<div class="space-y-8">

    <div class="md-hero">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-xl font-bold text-[var(--color-on-surface)]">Admin Control Center</h2>
                <p class="text-[var(--color-on-surface-muted)] text-sm mt-1">
                    Campaign: <span class="font-semibold text-[var(--color-primary)]">{{ $campaignName }}</span>
                </p>
            </div>
            <div class="flex gap-2">
                <x-badge type="active">Live</x-badge>
                @if($user->isSuperAdmin())
                    <x-badge type="error">Super Admin</x-badge>
                @elseif($user->isAdmin())
                    <x-badge type="warning">Admin</x-badge>
                @else
                    <x-badge type="info">Team Leader</x-badge>
                @endif
            </div>
        </div>
        <form method="GET" action="{{ route('admin.dashboard') }}" class="mt-5 flex flex-wrap items-end gap-3">
            <div class="min-w-[15rem] flex-1 max-w-md">
                <label for="admin-dashboard-campaign" class="form-label">Customize campaign</label>
                <select id="admin-dashboard-campaign" name="campaign" class="form-select w-full" onchange="this.form.submit()">
                    @foreach($campaigns as $campaignCode => $campaignConfig)
                        <option value="{{ $campaignCode }}" @selected($campaignCode === $campaign)>{{ $campaignConfig['name'] ?? $campaignCode }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">This selector only changes the dashboard being edited; it does not change an agent's active campaign.</p>
            </div>
            <noscript><button type="submit" class="btn-secondary">Load campaign</button></noscript>
        </form>
    </div>

    @if($user->isAdmin())
        @php
            $savedDashboardSections = collect($dashboardLayout['sections'] ?? [])
                ->sortBy('order')
                ->keys()
                ->values()
                ->all();
            $visibleDashboardSections = collect($dashboardLayout['sections'] ?? [])
                ->filter(fn ($section) => ($section['visible'] ?? false) === true)
                ->keys()
                ->values()
                ->all();
            $storedSales = is_array($dashboardLayout['sales'] ?? null) ? $dashboardLayout['sales'] : [];
            $salesRulesSource = old('sales_forms', $storedSales['forms'] ?? []);
            $storedSalesRules = collect($salesRulesSource)
                ->filter(fn ($rule) => is_array($rule))
                ->map(function ($rule) {
                    return [
                        'form_code' => (string) ($rule['form_code'] ?? ''),
                        'amount_field' => (string) ($rule['amount_field'] ?? ''),
                        'trigger' => (string) ($rule['trigger'] ?? ''),
                        'conditions' => collect($rule['conditions'] ?? [])
                            ->filter(fn ($condition) => is_array($condition))
                            ->map(fn ($condition) => [
                                'field_name' => (string) ($condition['field_name'] ?? ''),
                                'accepted_values' => array_values(array_filter(
                                    array_map('strval', (array) ($condition['accepted_values'] ?? [])),
                                    fn ($value) => trim($value) !== '',
                                )),
                            ])
                            ->values()
                            ->all(),
                    ];
                })
                ->values()
                ->all();
        @endphp
        <div class="md-card" x-data="{
            sections: @js($savedDashboardSections),
            visible: @js($visibleDashboardSections),
            labels: @js($dashboardSections),
            salesMode: @js(old('sales_mode', $storedSales['mode'] ?? 'legacy')),
            salesRules: @js($storedSalesRules),
            formOptions: @js($salesEditorForms),
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
                const current = this.sections[index];
                this.sections[index] = this.sections[next];
                this.sections[next] = current;
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
        }" x-init="initialiseRules()">
            <div class="p-5 border-b border-[var(--color-border)]">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Customize user dashboard</h3>
                        <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">Choose visible sections and use the arrows to set their order for {{ $campaignName }}.</p>
                    </div>
                    <x-badge type="info">Admin controlled</x-badge>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.dashboard-layout.update') }}" class="p-5 space-y-6">
                @csrf
                <input type="hidden" name="campaign_code" value="{{ $campaign }}">
                <fieldset class="space-y-2" x-data="{ amountsEnabled: @js((bool) old('amounts.enabled', data_get($dashboardLayout, 'amounts.enabled', true))) }">
                    <legend class="text-sm font-semibold text-[var(--color-on-surface)]">Dashboard amounts</legend>
                    <p class="text-xs text-[var(--color-on-surface-dim)]">Turn off all amounts for this campaign, or choose the monetary displays to include. Sales counts remain visible.</p>
                    @foreach(\App\Services\DashboardLayoutService::amountDefinitions() as $amountKey => $amountLabel)
                        <label class="flex min-h-11 items-center gap-3 rounded-lg border border-[var(--color-border)] px-3 py-2"
                               @if($amountKey !== 'enabled') x-show="amountsEnabled" @endif>
                            <input type="hidden" name="amounts[{{ $amountKey }}]" value="0">
                            <input type="checkbox" name="amounts[{{ $amountKey }}]" value="1" class="form-checkbox"
                                   @checked(old('amounts.'.$amountKey, data_get($dashboardLayout, 'amounts.'.$amountKey, true)))
                                   @if($amountKey === 'enabled') x-model="amountsEnabled" @endif>
                            <span class="text-sm">{{ $amountLabel }}</span>
                        </label>
                    @endforeach
                </fieldset>
                <div class="space-y-2">
                    <template x-for="(section, index) in sections" :key="section">
                        <div class="flex items-center gap-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] px-3 py-2">
                            <input type="hidden" name="section_order[]" :value="section">
                            <input type="checkbox" name="visible_sections[]" :value="section" :id="'dashboard-section-' + section" x-model="visible" class="form-checkbox">
                            <label :for="'dashboard-section-' + section" class="flex-1 text-sm text-[var(--color-on-surface)]" x-text="labels[section]"></label>
                            <div class="flex items-center gap-1">
                                <button type="button" class="btn-icon" @click="move(index, -1)" :disabled="index === 0" :aria-label="'Move ' + labels[section] + ' up'">↑</button>
                                <button type="button" class="btn-icon" @click="move(index, 1)" :disabled="index === sections.length - 1" :aria-label="'Move ' + labels[section] + ' down'">↓</button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="border-t border-[var(--color-border)] pt-5 space-y-4">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <h4 class="text-sm font-semibold text-[var(--color-on-surface)]">Sales attribution</h4>
                            <p class="text-xs text-[var(--color-on-surface-dim)] mt-1 max-w-2xl">Choose how this campaign counts sales: every submission, Yes/No tag conditions, or a numeric field marked as a sale amount. An amount field is optional for submission and tag rules.</p>
                        </div>
                        <div class="flex items-center gap-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] p-1" role="group" aria-label="Sales attribution mode">
                            <label class="cursor-pointer rounded-md px-3 py-1.5 text-xs transition" :class="salesMode === 'legacy' ? 'bg-[var(--color-surface)] text-[var(--color-on-surface)] shadow-sm' : 'text-[var(--color-on-surface-dim)]'">
                                <input type="radio" name="sales_mode" value="legacy" x-model="salesMode" class="sr-only"> Legacy
                            </label>
                            <label class="cursor-pointer rounded-md px-3 py-1.5 text-xs transition" :class="salesMode === 'custom' ? 'bg-[var(--color-primary-muted)] text-[var(--color-primary)] shadow-sm' : 'text-[var(--color-on-surface-dim)]'">
                                <input type="radio" name="sales_mode" value="custom" x-model="salesMode" class="sr-only"> Custom rules
                            </label>
                        </div>
                    </div>

                    <div x-show="salesMode === 'legacy'" x-cloak class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] p-4 text-xs text-[var(--color-on-surface-muted)]">
                        Legacy mode uses the existing numeric fields marked as sale amounts. Use custom rules when this campaign's sale tag or amount field is different.
                    </div>

                    <div x-show="salesMode === 'custom'" x-cloak class="space-y-4">
                        @if($salesConfiguration['warnings'] ?? [])
                            <div class="rounded-lg border border-[var(--color-warning)]/40 bg-[var(--color-warning-muted)] p-3 text-xs text-[var(--color-warning-fg)]" role="status">
                                <p class="font-semibold">Some saved references need attention</p>
                                <ul class="mt-1 list-disc pl-4 space-y-0.5">
                                    @foreach($salesConfiguration['warnings'] as $warning)
                                        <li>{{ $warning }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <div>
                                <p class="text-xs font-semibold text-[var(--color-on-surface)]">Rules by form</p>
                                <p class="text-[11px] text-[var(--color-on-surface-dim)]">Choose a trigger for each form. Tag conditions use OR logic, and a matching submission is counted once.</p>
                            </div>
                            <button type="button" class="btn-secondary text-xs" @click="addRule()" :disabled="tagForms().length === 0">Add form rule</button>
                        </div>

                        <div x-show="formOptions.length === 0" class="rounded-lg border border-dashed border-[var(--color-border)] p-4 text-xs text-[var(--color-on-surface-dim)]">No active forms with registered tables are available for this campaign.</div>
                        <div x-show="salesRules.length === 0 && tagForms().length > 0" class="rounded-lg border border-dashed border-[var(--color-border)] p-4 text-xs text-[var(--color-on-surface-dim)]">Add a form rule to enable custom sales counting.</div>

                        <div class="space-y-4">
                            <template x-for="(rule, ruleIndex) in salesRules" :key="ruleIndex">
                                <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-2)] p-4 space-y-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 flex-1">
                                            <div>
                                                <label class="form-label" :for="'sales-form-' + ruleIndex">Form</label>
                                                <select class="form-select w-full" :id="'sales-form-' + ruleIndex" :name="'sales_forms[' + ruleIndex + '][form_code]'" x-model="rule.form_code" @change="changeRuleForm(rule)">
                                                    <template x-for="form in tagForms()" :key="form.code">
                                                        <option :value="form.code" x-text="form.name"></option>
                                                    </template>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label" :for="'sales-trigger-' + ruleIndex">Sales trigger</label>
                                                <select class="form-select w-full" :id="'sales-trigger-' + ruleIndex" :name="'sales_forms[' + ruleIndex + '][trigger]'" x-model="rule.trigger" @change="changeRuleTrigger(rule)">
                                                    <option value="form">Any form submission</option>
                                                    <option value="tag" :disabled="tagFields(rule.form_code).length === 0">Match Yes/No tag</option>
                                                    <option value="marked_amount" :disabled="markedAmountFields(rule.form_code).length === 0">Marked sale amount</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label" :for="'sales-amount-' + ruleIndex" x-text="ruleUsesMarkedAmount(rule) ? 'Marked sale amount field' : 'Amount field (optional)'"></label>
                                                <select class="form-select w-full" :id="'sales-amount-' + ruleIndex" :name="'sales_forms[' + ruleIndex + '][amount_field]'" x-model="rule.amount_field">
                                                    <option value="" x-show="!ruleUsesMarkedAmount(rule)">Count only</option>
                                                    <template x-for="field in ruleAmountFields(rule)" :key="field.name">
                                                        <option :value="field.name" x-text="field.label"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-icon text-[var(--color-danger-fg)]" @click="removeRule(ruleIndex)" aria-label="Remove form sales rule">&times;</button>
                                    </div>

                                    <div x-show="ruleUsesForm(rule)" x-cloak class="rounded-lg border border-[var(--color-primary)]/30 bg-[var(--color-primary-muted)] p-3 text-xs text-[var(--color-on-surface-muted)]">
                                        <p class="font-semibold text-[var(--color-primary)]">Form submission trigger</p>
                                        <p class="mt-1">Every submission of this form counts as one sale. The amount field is optional and only contributes to the sales amount total.</p>
                                        <button type="button" class="mt-2 text-[11px] text-[var(--color-primary)] hover:underline" x-show="tagFields(rule.form_code).length > 0" @click="rule.trigger = 'tag'; changeRuleTrigger(rule)">Use a Yes/No tag condition instead</button>
                                    </div>

                                    <div x-show="ruleUsesMarkedAmount(rule)" x-cloak class="rounded-lg border border-[var(--color-primary)]/30 bg-[var(--color-primary-muted)] p-3 text-xs text-[var(--color-on-surface-muted)]">
                                        <p class="font-semibold text-[var(--color-primary)]">Marked sale-amount trigger</p>
                                        <p class="mt-1">A submission qualifies when this marked numeric field contains a value. The submission is counted once and the field value is added to the sales amount.</p>
                                        <button type="button" class="mt-2 text-[11px] text-[var(--color-primary)] hover:underline" x-show="tagFields(rule.form_code).length > 0" @click="switchToTagRule(rule)">Use a text/select tag condition instead</button>
                                    </div>

                                    <div x-show="ruleUsesTag(rule)" x-cloak class="space-y-3">
                                        <template x-for="(condition, conditionIndex) in rule.conditions" :key="conditionIndex">
                                            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-3 space-y-3">
                                                <div class="flex items-center justify-between gap-2">
                                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[var(--color-on-surface-dim)]" x-text="'Condition ' + (conditionIndex + 1)"></p>
                                                    <button type="button" class="text-[11px] text-[var(--color-danger-fg)] hover:underline" @click="removeCondition(rule, conditionIndex)">Remove</button>
                                                </div>
                                                <div>
                                                    <label class="form-label" :for="'sales-condition-field-' + ruleIndex + '-' + conditionIndex">Tag field</label>
                                                    <select class="form-select w-full" :id="'sales-condition-field-' + ruleIndex + '-' + conditionIndex" :name="'sales_forms[' + ruleIndex + '][conditions][' + conditionIndex + '][field_name]'" x-model="condition.field_name">
                                                        <template x-for="field in tagFields(rule.form_code)" :key="field.name">
                                                            <option :value="field.name" x-text="field.label"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div class="space-y-2">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <label class="form-label mb-0">Accepted values</label>
                                                        <button type="button" class="text-[11px] text-[var(--color-primary)] hover:underline" @click="addAcceptedValue(condition)">Add value</button>
                                                    </div>
                                                    <template x-for="(value, valueIndex) in condition.accepted_values" :key="valueIndex">
                                                        <div class="flex items-center gap-2">
                                                            <input type="text" class="form-input flex-1" :name="'sales_forms[' + ruleIndex + '][conditions][' + conditionIndex + '][accepted_values][]'" x-model="condition.accepted_values[valueIndex]" placeholder="e.g. Yes">
                                                            <button type="button" class="btn-icon" @click="removeAcceptedValue(condition, valueIndex)" :aria-label="'Remove accepted value ' + (valueIndex + 1)">&times;</button>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                        <button type="button" class="btn-ghost text-xs" @click="addCondition(rule)">+ Add OR condition</button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] p-3 text-xs text-[var(--color-on-surface-muted)]">
                            <span class="font-semibold text-[var(--color-on-surface)]">Preview:</span>
                            <span x-text="salesRules.length + ' form rule' + (salesRules.length === 1 ? '' : 's') + ' • ' + salesRules.reduce((total, rule) => total + rule.conditions.length, 0) + ' OR condition' + (salesRules.reduce((total, rule) => total + rule.conditions.length, 0) === 1 ? '' : 's')"></span>
                        </div>
                    </div>
                </div>

                @if($errors->any())
                    <div class="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger-muted)] p-3 text-xs text-[var(--color-danger-fg)]" role="alert">
                        <p class="font-semibold">Review the dashboard settings</p>
                        <ul class="mt-1 list-disc pl-4 space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex items-center justify-between gap-3 flex-wrap pt-2">
                    <p class="text-xs text-[var(--color-on-surface-dim)]">Changes apply to users viewing {{ $campaignName }}.</p>
                    <div class="flex w-full flex-col items-stretch gap-2 sm:w-auto sm:flex-row sm:items-center">
                        <button type="button" class="btn-ghost text-xs" @click="if (window.confirm('Reset this campaign to legacy sale fields?')) salesMode = 'legacy'">Reset sales to legacy</button>
                        <button type="submit" class="btn-primary">Apply dashboard layout</button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    {{-- KPI stat cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4 animate-stagger">
        @foreach($stats as $formCode => $stat)
            <x-stat-card
                :label="$stat['name']"
                :value="number_format($stat['count'])"
                icon="document-text"
                color="primary"
                :href="route('admin.data-master.index', ['type' => $formCode])" />
        @endforeach
        <x-stat-card
            label="System Users"
            :value="number_format($userCount)"
            icon="users"
            color="info"
            :href="$user->isSuperAdmin() ? route('admin.users.index') : null" />
    </div>

    {{-- Charts row --}}
    @if(!empty($activityTrend['labels']))
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 chart-container">
            <p class="chart-title">Submission Activity — Last 30 days</p>
            <div id="admin-chart-activity" style="min-height: 240px;"></div>
        </div>
        <div class="chart-container">
            <p class="chart-title">Top Agents</p>
            <div id="admin-chart-agents" style="min-height: 240px;"></div>
        </div>
    </div>
    @endif

    {{-- Admin navigation grid --}}
    <div>
        <h3 class="text-xs font-bold text-[var(--color-on-surface-dim)] uppercase tracking-widest mb-4">Admin Tools</h3>
        @php
        $adminLinks = [
            ['route' => 'admin.records.index',            'icon' => 'table-cells',             'label' => 'Records List',         'desc' => 'Call history & submissions'],
            ['route' => 'admin.data-master.index',        'icon' => 'list-bullet',             'label' => 'Data Master',          'desc' => 'CRUD form data records'],
            ['route' => 'admin.disposition-records.index','icon' => 'clipboard-document-list', 'label' => 'Disposition Records',  'desc' => 'Lead & disposition log'],
            ['route' => 'admin.disposition-codes.index',  'icon' => 'tag',                     'label' => 'Disposition Codes',    'desc' => 'Manage codes per campaign'],
            ['route' => 'admin.field-logic.index',        'icon' => 'cog-6-tooth',             'label' => 'Field Logic',          'desc' => 'Form field schemas'],
            ['route' => 'admin.extraction.index',         'icon' => 'arrow-down-tray',         'label' => 'Data Extraction',      'desc' => 'Export to CSV'],
            ['route' => 'admin.attendance.index',         'icon' => 'clock',                   'label' => 'Staff Attendance',     'desc' => 'Login event history'],
        ];
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 animate-stagger">
            @foreach($adminLinks as $link)
                <a href="{{ route($link['route']) }}" class="md-card p-4 flex items-center gap-3 no-underline group">
                    <div class="w-10 h-10 rounded-lg bg-[var(--color-surface-2)] border border-[var(--color-border)] flex items-center justify-center shrink-0 group-hover:border-[var(--color-primary)] group-hover:bg-[var(--color-primary-muted)] transition-colors">
                        <x-icon :name="$link['icon']" class="w-5 h-5 text-[var(--color-on-surface-muted)] group-hover:text-[var(--color-primary)]" />
                    </div>
                    <div class="min-w-0">
                        <h4 class="font-semibold text-[var(--color-on-surface)] text-sm">{{ $link['label'] }}</h4>
                        <p class="text-xs text-[var(--color-on-surface-dim)] truncate">{{ $link['desc'] }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Super Admin section --}}
    @if($user->isSuperAdmin())
    <div>
        <h3 class="text-xs font-bold text-[var(--color-on-surface-dim)] uppercase tracking-widest mb-4">Super Admin</h3>
        @php
        $superLinks = [
            ['route' => 'admin.users.index',             'icon' => 'users',          'label' => 'User Access',       'desc' => 'Manage users & roles'],
            ['route' => 'admin.vicidial-servers.index',  'icon' => 'server',         'label' => 'ViciDial Servers',  'desc' => 'API & DB connections'],
            ['route' => 'admin.campaigns.index',         'icon' => 'building-office','label' => 'Campaigns',         'desc' => 'Manage campaigns'],
            ['route' => 'admin.forms.index',             'icon' => 'document-text',  'label' => 'Forms',             'desc' => 'Form definitions'],
            ['route' => 'admin.agent-screen.index',      'icon' => 'computer-desktop','label' => 'Agent Screen',     'desc' => 'Agent screen fields'],
            ['route' => 'admin.configuration',           'icon' => 'cog-6-tooth',    'label' => 'Configuration',     'desc' => 'System settings'],
        ];
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 animate-stagger">
            @foreach($superLinks as $link)
                @if($link['route'] !== 'admin.agent-screen.index' || $agentScreenVisible)
                <a href="{{ route($link['route']) }}" class="md-card p-4 flex items-center gap-3 no-underline group">
                    <div class="w-10 h-10 rounded-lg bg-[var(--color-danger-muted)] border border-[var(--color-border)] flex items-center justify-center shrink-0 group-hover:border-[var(--color-danger)] transition-colors">
                        <x-icon :name="$link['icon']" class="w-5 h-5 text-[var(--color-danger-fg)]" />
                    </div>
                    <div class="min-w-0">
                        <h4 class="font-semibold text-[var(--color-on-surface)] text-sm">{{ $link['label'] }}</h4>
                        <p class="text-xs text-[var(--color-on-surface-dim)] truncate">{{ $link['desc'] }}</p>
                    </div>
                </a>
                @endif
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
@if(!empty($activityTrend['labels']))
<script>
(async () => {
    const scope = window.crmSoftNav?.currentScope?.() || window.location.pathname;
    const chartGroup = 'admin-dashboard';

    function destroyCharts() {
        window.crmCharts?.destroyGroup?.(chartGroup);
    }

    async function renderCharts() {
        destroyCharts();

        if (document.readyState === 'loading') {
            await new Promise((resolve) => document.addEventListener('DOMContentLoaded', resolve, { once: true }));
        }

        const ApexCharts = await window.ApexChartsLoader?.() ?? null;
        if (!ApexCharts) {
            return;
        }

        const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
        const textColor = isDark ? '#a1a1aa' : '#52525b';
        const gridColor = isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.05)';

        const activityEl = document.getElementById('admin-chart-activity');
        if (activityEl) {
            const activity = new ApexCharts(activityEl, {
                series: [{ name: 'Submissions', data: @json($activityTrend['values'] ?? []) }],
                chart: { type: 'area', height: 240, toolbar: { show: false }, background: 'transparent', fontFamily: 'DM Sans, ui-sans-serif' },
                colors: ['#e91e8c'],
                fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .03 } },
                stroke: { curve: 'smooth', width: 2 },
                xaxis: { categories: @json($activityTrend['labels'] ?? []), labels: { style: { colors: textColor, fontSize: '11px' }, rotate: -30 }, axisBorder: { show: false }, axisTicks: { show: false } },
                yaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, min: 0 },
                grid: { borderColor: gridColor, strokeDashArray: 3 },
                tooltip: { theme: isDark ? 'dark' : 'light' },
                dataLabels: { enabled: false },
                theme: { mode: isDark ? 'dark' : 'light' },
            });
            window.crmCharts?.register?.(chartGroup, 'activity', activity);
            await activity.render();
        }

        const agentLabels = @json($topAgents['labels'] ?? []);
        const agentsEl = document.getElementById('admin-chart-agents');
        if (agentLabels.length && agentsEl) {
            const agents = new ApexCharts(agentsEl, {
                series: [{ name: 'Submissions', data: @json($topAgents['values'] ?? []) }],
                chart: { type: 'bar', height: 240, toolbar: { show: false }, background: 'transparent', fontFamily: 'DM Sans, ui-sans-serif' },
                colors: ['#e91e8c'],
                plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '55%' } },
                xaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, axisBorder: { show: false } },
                yaxis: { labels: { style: { colors: textColor, fontSize: '11px' }, maxWidth: 120 } },
                grid: { borderColor: gridColor, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
                tooltip: { theme: isDark ? 'dark' : 'light' },
                dataLabels: { enabled: false },
                theme: { mode: isDark ? 'dark' : 'light' },
                categories: agentLabels,
            });
            window.crmCharts?.register?.(chartGroup, 'agents', agents);
            await agents.render();
        }

        await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        window.crmCharts?.resizeGroup?.(chartGroup);
        requestAnimationFrame(() => window.crmCharts?.resizeGroup?.(chartGroup));
        setTimeout(() => window.crmCharts?.resizeGroup?.(chartGroup), 120);
        setTimeout(() => window.crmCharts?.resizeGroup?.(chartGroup), 360);
    }

    window.crmSoftNav?.register?.(scope, {
        beforeSwap: destroyCharts,
        afterSwap: () => {
            void renderCharts();
        },
    });

    if (!window.crmSoftNav?.isRehydrating?.()) {
        await renderCharts();
    }
})();
</script>
@endif
@endpush
