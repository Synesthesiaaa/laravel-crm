@extends('layouts.app')

@section('title', 'Configuration - Admin')
@section('header-icon')<x-icon name="cog-6-tooth" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'System Configuration')

@section('content')
<x-page-header title="System Configuration"
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Configuration' => null]" />

<div class="md-card">
    <div class="flex gap-2 p-4 border-b border-[var(--color-border)]">
        <a href="?tab=general"
           class="{{ !in_array(($tab ?? ''), ['disposition', 'telephony', 'diagnostics', 'retention'], true) ? 'btn-primary' : 'btn-secondary' }} text-sm">
            General
        </a>
        <a href="?tab=disposition"
           class="{{ ($tab ?? '') === 'disposition' ? 'btn-primary' : 'btn-secondary' }} text-sm">
            Disposition
        </a>
        <a href="?tab=telephony"
           class="{{ ($tab ?? '') === 'telephony' ? 'btn-primary' : 'btn-secondary' }} text-sm">
            Telephony Features
        </a>
        <a href="?tab=diagnostics"
           class="{{ ($tab ?? '') === 'diagnostics' ? 'btn-primary' : 'btn-secondary' }} text-sm">
            Diagnostics
        </a>
        <a href="?tab=retention"
           class="{{ ($tab ?? '') === 'retention' ? 'btn-primary' : 'btn-secondary' }} text-sm">
            Data Retention
        </a>
    </div>
    <div class="p-6">
        @if(session('status'))
            <x-alert type="success" class="mb-4">
                {{ session('status') }}
            </x-alert>
        @endif

        @if(($tab ?? '') === 'disposition')
            <x-alert type="info">
                Disposition codes are managed per campaign from the
                <a href="{{ route('admin.disposition-codes.index') }}" class="link-primary">Disposition Codes</a> page.
            </x-alert>
        @elseif(($tab ?? '') === 'telephony')
            <form method="POST" action="{{ route('admin.configuration.telephony-features.update') }}" class="space-y-5">
                @csrf
                @php
                    $featureLabels = [
                        'session_controls' => 'ViciDial Session Controls (login, pause, pause code, logout)',
                        'ingroup_management' => 'In-group Management',
                        'transfer_controls' => 'Transfer and Conference Controls',
                        'recording_controls' => 'Recording Controls',
                        'dtmf_controls' => 'DTMF Keypad',
                        'callback_controls' => 'Callback Scheduling',
                        'lead_tools' => 'Lead Search and Lead Tools',
                        'predictive_dialing' => 'Predictive Dialing',
                    ];
                @endphp

                <x-alert type="info" title="Feature Gating">
                    Disabled features are hidden from agent screen and blocked at API level for non-Super Admin users.
                </x-alert>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($featureLabels as $featureKey => $featureLabel)
                        <label class="flex items-center justify-between rounded-lg border border-[var(--color-border)] p-3">
                            <span class="text-sm text-[var(--color-on-surface)]">{{ $featureLabel }}</span>
                            <input type="checkbox"
                                   name="features[{{ $featureKey }}]"
                                   value="1"
                                   class="h-4 w-4 rounded border-[var(--color-border)]"
                                   @checked(($telephonyFeatures[$featureKey] ?? true) === true)>
                        </label>
                    @endforeach
                </div>

                <div>
                    <button type="submit" class="btn-primary">Save Telephony Feature Access</button>
                </div>
            </form>
        @elseif(($tab ?? '') === 'diagnostics')
            <div x-data="telephonyDiagnostics()" x-init="init()">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Telephony Readiness Check</h3>
                    <button type="button" class="btn-primary text-sm" @click="run()" {!! 'x-bind:disabled="loading"' !!}>
                        <span {!! 'x-bind:class="loading ? \'animate-spin\' : \'\'"' !!}><x-icon name="arrow-path" class="w-4 h-4" /></span>
                        <span x-text="loading ? 'Checking...' : 'Run Diagnostics'">Run Diagnostics</span>
                    </button>
                </div>

        <x-alert type="info" class="mb-4">
            Checks live connectivity to ViciDial APIs, AMI, and validates per-campaign and per-agent credential completeness.
            No data is modified.
        </x-alert>

        <template x-if="callUrlLinks.length > 0">
            <div class="mb-4 space-y-3">
                <x-alert type="info" title="Vicidial Call URL Links">
                    Paste these absolute URLs into the matching Vicidial campaign or in-group call URL fields.
                    The <code class="font-mono text-xs">VAR</code> prefix keeps Vicidial macro substitution enabled.
                </x-alert>

                <div class="space-y-3">
                    <template x-for="link in callUrlLinks" :key="link.key">
                        <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-[var(--color-on-surface)]" x-text="link.label"></p>
                                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-1" x-text="link.vicidial_field"></p>
                                </div>
                                <button type="button"
                                        class="btn-secondary text-sm shrink-0"
                                        @click="copyLink(link)">
                                    <span x-text="copiedLinkKey === link.key ? 'Copied' : 'Copy URL'">Copy URL</span>
                                </button>
                            </div>

                            <div class="mt-3 rounded-md border border-dashed border-[var(--color-border)] bg-[var(--color-surface)] p-3">
                                <pre class="whitespace-pre-wrap break-all text-xs font-mono text-[var(--color-on-surface)]" x-text="link.url"></pre>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="checks.length === 0 && !loading">
            <p class="text-sm text-[var(--color-on-surface-dim)]">Click "Run Diagnostics" to test your ViciDial connection.</p>
        </template>

                <template x-if="loading && checks.length === 0">
                    <div class="flex items-center gap-2 text-sm text-[var(--color-on-surface-dim)]">
                        <x-icon name="arrow-path" class="w-4 h-4 animate-spin" />
                        Running checks...
                    </div>
                </template>

                <template x-if="checks.length > 0">
                    <div class="space-y-2">
                        <template x-for="check in checks" :key="check.label">
                            <div class="flex items-start gap-3 rounded-lg border p-3"
                                 :class="{
                                     'border-green-500/40 bg-green-500/5':  check.status === 'ok',
                                     'border-amber-500/40 bg-amber-500/5':  check.status === 'warn',
                                     'border-red-500/40 bg-red-500/5':      check.status === 'fail',
                                 }">
                                <div class="shrink-0 mt-0.5">
                                    <template x-if="check.status === 'ok'">
                                        <x-icon name="check-circle" class="w-5 h-5 text-green-500" />
                                    </template>
                                    <template x-if="check.status === 'warn'">
                                        <x-icon name="exclamation-triangle" class="w-5 h-5 text-amber-500" />
                                    </template>
                                    <template x-if="check.status === 'fail'">
                                        <x-icon name="x-circle" class="w-5 h-5 text-red-500" />
                                    </template>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-[var(--color-on-surface)]" x-text="check.label"></p>
                                    <p class="text-xs text-[var(--color-on-surface-muted)] mt-0.5 break-all" x-text="check.message"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        @elseif(($tab ?? '') === 'retention')
            @php
                $selectedRetentionForm = collect($retentionForms ?? [])->firstWhere('id', (int) ($selectedRetentionFormId ?? 0));
                $selectedRetentionPolicy = $selectedRetentionForm?->retentionPolicy;
                $retentionFromDate = old('from_date', $selectedRetentionPolicy?->from_date?->format('Y-m-d') ?? '');
                $retentionToDate = old('to_date', $selectedRetentionPolicy?->to_date?->format('Y-m-d') ?? '');
                $retentionIsActive = old('is_active', $selectedRetentionPolicy?->is_active ?? true);
                $retentionDeletionMode = old('deletion_mode', $selectedRetentionPolicy?->deletion_mode ?? 'whole_record');
                $retentionSelectedFields = old('selected_fields', $selectedRetentionPolicy?->selected_fields ?? []);
                $retentionSelectedFields = is_array($retentionSelectedFields) ? $retentionSelectedFields : [];
            @endphp

            <div class="space-y-6" x-data="{ mode: @js($retentionDeletionMode) }">
                <div x-show="mode === 'whole_record'" x-cloak>
                    <x-alert type="warning" title="Permanent deletion">
                        Retention cleanup permanently deletes complete records from the selected form when their record date is within the configured From and To dates. This cannot be undone.
                    </x-alert>
                </div>
                <div x-show="mode === 'selected_fields'" x-cloak>
                    <x-alert type="warning" title="Permanent field clearing">
                        Retention cleanup permanently clears the selected field values from records within the configured From and To dates. The records and unselected fields are preserved, but cleared values cannot be recovered.
                    </x-alert>
                </div>

                <form method="GET" action="{{ route('admin.configuration') }}" class="md-card md-card--static">
                    <input type="hidden" name="tab" value="retention">
                    <div class="p-4">
                        <div class="form-field max-w-xl">
                            <label class="form-label" for="retention-form-filter">Form</label>
                            <select id="retention-form-filter" name="retention_form" class="form-select" @change="$el.form.submit()">
                                @forelse($retentionForms ?? [] as $retentionForm)
                                    <option value="{{ $retentionForm->id }}" @selected((int) $selectedRetentionFormId === $retentionForm->id)>
                                        {{ $retentionForm->campaign_code }} — {{ $retentionForm->name }}
                                    </option>
                                @empty
                                    <option value="">No active forms configured</option>
                                @endforelse
                            </select>
                        </div>
                    </div>
                </form>

                @if($selectedRetentionForm)
                    <form method="POST" action="{{ route('admin.configuration.retention.store') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="form_id" value="{{ $selectedRetentionForm->id }}">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="form-field sm:col-span-2">
                                <span class="form-label">Deletion scope</span>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <label class="flex items-start gap-3 rounded-lg border border-[var(--color-border)] p-3 cursor-pointer">
                                        <input type="radio" name="deletion_mode" value="whole_record" x-model="mode" @checked($retentionDeletionMode === 'whole_record') class="mt-1">
                                        <span>
                                            <span class="block text-sm font-medium text-[var(--color-on-surface)]">Delete entire records</span>
                                            <span class="block text-xs text-[var(--color-on-surface-dim)] mt-1">Remove every field from matching form records.</span>
                                        </span>
                                    </label>
                                    <label class="flex items-start gap-3 rounded-lg border border-[var(--color-border)] p-3 cursor-pointer">
                                        <input type="radio" name="deletion_mode" value="selected_fields" x-model="mode" @checked($retentionDeletionMode === 'selected_fields') class="mt-1">
                                        <span>
                                            <span class="block text-sm font-medium text-[var(--color-on-surface)]">Clear selected fields only</span>
                                            <span class="block text-xs text-[var(--color-on-surface-dim)] mt-1">Preserve records and all fields that are not selected.</span>
                                        </span>
                                    </label>
                                </div>
                                @error('deletion_mode')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="form-field">
                                <label class="form-label" for="retention-from-date">From date</label>
                                <input id="retention-from-date" type="date" name="from_date" value="{{ $retentionFromDate }}" class="form-input @error('from_date') error @enderror" required>
                                @error('from_date')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="form-field">
                                <label class="form-label" for="retention-to-date">To date</label>
                                <input id="retention-to-date" type="date" name="to_date" value="{{ $retentionToDate }}" class="form-input @error('to_date') error @enderror" required>
                                @error('to_date')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="form-field sm:col-span-2 flex items-end pb-1">
                                <label class="checkbox-row">
                                    <input type="checkbox" name="is_active" value="1" @checked($retentionIsActive)>
                                    <span>Active automatic cleanup</span>
                                </label>
                            </div>
                        </div>
                        <div x-show="mode === 'selected_fields'" x-cloak class="rounded-lg border border-[var(--color-border)] p-4">
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Fields to clear</h3>
                                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">Choose one or more fields. The record and unselected fields will remain.</p>
                                </div>
                                <span class="text-xs text-[var(--color-on-surface-dim)]">{{ $selectedRetentionForm->form_code }}</span>
                            </div>
                            @if($selectedRetentionForm->formFields->isNotEmpty())
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                    @foreach($selectedRetentionForm->formFields as $field)
                                        <label class="flex items-start gap-3 rounded-md border border-[var(--color-border)] p-3 cursor-pointer">
                                            <input type="checkbox"
                                                   name="selected_fields[]"
                                                   value="{{ $field->field_name }}"
                                                   @checked(in_array($field->field_name, $retentionSelectedFields, true))
                                                   x-bind:disabled="mode !== 'selected_fields'"
                                                   class="mt-1 h-4 w-4 rounded border-[var(--color-border)]">
                                            <span class="min-w-0">
                                                <span class="block text-sm text-[var(--color-on-surface)]">{{ $field->field_label }}</span>
                                                <span class="block text-xs font-mono text-[var(--color-on-surface-dim)] truncate">{{ $field->field_name }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-[var(--color-on-surface-dim)]">No eligible fields are registered for this form.</p>
                            @endif
                            @error('selected_fields')<p class="form-error mt-3">{{ $message }}</p>@enderror
                        </div>
                        @error('form_id')<p class="form-error">{{ $message }}</p>@enderror
                        <div>
                            <button type="submit" class="btn-primary">Save Retention Policy</button>
                        </div>
                    </form>
                @endif

                <div>
                    <h3 class="text-sm font-semibold text-[var(--color-on-surface)] mb-3">Configured policies</h3>
                    <x-table.index caption="Configured data retention policies">
                        <x-table.head :columns="[
                            ['label' => 'Campaign'],
                            ['label' => 'Form'],
                            ['label' => 'Storage table'],
                            ['label' => 'Deletion scope'],
                            ['label' => 'Selected fields'],
                            ['label' => 'From date'],
                            ['label' => 'To date'],
                            ['label' => 'Status'],
                            ['label' => 'Last run'],
                            ['label' => 'Deleted'],
                            ['label' => 'Actions', 'align' => 'right'],
                        ]" />
                        <tbody>
                            @forelse($retentionPolicies ?? [] as $policy)
                                <tr>
                                    <td>{{ $policy->form?->campaign?->name ?? $policy->form?->campaign_code ?? '—' }}</td>
                                    <td>{{ $policy->form?->name ?? 'Form unavailable' }}</td>
                                    <td class="font-mono text-xs">{{ $policy->form?->table_name ?? '—' }}</td>
                                    <td>{{ $policy->deletion_mode === 'selected_fields' ? 'Clear selected fields' : 'Delete entire records' }}</td>
                                    <td class="max-w-xs text-xs">{{ $policy->selected_fields ? implode(', ', $policy->selected_fields) : '—' }}</td>
                                    <td>{{ $policy->from_date?->format('Y-m-d') ?? 'Any date' }}</td>
                                    <td>{{ $policy->to_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td>
                                        <x-badge :type="$policy->is_active ? 'active' : 'inactive'">
                                            {{ $policy->is_active ? 'Active' : 'Inactive' }}
                                        </x-badge>
                                    </td>
                                    <td>{{ $policy->last_run_at?->format('Y-m-d H:i') ?? 'Never' }}</td>
                                    <td>{{ number_format($policy->last_deleted_count) }}</td>
                                    <td>
                                        <div class="table-actions justify-end">
                                            @if($policy->form)
                                                <a href="{{ route('admin.configuration', ['tab' => 'retention', 'retention_form' => $policy->form_id]) }}" class="btn-secondary text-xs px-2 py-1">Edit</a>
                                            @endif
                                            @if($policy->is_active)
                                                <form method="POST" action="{{ route('admin.configuration.retention.deactivate', $policy) }}">
                                                    @csrf
                                                    <button type="submit" class="btn-danger text-xs px-2 py-1">Deactivate</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-table.empty :colspan="11" message="No retention policies configured." description="Choose an active form and date range above to begin." />
                            @endforelse
                        </tbody>
                    </x-table.index>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-stat-card label="Active Campaigns"
                    :value="count($campaigns ?? [])"
                    icon="building-office"
                    :href="route('admin.campaigns.index')" />
            </div>
            <div class="mt-6">
                <x-alert type="info" title="Configuration Note">
                    Campaigns and forms are loaded from the database. Use the
                    <a href="{{ route('admin.campaigns.index') }}" class="link-primary">Campaigns</a> and
                    <a href="{{ route('admin.forms.index') }}" class="link-primary">Forms</a> pages to manage them.
                </x-alert>
            </div>
        @endif
    </div>
</div>
@endsection

@if(($tab ?? '') === 'diagnostics')
@push('scripts')
<script>
window.telephonyDiagnostics = function () {
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
                Alpine.store('toast').success(`${link.label} copied to clipboard.`);
            } catch (e) {
                Alpine.store('toast').error('Could not copy the Vicidial URL.');
            }
        },
        async run() {
            this.loading = true;
            this.checks = [];
            try {
                const res = await window.axios.post('/admin/configuration/telephony-diagnostics');
                this.checks = res.data?.checks ?? [];
                this.callUrlLinks = res.data?.call_url_links ?? [];
                if (res.data?.ok) {
                    Alpine.store('toast').success('All telephony checks passed.');
                } else {
                    Alpine.store('toast').warning('Some checks need attention. See details below.');
                }
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Diagnostics request failed.');
            } finally {
                this.loading = false;
            }
        },
    };
};
</script>
@endpush
@endif
