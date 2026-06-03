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

    <nav class="mb-4 text-sm text-[var(--color-on-surface-dim)]" aria-label="Breadcrumb">
        <a href="{{ route('admin.dashboard') }}" class="link-primary">Admin</a>
        <span class="mx-1.5">/</span>
        <span class="text-[var(--color-on-surface-muted)]">Forms</span>
    </nav>

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

    <div class="md-card mb-6 md-card--static">
        <div class="px-6 py-4 border-b border-[var(--color-border)]">
            <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Add Form</h3>
        </div>
        <div class="p-6">
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
        </div>
    </div>

    <x-table.index caption="Campaign forms">
        <x-table.head :columns="[
            ['label' => 'Form Code'],
            ['label' => 'Name'],
            ['label' => 'Table'],
            ['label' => 'Actions', 'align' => 'right'],
        ]" />
        <tbody>
            @forelse($forms as $f)
                @php
                    $shouldOpenEdit = $errors->isNotEmpty()
                        && old('campaign_code') === $f->campaign_code
                        && (old('form_code') === $f->form_code || request()->routeIs('admin.forms.update'));
                @endphp
                <tr x-data="{ editOpen: @js($shouldOpenEdit) }">
                    <td><span class="font-mono font-semibold text-sm text-[var(--color-on-surface)]">{{ $f->form_code }}</span></td>
                    <td>{{ $f->name }}</td>
                    <td class="font-mono text-sm">{{ $f->table_name }}</td>
                    <td>
                        <div class="table-actions">
                            <button type="button" class="btn-secondary text-xs px-2 py-1" @click="editOpen = !editOpen">
                                <x-icon name="pencil" class="w-3.5 h-3.5" />
                                <span x-text="editOpen ? 'Cancel' : 'Edit'">Edit</span>
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
                <tr x-show="editOpen" x-collapse class="inline-edit-row">
                    <td colspan="4">
                        <form method="POST" action="{{ route('admin.forms.update', $f) }}"
                              x-data="{ submitting: false }" @submit="submitting = true">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="campaign_code" value="{{ $f->campaign_code }}">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="form-field">
                                    <label class="form-label">Form Code</label>
                                    <input type="text" name="form_code" value="{{ old('form_code', $f->form_code) }}" required pattern="[a-z0-9_]+" class="form-input @error('form_code') error @enderror">
                                </div>
                                <x-form.input name="name" label="Name" :value="old('name', $f->name)" required />
                                <x-form.input name="table_name" label="Table Name" :value="old('table_name', $f->table_name)" required />
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-4">
                                <x-form.checkbox name="is_active" label="Active" :checked="old('is_active', $f->is_active)" />
                                <button type="submit" class="btn-primary text-sm" :disabled="submitting">
                                    <x-icon name="check" class="w-4 h-4" />
                                    <span x-text="submitting ? 'Saving...' : 'Update'">Update</span>
                                </button>
                            </div>
                        </form>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="4" message="No forms for this campaign." description="Add a form above to get started." />
            @endforelse
        </tbody>
    </x-table.index>
@endsection
