@extends('layouts.app')

@section('title', 'Forms - Admin')
@section('header-icon')
    <x-icon name="document-text" class="w-5 h-5 text-[var(--color-primary)]" />
@endsection
@section('header-title')
    Forms
@endsection

@section('content')
    @if(session('success'))
        <x-alert type="success" class="mb-4">{{ session('success') }}</x-alert>
    @endif
    @if(session('error'))
        <x-alert type="error" class="mb-4">{{ session('error') }}</x-alert>
    @endif
    <x-validation-errors />

    <x-page-header title="Forms"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Forms' => null]" />

    <div class="md-card mb-6 overflow-hidden">
        <div class="p-6 border-b border-[var(--color-border)]">
            <form method="GET" action="{{ route('admin.forms.index') }}" class="filter-row">
                <div class="form-field">
                    <label class="form-label">Campaign</label>
                    <select name="campaign" class="form-select">
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
        <div class="p-6 border-b border-[var(--color-border)] bg-[var(--color-surface-1)]">
            <h3 class="text-sm font-semibold text-[var(--color-on-surface)] mb-4">Add Form</h3>
            <form method="POST" action="{{ route('admin.forms.store') }}" class="form-grid">
                @csrf
                <input type="hidden" name="campaign_code" value="{{ $selectedCampaign }}">
                <div class="form-field">
                    <label class="form-label">Form Code</label>
                    <input type="text" name="form_code" value="{{ old('form_code') }}" required pattern="[a-z0-9_]+" class="form-input @error('form_code') error @enderror" placeholder="e.g. ezycash">
                    <p class="form-help">Use lowercase letters, numbers, and underscores.</p>
                </div>
                <div class="form-field">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="form-input @error('name') error @enderror">
                </div>
                <div class="form-field">
                    <label class="form-label">Table Name</label>
                    <input type="text" name="table_name" value="{{ old('table_name') }}" required class="form-input @error('table_name') error @enderror">
                </div>
                <div class="form-actions-bottom">
                    <button type="submit" class="btn-primary">
                        <x-icon name="plus" class="w-4 h-4" />
                        Add
                    </button>
                </div>
            </form>
        </div>
        <div class="table-scroll-wrap">
            <table role="grid">
                <thead>
                    <tr>
                        <th>Form Code</th>
                        <th>Name</th>
                        <th>Table</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($forms as $f)
                        <tr>
                            <td class="font-mono text-sm">{{ $f->form_code }}</td>
                            <td>{{ $f->name }}</td>
                            <td class="font-mono text-sm">{{ $f->table_name }}</td>
                            <td>
                                <div class="table-actions">
                                <button type="button" onclick="document.getElementById('edit-form-{{ $f->id }}').classList.toggle('hidden'); this.querySelector('span').textContent = document.getElementById('edit-form-{{ $f->id }}').classList.contains('hidden') ? 'Edit' : 'Cancel';" class="btn-secondary text-xs px-2 py-1">
                                    <x-icon name="pencil" class="w-3.5 h-3.5" />
                                    <span>Edit</span>
                                </button>
                                <a href="{{ route('admin.field-logic.index', ['form' => $f->form_code]) }}" class="btn-secondary text-xs px-2 py-1">Fields</a>
                                <form method="POST" action="{{ route('admin.forms.destroy') }}" onsubmit="return confirm('Deactivate this form?');">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $f->id }}">
                                    <button type="submit" class="btn-danger text-xs px-2 py-1">
                                        <x-icon name="trash" class="w-3.5 h-3.5" />
                                        Deactivate
                                    </button>
                                </form>
                                </div>
                            </td>
                        </tr>
                        <tr id="edit-form-{{ $f->id }}" class="hidden inline-edit-row">
                            <td colspan="4">
                                <form method="POST" action="{{ route('admin.forms.update', $f) }}" class="form-grid">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="campaign_code" value="{{ $f->campaign_code }}">
                                    <div class="form-field">
                                        <label class="form-label">Form Code</label>
                                        <input type="text" name="form_code" value="{{ old('form_code', $f->form_code) }}" required pattern="[a-z0-9_]+" class="form-input @error('form_code') error @enderror">
                                    </div>
                                    <div class="form-field">
                                        <label class="form-label">Name</label>
                                        <input type="text" name="name" value="{{ old('name', $f->name) }}" required class="form-input @error('name') error @enderror">
                                    </div>
                                    <div class="form-field">
                                        <label class="form-label">Table Name</label>
                                        <input type="text" name="table_name" value="{{ old('table_name', $f->table_name) }}" required class="form-input @error('table_name') error @enderror">
                                    </div>
                                    <label class="checkbox-row">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" id="form-active-{{ $f->id }}" {{ old('is_active', $f->is_active) ? 'checked' : '' }}>
                                        <span>Active</span>
                                    </label>
                                    <div class="form-actions-bottom">
                                        <button type="submit" class="btn-primary">
                                            <x-icon name="check" class="w-4 h-4" />
                                            Update
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="table-empty">
                                <x-icon name="document-text" class="w-10 h-10 mx-auto mb-2" />
                                <p class="font-medium text-sm">No forms for this campaign.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
