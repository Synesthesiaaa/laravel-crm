@extends('layouts.app')

@section('title', 'Configuration - Admin')
@section('header-icon')<x-icon name="cog-6-tooth" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'System Configuration')

@section('content')
<x-page-header title="System Configuration"
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Configuration' => null]" />

<div class="md-card">
    <div class="flex max-w-full gap-2 overflow-x-auto p-4 border-b border-[var(--color-border)]">
        <a href="?tab=general"
           class="{{ !in_array(($tab ?? ''), ['branding', 'disposition', 'telephony', 'diagnostics', 'retention'], true) ? 'btn-primary' : 'btn-secondary' }} shrink-0 text-sm">
            General
        </a>
        <a href="?tab=branding"
           class="{{ ($tab ?? '') === 'branding' ? 'btn-primary' : 'btn-secondary' }} shrink-0 text-sm">
            Branding
        </a>
        <a href="?tab=disposition"
           class="{{ ($tab ?? '') === 'disposition' ? 'btn-primary' : 'btn-secondary' }} shrink-0 text-sm">
            Disposition
        </a>
        <a href="?tab=telephony"
           class="{{ ($tab ?? '') === 'telephony' ? 'btn-primary' : 'btn-secondary' }} shrink-0 text-sm">
            Telephony Features
        </a>
        <a href="?tab=diagnostics"
           class="{{ ($tab ?? '') === 'diagnostics' ? 'btn-primary' : 'btn-secondary' }} shrink-0 text-sm">
            Diagnostics
        </a>
        <a href="?tab=retention"
           class="{{ ($tab ?? '') === 'retention' ? 'btn-primary' : 'btn-secondary' }} shrink-0 text-sm">
            Data Retention
        </a>
    </div>
    <div class="p-6">
        @if(session('status'))
            <x-alert type="success" class="mb-4">
                {{ session('status') }}
            </x-alert>
        @endif

        @if(($tab ?? '') === 'branding')
            <div class="max-w-4xl space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-[var(--color-on-surface)]">Company Branding</h2>
                    <p class="mt-1 max-w-2xl text-sm text-[var(--color-on-surface-muted)]">
                        Customize how the CRM is identified across the browser, login screen, sidebar, and dashboard.
                    </p>
                </div>

                @if($errors->any())
                    <div id="branding-errors" role="alert" tabindex="-1" class="rounded-lg border border-red-500/40 bg-red-500/10 p-4 text-sm text-[var(--color-on-surface)]">
                        <p class="font-semibold">There is a problem with your branding changes.</p>
                        <ul class="mt-2 list-inside list-disc space-y-1 text-[var(--color-on-surface-muted)]">
                            @foreach($errors->keys() as $field)
                                <li><a class="link-primary" href="#branding-{{ $field }}">{{ $errors->first($field) }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.configuration.branding.update') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <section class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-2)] p-5">
                        <div class="form-field max-w-2xl">
                            <label class="form-label" for="branding-company_name">Company name <span class="text-[var(--color-danger)]">*</span></label>
                            <input id="branding-company_name"
                                   name="company_name"
                                   type="text"
                                   value="{{ old('company_name', $brandingSettings['name'] ?? '') }}"
                                   maxlength="{{ config('branding.max_company_name_length', 120) }}"
                                   required
                                   autocomplete="organization"
                                   aria-describedby="branding-company-name-help{{ $errors->has('company_name') ? ' branding-company_name-error' : '' }}"
                                   class="form-input w-full">
                            <p id="branding-company-name-help" class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Used in page titles, the login screen, dashboard welcome, and sidebar.</p>
                            @error('company_name')
                                <p id="branding-company_name-error" role="alert" class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </section>

                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <section class="rounded-xl border border-[var(--color-border)] p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Company logo</h3>
                                    <p class="mt-1 text-xs text-[var(--color-on-surface-muted)]">Shown on the login screen and sidebar.</p>
                                </div>
                                <x-brand :branding="$brandingSettings" variant="preview" :show-name="false" aria-label="Current company logo preview" />
                            </div>
                            <div class="form-field mt-5">
                                <label class="form-label" for="branding-logo">Upload logo</label>
                                <input id="branding-logo"
                                       name="logo"
                                       type="file"
                                       accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                                       aria-describedby="branding-logo-help{{ $errors->has('logo') ? ' branding-logo-error' : '' }}"
                                       class="form-input w-full cursor-pointer">
                                <p id="branding-logo-help" class="mt-1 text-xs text-[var(--color-on-surface-dim)]">PNG, JPG, JPEG, or WebP. Maximum {{ number_format(config('branding.max_logo_kilobytes', 5120) / 1024, 1) }} MB.</p>
                                @error('logo')
                                    <p id="branding-logo-error" role="alert" class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </section>

                        <section class="rounded-xl border border-[var(--color-border)] p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Browser tab icon</h3>
                                    <p class="mt-1 text-xs text-[var(--color-on-surface-muted)]">Shown in the browser tab and bookmarks.</p>
                                </div>
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] p-3">
                                    <img src="{{ $brandingSettings['favicon_url'] }}" alt="{{ ($brandingSettings['name'] ?? 'CRM').' favicon' }}" width="40" height="40" class="h-full w-full object-contain" decoding="async">
                                </div>
                            </div>
                            <div class="form-field mt-5">
                                <label class="form-label" for="branding-favicon">Upload favicon</label>
                                <input id="branding-favicon"
                                       name="favicon"
                                       type="file"
                                       accept=".png,.jpg,.jpeg,.webp,.ico,image/png,image/jpeg,image/webp,image/x-icon"
                                       aria-describedby="branding-favicon-help{{ $errors->has('favicon') ? ' branding-favicon-error' : '' }}"
                                       class="form-input w-full cursor-pointer">
                                <p id="branding-favicon-help" class="mt-1 text-xs text-[var(--color-on-surface-dim)]">PNG, JPG, JPEG, WebP, or ICO. Maximum {{ number_format(config('branding.max_favicon_kilobytes', 2048) / 1024, 1) }} MB.</p>
                                @error('favicon')
                                    <p id="branding-favicon-error" role="alert" class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </section>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary">Save Branding Changes</button>
                    </div>
                </form>
            </div>
        @elseif(($tab ?? '') === 'disposition')
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
                        'agent_screen_access' => 'Agent Screen Access',
                    ];
                @endphp

                <x-alert type="info" title="Feature Gating">
                    Disabled telephony features are hidden from the Agent Screen and blocked at API level for non-Super Admin users. Agent Screen Access also controls Agent Capture webforms.
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
                $retentionRunMode = old('run_mode', $selectedRetentionPolicy?->run_mode ?? 'recurring');
                $retentionRunAt = old('run_at', $selectedRetentionPolicy?->run_at?->format('Y-m-d\\TH:i') ?? '');
                $retentionRecurrence = old('recurrence', $selectedRetentionPolicy?->recurrence ?? 'daily');
                $retentionRunTime = old('run_time', $selectedRetentionPolicy?->run_time ? substr((string) $selectedRetentionPolicy->run_time, 0, 5) : '03:00');
                $retentionRunDayOfWeek = old('run_day_of_week', $selectedRetentionPolicy?->run_day_of_week ?? 1);
                $retentionRunDayOfMonth = old('run_day_of_month', $selectedRetentionPolicy?->run_day_of_month ?? 1);
            @endphp

            <div class="space-y-6" x-data="{ deletionMode: @js($retentionDeletionMode), executionMode: @js($retentionRunMode), recurrence: @js($retentionRecurrence) }">
                <div x-show="deletionMode === 'whole_record'" x-cloak>
                    <x-alert type="warning" title="Permanent deletion">
                        Retention cleanup permanently deletes complete records from the selected form when their record date is within the configured From and To dates. This cannot be undone.
                    </x-alert>
                </div>
                <div x-show="deletionMode === 'selected_fields'" x-cloak>
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
                                        <input type="radio" name="deletion_mode" value="whole_record" x-model="deletionMode" @checked($retentionDeletionMode === 'whole_record') class="mt-1">
                                        <span>
                                            <span class="block text-sm font-medium text-[var(--color-on-surface)]">Delete entire records</span>
                                            <span class="block text-xs text-[var(--color-on-surface-dim)] mt-1">Remove every field from matching form records.</span>
                                        </span>
                                    </label>
                                    <label class="flex items-start gap-3 rounded-lg border border-[var(--color-border)] p-3 cursor-pointer">
                                        <input type="radio" name="deletion_mode" value="selected_fields" x-model="deletionMode" @checked($retentionDeletionMode === 'selected_fields') class="mt-1">
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
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" @checked($retentionIsActive)>
                                    <span>Active automatic cleanup</span>
                                </label>
                            </div>
                            <div class="form-field sm:col-span-2 rounded-lg border border-[var(--color-border)] p-4 space-y-4">
                                <div>
                                    <span class="form-label">Execution mode</span>
                                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">Run Once supports a scheduled date/time and the Run Now action. Recurring policies can run daily, weekly, or monthly.</p>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <label class="flex items-start gap-3 rounded-lg border border-[var(--color-border)] p-3 cursor-pointer">
                                        <input type="radio" name="run_mode" value="once" x-model="executionMode" @checked($retentionRunMode === 'once') class="mt-1">
                                        <span>
                                            <span class="block text-sm font-medium text-[var(--color-on-surface)]">Run Once</span>
                                            <span class="block text-xs text-[var(--color-on-surface-dim)] mt-1">Schedule one automatic execution or run it immediately from the policy list.</span>
                                        </span>
                                    </label>
                                    <label class="flex items-start gap-3 rounded-lg border border-[var(--color-border)] p-3 cursor-pointer">
                                        <input type="radio" name="run_mode" value="recurring" x-model="executionMode" @checked($retentionRunMode === 'recurring') class="mt-1">
                                        <span>
                                            <span class="block text-sm font-medium text-[var(--color-on-surface)]">Recurring</span>
                                            <span class="block text-xs text-[var(--color-on-surface-dim)] mt-1">Keep the policy active and run it on a daily, weekly, or monthly schedule.</span>
                                        </span>
                                    </label>
                                </div>
                                @error('run_mode')<p class="form-error">{{ $message }}</p>@enderror

                                <div x-show="executionMode === 'once'" x-cloak class="form-field">
                                    <label class="form-label" for="retention-run-at">Scheduled run date and time</label>
                                    <input id="retention-run-at" type="datetime-local" name="run_at" value="{{ $retentionRunAt }}" class="form-input @error('run_at') error @enderror" x-bind:disabled="executionMode !== 'once'">
                                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">Use Run Now below when this one-time policy should execute immediately.</p>
                                    @error('run_at')<p class="form-error">{{ $message }}</p>@enderror
                                </div>

                                <div x-show="executionMode === 'recurring'" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="form-field">
                                        <label class="form-label" for="retention-recurrence">Frequency</label>
                                        <select id="retention-recurrence" name="recurrence" class="form-select" x-model="recurrence" x-bind:disabled="executionMode !== 'recurring'">
                                            <option value="daily">Daily</option>
                                            <option value="weekly">Weekly</option>
                                            <option value="monthly">Monthly</option>
                                        </select>
                                        @error('recurrence')<p class="form-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="form-field">
                                        <label class="form-label" for="retention-run-time">Run time</label>
                                        <input id="retention-run-time" type="time" name="run_time" value="{{ $retentionRunTime }}" class="form-input @error('run_time') error @enderror" x-bind:disabled="executionMode !== 'recurring'">
                                        @error('run_time')<p class="form-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div x-show="recurrence === 'weekly'" x-cloak class="form-field">
                                        <label class="form-label" for="retention-run-day-of-week">Day of week</label>
                                        <select id="retention-run-day-of-week" name="run_day_of_week" class="form-select" x-bind:disabled="executionMode !== 'recurring' || recurrence !== 'weekly'">
                                            @foreach([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'] as $dayValue => $dayLabel)
                                                <option value="{{ $dayValue }}" @selected((int) $retentionRunDayOfWeek === $dayValue)>{{ $dayLabel }}</option>
                                            @endforeach
                                        </select>
                                        @error('run_day_of_week')<p class="form-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div x-show="recurrence === 'monthly'" x-cloak class="form-field">
                                        <label class="form-label" for="retention-run-day-of-month">Day of month</label>
                                        <select id="retention-run-day-of-month" name="run_day_of_month" class="form-select" x-bind:disabled="executionMode !== 'recurring' || recurrence !== 'monthly'">
                                            @foreach(range(1, 31) as $dayValue)
                                                <option value="{{ $dayValue }}" @selected((int) $retentionRunDayOfMonth === $dayValue)>{{ $dayValue }}</option>
                                            @endforeach
                                        </select>
                                        <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">If a month is shorter, the last day of that month is used.</p>
                                        @error('run_day_of_month')<p class="form-error">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div x-show="deletionMode === 'selected_fields'" x-cloak class="rounded-lg border border-[var(--color-border)] p-4">
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
                                                   x-bind:disabled="deletionMode !== 'selected_fields'"
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
                            ['label' => 'Execution'],
                            ['label' => 'Next run'],
                            ['label' => 'Last run'],
                            ['label' => 'Result'],
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
                                        <span class="block text-xs text-[var(--color-on-surface-dim)] mt-1">
                                            {{ $policy->run_mode === 'once' ? 'Run Once' : 'Recurring '.ucfirst($policy->recurrence ?? 'daily') }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap">{{ $policy->next_run_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                    <td>{{ $policy->last_run_at?->format('Y-m-d H:i') ?? 'Never' }}</td>
                                    <td>
                                        <span class="block">{{ $policy->last_run_status ? ucfirst($policy->last_run_status) : 'Not run' }}</span>
                                        <span class="block text-xs text-[var(--color-on-surface-dim)]">{{ number_format($policy->last_deleted_count) }} affected</span>
                                        @if($policy->last_error)
                                            <span class="block max-w-xs text-xs text-red-500 truncate" title="{{ $policy->last_error }}">{{ $policy->last_error }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="table-actions justify-end flex-wrap">
                                            @if($policy->form)
                                                <a href="{{ route('admin.configuration', ['tab' => 'retention', 'retention_form' => $policy->form_id]) }}" class="btn-secondary text-xs px-2 py-1">Edit</a>
                                            @endif
                                            @if($policy->is_active)
                                                <form method="POST" action="{{ route('admin.configuration.retention.run', $policy) }}" onsubmit="return confirm('Run this retention policy now? This may permanently delete or clear matching data.')">
                                                    @csrf
                                                    <button type="submit" class="btn-danger text-xs px-2 py-1">Run Now</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('admin.configuration.retention.destroy', $policy) }}" onsubmit="return confirm('Delete only this retention policy configuration? Form data will not be changed.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-danger text-xs px-2 py-1">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-table.empty :colspan="13" message="No retention policies configured." description="Choose an active form, date range, and execution schedule above to begin." />
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
