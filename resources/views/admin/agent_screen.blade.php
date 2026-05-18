@extends('layouts.app')

@section('title', 'Agent Screen - Admin')
@section('header-icon')<x-icon name="adjustments-horizontal" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Agent Screen Configuration')

@section('content')
<x-page-header title="Agent Screen Configuration"
    description="Configure capture fields, Vicidial mappings, and synchronization direction."
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Agent Screen' => null]" />

<x-validation-errors />

@if(session('success'))
    <x-alert type="success" class="mb-4">
        {{ session('success') }}
    </x-alert>
@endif
@if(session('error'))
    <x-alert type="error" class="mb-4">
        {{ session('error') }}
    </x-alert>
@endif

@php
    $campaignOptions = collect($campaigns)->mapWithKeys(fn ($cfg, $code) => [$code => $cfg['name'] ?? $code])->all();
    $writeableViciFields = collect($viciFields ?? [])->filter(fn ($meta) => (bool) ($meta['writeable'] ?? false));
    $readonlyViciFields = collect($viciFields ?? [])->reject(fn ($meta) => (bool) ($meta['writeable'] ?? false));
    $fieldTypeOptions = [
        'text' => 'Text',
        'number' => 'Number',
        'email' => 'Email',
        'tel' => 'Phone',
        'date' => 'Date',
        'textarea' => 'Textarea',
        'select' => 'Select',
        'checkbox' => 'Checkbox',
    ];
    $directionOptions = [
        'get' => 'GET (autofill from Vicidial)',
        'post' => 'POST (push to Vicidial on save)',
        'both' => 'BOTH (autofill + push)',
        'none' => 'NONE (local only)',
    ];
    $widthOptions = ['full' => 'Full', 'half' => 'Half', 'third' => 'Third'];
    $oldViciField = old('vici_field');
    $oldViciIsCustom = $oldViciField && !array_key_exists($oldViciField, $viciFields ?? []);
@endphp

<div class="md-card mb-6">
    <div class="p-4">
        <form method="GET" action="{{ route('admin.agent-screen.index') }}" class="flex flex-wrap items-end gap-4">
            <x-form.select name="campaign" label="Campaign" :options="$campaignOptions" :selected="$selectedCampaign" />
            <div class="form-field">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn-secondary">
                    <x-icon name="funnel" class="w-4 h-4" />
                    Load
                </button>
            </div>
        </form>
    </div>
</div>

