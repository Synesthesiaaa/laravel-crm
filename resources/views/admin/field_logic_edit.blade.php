@extends('layouts.app')

@section('title', 'Edit Field - Admin')
@section('header-icon')
    <x-icon name="cog-6-tooth" class="w-5 h-5 text-[var(--color-primary)]" />
@endsection
@section('header-title', 'Edit Field')

@section('content')
    @if(session('success'))
        <x-alert type="success" class="mb-4">{{ session('success') }}</x-alert>
    @endif
    <x-validation-errors />

    @php
        $visibility = is_array($field->visibility) ? $field->visibility : [];
        $visibilityOperatorOptions = [
            'equals' => 'Equals',
            'not_equals' => 'Does not equal',
            'in' => 'Any of',
            'not_in' => 'None of',
        ];
    @endphp

    <nav class="mb-4 text-sm text-[var(--color-on-surface-dim)]" aria-label="Breadcrumb">
        <a href="{{ route('admin.dashboard') }}" class="link-primary">Admin</a>
        <span class="mx-1.5">/</span>
        <a href="{{ route('admin.field-logic.index', ['form' => $formType]) }}" class="link-primary">Field Logic</a>
        <span class="mx-1.5">/</span>
        <span class="text-[var(--color-on-surface-muted)]">Edit {{ $field->field_label }}</span>
    </nav>

    <div class="md-card md-card--static mb-6">
        <div class="px-6 py-4 border-b border-[var(--color-border)]">
            <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Edit field</h3>
            <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">
                Form: <span class="font-mono">{{ $formType }}</span>
                · Campaign: <span class="font-mono">{{ $field->campaign_code }}</span>
            </p>
        </div>
        <div class="p-6">
            <form method="POST" action="{{ route('admin.field-logic.update', $field->id) }}"
                  class="space-y-6"
                  x-data="{ submitting: false, ft: @js(old('field_type', $field->field_type)) }"
                  @submit="submitting = true">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="form-field sm:col-span-2">
                        <label class="form-label" for="field_name">Field name</label>
                        <input id="field_name" type="text" name="field_name" value="{{ old('field_name', $field->field_name) }}" required class="form-input @error('field_name') error @enderror" pattern="[a-zA-Z0-9_]+" title="Letters, numbers, underscores only">
                    </div>
                    <div class="form-field sm:col-span-2">
                        <label class="form-label" for="field_label">Label</label>
                        <input id="field_label" type="text" name="field_label" value="{{ old('field_label', $field->field_label) }}" required class="form-input @error('field_label') error @enderror">
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="field_type">Type</label>
                        <select id="field_type" name="field_type" class="form-select" x-model="ft">
                            <option value="text">Text</option>
                            <option value="textarea">Textarea</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                            <option value="select">Select</option>
                            <option value="multiselect">Multi-select (checkboxes)</option>
                            <option value="percentage">Percentage (%)</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="field_width">Width</label>
                        <select id="field_width" name="field_width" class="form-select">
                            <option value="full" @selected(old('field_width', $field->field_width) === 'full')>Full</option>
                            <option value="half" @selected(old('field_width', $field->field_width) === 'half')>Half</option>
                            <option value="third" @selected(old('field_width', $field->field_width) === 'third')>Third</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="field_order">Order</label>
                        <input id="field_order" type="number" name="field_order" class="form-input" value="{{ old('field_order', $field->field_order) }}" min="0" step="1">
                    </div>
                    <div class="form-field flex items-center gap-2 pt-6">
                        <input type="checkbox" name="is_required" value="1" id="edit_req"
                               class="rounded border-[var(--color-border-strong)] accent-[var(--color-primary)]"
                               @checked(old('is_required', $field->is_required))>
                        <label for="edit_req" class="text-sm text-[var(--color-on-surface)]">Required</label>
                    </div>
                </div>

                <div class="form-field max-w-xl" x-show="ft === 'select' || ft === 'multiselect'" x-cloak>
                    <label class="form-label" for="options">Options <span class="text-[var(--color-on-surface-muted)] font-normal">(one per line)</span></label>
                    <textarea id="options" name="options" rows="4" class="form-textarea font-mono text-sm @error('options') error @enderror" placeholder="Option A&#10;Option B">{{ old('options', $field->optionsTextForAdmin()) }}</textarea>
                    <p class="form-help mt-1">Required for single select and multi-select fields.</p>
                </div>

                <div class="rounded-lg border border-[var(--color-border)] p-4 max-w-3xl">
                    <p class="text-sm font-medium text-[var(--color-on-surface)] mb-3">Show When (optional)</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-form.select name="visibility[field]" label="Source Field" :options="$visibilityFieldOptions" :selected="old('visibility.field', $visibility['field'] ?? '')" empty="— None —" />
                        <x-form.select name="visibility[operator]" label="Operator" :options="$visibilityOperatorOptions" :selected="old('visibility.operator', $visibility['operator'] ?? '')" empty="— None —" />
                        <div class="form-field sm:col-span-2">
                            <x-form.textarea name="visibility[values][]" label="Values (comma or newline separated)" :value="$visibilityValuesText" rows="3" placeholder="Yes&#10;No" />
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="btn-primary" :disabled="submitting">
                        <x-icon name="check" class="w-4 h-4" />
                        <span x-text="submitting ? 'Saving...' : 'Update field'">Update field</span>
                    </button>
                    <a href="{{ route('admin.field-logic.index', ['form' => $formType]) }}" class="btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
