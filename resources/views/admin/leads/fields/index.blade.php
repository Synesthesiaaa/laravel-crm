@extends('layouts.app')

@section('title', 'Lead Fields')
@section('header-icon')<x-icon name="adjustments-horizontal" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Lead Fields')

@section('content')
<x-page-header title="Lead Fields" description="Customize which columns appear on lead lists, imports, and exports per campaign."
    :breadcrumbs="[
        'Admin' => route('admin.dashboard'),
        'Lead Lists' => route('admin.leads.lists.index', ['campaign' => $filterCampaign]),
        'Fields' => null,
    ]" />

<x-validation-errors />

<div class="md-card mb-4">
    <div class="p-4 flex flex-wrap items-end gap-3">
        <form method="GET" action="{{ route('admin.leads.fields.index') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="text-xs text-[var(--color-on-surface-dim)]">Campaign</label>
                <select name="campaign" class="form-select">
                    @foreach($campaigns as $c)
                        <option value="{{ $c->code }}" @selected($c->code === $filterCampaign)>{{ $c->name }} ({{ $c->code }})</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-secondary text-sm">
                <x-icon name="funnel" class="w-4 h-4" /> Filter
            </button>
        </form>
    </div>
</div>

<div class="md-card mb-6">
    <div class="px-6 py-4 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold">Add Custom Field</h3>
    </div>
    <div class="p-6">
        <form method="POST" action="{{ route('admin.leads.fields.store') }}" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <input type="hidden" name="campaign_code" value="{{ $filterCampaign }}">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <x-form.input name="field_key" label="Field Key (snake_case)" :value="old('field_key')" required placeholder="custom_note" />
                <x-form.input name="field_label" label="Label" :value="old('field_label')" required />
                <div>
                    <label class="text-xs text-[var(--color-on-surface-dim)]">Type</label>
                    <select name="field_type" class="form-select w-full">
                        <option value="text">Text</option>
                        <option value="number">Number</option>
                        <option value="email">Email</option>
                        <option value="date">Date</option>
                        <option value="select">Select</option>
                        <option value="textarea">Textarea</option>
                    </select>
                </div>
                <x-form.input name="field_order" type="number" label="Order" :value="old('field_order', 900)" />
            </div>
            <div class="mt-4 flex items-center gap-4">
                <x-form.checkbox name="visible" label="Visible" :checked="old('visible', true)" />
                <x-form.checkbox name="exportable" label="Exportable" :checked="old('exportable', true)" />
                <x-form.checkbox name="importable" label="Importable" :checked="old('importable', true)" />
                <button type="submit" class="btn-primary" :disabled="submitting">
                    <x-icon name="plus" class="w-4 h-4" /> Add Field
                </button>
            </div>
        </form>
    </div>
</div>

<x-table.index caption="Configured fields">
    <x-table.head :columns="[
        ['label' => 'Order'],
        ['label' => 'Key'],
        ['label' => 'Label'],
        ['label' => 'Type'],
        ['label' => 'Visible'],
        ['label' => 'Import'],
        ['label' => 'Export'],
        ['label' => 'Actions', 'align' => 'right'],
    ]" />
    <tbody>
        @forelse($fields as $field)
            <tr>
                <td class="text-xs">{{ $field->field_order }}</td>
                <td class="font-mono text-xs">
                    {{ $field->field_key }}
                    @if($field->is_standard)<span class="text-[var(--color-on-surface-dim)] text-[10px]">(standard)</span>@endif
                </td>
                <td>{{ $field->field_label }}</td>
                <td class="text-xs">{{ $field->field_type }}</td>
                <td>
                    <x-badge :type="$field->visible ? 'active' : 'inactive'">{{ $field->visible ? 'Yes' : 'No' }}</x-badge>
                </td>
                <td>
                    <x-badge :type="$field->importable ? 'active' : 'inactive'">{{ $field->importable ? 'Yes' : 'No' }}</x-badge>
                </td>
                <td>
                    <x-badge :type="$field->exportable ? 'active' : 'inactive'">{{ $field->exportable ? 'Yes' : 'No' }}</x-badge>
                </td>
                <td>
                    <div class="table-actions" x-data="{ open: false }">
                        <button type="button" class="btn-ghost text-xs px-2 py-1" @click="open = !open">
                            <x-icon name="pencil" class="w-3.5 h-3.5" /> Edit
                        </button>
                        @unless($field->is_standard)
                            <form method="POST" action="{{ route('admin.leads.fields.destroy') }}" class="inline">
                                @csrf
                                <input type="hidden" name="id" value="{{ $field->id }}">
                                <button type="submit" class="btn-danger text-xs px-2 py-1">
                                    <x-icon name="trash" class="w-3.5 h-3.5" /> Delete
                                </button>
                            </form>
                        @endunless
                        <div x-show="open" class="mt-2" x-collapse>
                            <form method="POST" action="{{ route('admin.leads.fields.update', $field->id) }}">
                                @csrf @method('PUT')
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                                    <x-form.input name="field_label" :value="$field->field_label" required />
                                    <select name="field_type" class="form-select">
                                        @foreach(['text','number','email','date','select','textarea'] as $t)
                                            <option value="{{ $t }}" @selected($field->field_type === $t)>{{ $t }}</option>
                                        @endforeach
                                    </select>
                                    <x-form.input name="field_order" type="number" :value="$field->field_order" />
                                    <button type="submit" class="btn-primary text-xs">Save</button>
                                </div>
                                <div class="mt-2 flex items-center gap-3">
                                    <x-form.checkbox name="visible" label="Visible" :checked="$field->visible" />
                                    <x-form.checkbox name="importable" label="Importable" :checked="$field->importable" />
                                    <x-form.checkbox name="exportable" label="Exportable" :checked="$field->exportable" />
                                </div>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="8" message="No fields configured yet." />
        @endforelse
    </tbody>
</x-table.index>
@endsection