<div class="md-card mb-6">
    <div class="px-6 py-4 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Add Capture Field</h3>
    </div>
    <div class="p-6">
        <form method="POST" action="{{ route('admin.agent-screen.store') }}"
              x-data="{ fieldType: '{{ old('field_type', 'text') }}', useCustomVici: @js((bool) $oldViciIsCustom), submitting: false }"
              @submit="submitting = true">
            @csrf
            <input type="hidden" name="campaign_code" value="{{ $selectedCampaign }}">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-form.input name="field_key" label="Field Key" required placeholder="customer_email" />
                <x-form.input name="field_label" label="Label" required placeholder="Customer Email" />
                <x-form.select name="direction" label="Sync Direction" :options="$directionOptions" :selected="old('direction', 'get')" />
                <x-form.select name="field_type" label="Field Type" :options="$fieldTypeOptions" :selected="old('field_type', 'text')" x-model="fieldType" />
                <x-form.select name="field_width" label="Width" :options="$widthOptions" :selected="old('field_width', 'full')" />
                <x-form.input name="placeholder" label="Placeholder" :value="old('placeholder')" placeholder="Optional placeholder..." />
                <x-form.input name="field_order" type="number" label="Display Order" :value="old('field_order')" placeholder="Auto if blank" />
                <div class="form-field">
                    <label class="form-label">Required</label>
                    <x-form.checkbox name="is_required" label="Mark as required" :checked="old('is_required', false)" />
                </div>

                <div class="sm:col-span-2 lg:col-span-4 rounded-lg border border-[var(--color-border)] p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                        <p class="text-sm font-medium text-[var(--color-on-surface)]">Vicidial Field Mapping</p>
                        <label class="inline-flex items-center gap-2 text-xs text-[var(--color-on-surface-dim)]">
                            <input type="checkbox" class="w-4 h-4 accent-[var(--color-primary)]" x-model="useCustomVici">
                            Use custom field name
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="form-field" x-show="!useCustomVici" x-cloak>
                            <label class="form-label">Vicidial Field</label>
                            <select name="vici_field" class="form-select" x-bind:disabled="useCustomVici">
                                <option value="">-- Select field --</option>
                                @if($writeableViciFields->isNotEmpty())
                                    <optgroup label="Writeable">
                                        @foreach($writeableViciFields as $key => $meta)
                                            <option value="{{ $key }}" @selected(old('vici_field') == $key)>{{ $meta['label'] ?? $key }} ({{ $key }})</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if($readonlyViciFields->isNotEmpty())
                                    <optgroup label="Read-only">
                                        @foreach($readonlyViciFields as $key => $meta)
                                            <option value="{{ $key }}" @selected(old('vici_field') == $key)>{{ $meta['label'] ?? $key }} ({{ $key }})</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                        </div>
                        <x-form.input name="vici_field"
                                      label="Custom Vicidial Field"
                                      :value="old('vici_field')"
                                      placeholder="custom_1"
                                      x-show="useCustomVici"
                                      x-cloak
                                      x-bind:disabled="!useCustomVici" />
                    </div>
                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-2">
                        GET/BOTH reads from Vicidial into Capture Details. POST/BOTH pushes mapped values back on save.
                    </p>
                </div>

                <div class="sm:col-span-2 lg:col-span-4 form-field" x-show="fieldType === 'select'" x-cloak>
                    <x-form.textarea name="options" label="Select Options (one per line)" :value="old('options')" rows="4" placeholder="Option A&#10;Option B&#10;Option C" />
                </div>
            </div>

            <div class="mt-5">
                <button type="submit" class="btn-primary" :disabled="submitting">
                    <x-icon name="plus" class="w-4 h-4" />
                    Add Field
                </button>
            </div>
        </form>
    </div>
</div>

<x-table.index caption="Agent screen capture field list">
    <x-table.head :columns="[
        ['label' => 'Order'],
        ['label' => 'Key'],
        ['label' => 'Label'],
        ['label' => 'Vicidial Field'],
        ['label' => 'Direction'],
        ['label' => 'Type'],
        ['label' => 'Width'],
        ['label' => 'Required'],
        ['label' => 'Actions', 'align' => 'right'],
    ]" />
    <tbody>
        @forelse($fields as $f)
            @php
                $rowOptions = is_array($f->options) ? implode("\n", $f->options) : '';
                $rowViciIsCustom = $f->vici_field && !array_key_exists($f->vici_field, $viciFields ?? []);
            @endphp
            <tr>
                <td>{{ $f->field_order }}</td>
                <td><span class="font-mono text-xs">{{ $f->field_key }}</span></td>
                <td>{{ $f->field_label }}</td>
                <td>{{ $f->vici_field ?: '—' }}</td>
                <td>{{ strtoupper($f->direction ?? 'get') }}</td>
                <td>{{ strtoupper($f->field_type ?? 'text') }}</td>
                <td>{{ $f->field_width ?? 'full' }}</td>
                <td>
                    <x-badge :type="$f->is_required ? 'warning' : 'muted'" :dot="false">
                        {{ $f->is_required ? 'Yes' : 'No' }}
                    </x-badge>
                </td>
                <td>
                    <div class="table-actions relative" x-data="{ editOpen: false }">
                        <button type="button" class="btn-secondary text-xs px-2 py-1" @click="editOpen = !editOpen">
                            <x-icon name="pencil" class="w-3.5 h-3.5" />
                            <span x-text="editOpen ? 'Cancel' : 'Edit'">Edit</span>
                        </button>
                        <div x-data="{ async del(form) {
                            const ok = await Alpine.store('confirm').ask('Delete field?', 'Remove capture field {{ $f->field_key }}.');
                            if (ok) form.submit();
                        }}">
                            <form method="POST" action="{{ route('admin.agent-screen.destroy') }}" x-ref="deleteForm{{ $f->id }}">
                                @csrf
                                <input type="hidden" name="id" value="{{ $f->id }}">
                                <button type="button" class="btn-danger text-xs px-2 py-1" @click="del($refs['deleteForm{{ $f->id }}'])">
                                    <x-icon name="trash" class="w-3.5 h-3.5" />
                                    Delete
                                </button>
                            </form>
                        </div>

                        <div x-show="editOpen" x-collapse style="display:none; position: absolute; right: 1rem; top: 100%; z-index: 20; background: var(--color-surface-2); border: 1px solid var(--color-border-strong); border-radius: 10px; padding: 1rem; min-width: 34rem; box-shadow: var(--shadow-3);">
                            <form method="POST" action="{{ route('admin.agent-screen.update', $f) }}"
                                  x-data="{ fieldType: '{{ $f->field_type ?? 'text' }}', useCustomVici: @js((bool) $rowViciIsCustom), submitting: false }"
                                  @submit="submitting = true">
                                @csrf
                                @method('PUT')
                                <div class="grid grid-cols-2 gap-3">
                                    <x-form.input name="field_key" label="Field Key" :value="$f->field_key" required />
                                    <x-form.input name="field_label" label="Label" :value="$f->field_label" required />
                                    <x-form.select name="direction" label="Direction" :options="$directionOptions" :selected="$f->direction ?? 'get'" />
                                    <x-form.select name="field_type" label="Field Type" :options="$fieldTypeOptions" :selected="$f->field_type ?? 'text'" x-model="fieldType" />
                                    <x-form.select name="field_width" label="Width" :options="$widthOptions" :selected="$f->field_width ?? 'full'" />
                                    <x-form.input name="field_order" type="number" label="Order" :value="$f->field_order" />
                                    <x-form.input name="placeholder" label="Placeholder" :value="$f->placeholder" />
                                    <div class="form-field">
                                        <label class="form-label">Required</label>
                                        <x-form.checkbox name="is_required" label="Mark as required" :checked="$f->is_required" />
                                    </div>
                                </div>

                                <div class="mt-3 rounded-lg border border-[var(--color-border)] p-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                        <p class="text-xs font-medium text-[var(--color-on-surface)]">Vicidial Field Mapping</p>
                                        <label class="inline-flex items-center gap-2 text-xs text-[var(--color-on-surface-dim)]">
                                            <input type="checkbox" class="w-4 h-4 accent-[var(--color-primary)]" x-model="useCustomVici">
                                            Use custom
                                        </label>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="form-field" x-show="!useCustomVici" x-cloak>
                                            <label class="form-label">Vicidial Field</label>
                                            <select name="vici_field" class="form-select" x-bind:disabled="useCustomVici">
                                                <option value="">-- Select field --</option>
                                                @if($writeableViciFields->isNotEmpty())
                                                    <optgroup label="Writeable">
                                                        @foreach($writeableViciFields as $key => $meta)
                                                            <option value="{{ $key }}" @selected($f->vici_field === $key)>{{ $meta['label'] ?? $key }} ({{ $key }})</option>
                                                        @endforeach
                                                    </optgroup>
                                                @endif
                                                @if($readonlyViciFields->isNotEmpty())
                                                    <optgroup label="Read-only">
                                                        @foreach($readonlyViciFields as $key => $meta)
                                                            <option value="{{ $key }}" @selected($f->vici_field === $key)>{{ $meta['label'] ?? $key }} ({{ $key }})</option>
                                                        @endforeach
                                                    </optgroup>
                                                @endif
                                            </select>
                                        </div>
                                        <x-form.input name="vici_field"
                                                      label="Custom Vicidial Field"
                                                      :value="$f->vici_field"
                                                      placeholder="custom_1"
                                                      x-show="useCustomVici"
                                                      x-cloak
                                                      x-bind:disabled="!useCustomVici" />
                                    </div>
                                </div>

                                <div class="mt-3" x-show="fieldType === 'select'" x-cloak>
                                    <x-form.textarea name="options" label="Select Options (one per line)" :value="$rowOptions" rows="3" />
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn-primary text-sm" :disabled="submitting">
                                        <x-icon name="check" class="w-4 h-4" />
                                        Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="9" message="No capture fields configured yet." />
        @endforelse
    </tbody>
</x-table.index>
@endsection
