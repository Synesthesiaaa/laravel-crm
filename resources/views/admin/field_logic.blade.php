@extends('layouts.app')

@section('title', 'Field Logic - Admin')
@section('header-icon')
    <x-icon name="cog-6-tooth" class="w-5 h-5 text-[var(--color-primary)]" />
@endsection
@section('header-title', 'Field Logic')

@section('content')
    @if(session('success'))
        <x-alert type="success" class="mb-4">{{ session('success') }}</x-alert>
    @endif
    <x-validation-errors />
    @if(empty($forms))
        <x-alert type="warning" class="mb-4">No active forms are configured for this campaign.</x-alert>
    @endif

    @php
        $visibilityOperatorOptions = [
            'equals' => 'Equals',
            'not_equals' => 'Does not equal',
            'in' => 'Any of',
            'not_in' => 'None of',
        ];
        $visibilityFieldOptions = collect($fields)
            ->mapWithKeys(fn ($field) => [
                $field->field_name => $field->field_label !== ''
                    ? $field->field_label.' ('.$field->field_name.')'
                    : $field->field_name,
            ])->all();
    @endphp

    <nav class="mb-4 text-sm text-[var(--color-on-surface-dim)]" aria-label="Breadcrumb">
        <a href="{{ route('admin.dashboard') }}" class="link-primary">Admin</a>
        <span class="mx-1.5">/</span>
        <span class="text-[var(--color-on-surface-muted)]">Field Logic</span>
    </nav>

    <div class="md-card mb-6 md-card--static">
        <div class="p-4">
            <form method="GET" action="{{ route('admin.field-logic.index') }}" class="filter-row">
                <div class="form-field">
                    <label class="form-label" for="form-filter">Form</label>
                    <select id="form-filter" name="form" class="form-select max-w-xs">
                        @foreach($forms as $code => $config)
                            <option value="{{ $code }}" {{ $formType === $code ? 'selected' : '' }}>{{ $config['name'] ?? $code }}</option>
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
            <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Add field</h3>
        </div>
        <div class="p-6">
            @if(empty($forms))
                <div class="table-empty py-8">
                    <x-icon name="cog-6-tooth" class="w-10 h-10 mx-auto mb-2" />
                    <p class="text-sm font-medium">No active forms are configured for this campaign.</p>
                </div>
            @else
            <form method="POST" action="{{ route('admin.field-logic.store') }}" class="space-y-4" x-data="{ submitting: false, ft: @js(old('field_type', 'text')) }" @submit="submitting = true">
                @csrf
                <input type="hidden" name="campaign_code" value="{{ $campaign }}">
                <input type="hidden" name="form_type" value="{{ $formType }}">
                <div class="filter-row">
                    <div class="form-field">
                        <label class="form-label">Field name</label>
                        <input type="text" name="field_name" value="{{ old('field_name') }}" required class="form-input" placeholder="column_name" pattern="[a-zA-Z0-9_]+" title="Letters, numbers, underscores only">
                    </div>
                    <div class="form-field">
                        <label class="form-label">Label</label>
                        <input type="text" name="field_label" value="{{ old('field_label') }}" required class="form-input" placeholder="Display Label">
                    </div>
                    <div class="form-field">
                        <label class="form-label">Type</label>
                        <select name="field_type" class="form-select" x-model="ft">
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
                        <label class="form-label">Width</label>
                        <select name="field_width" class="form-select">
                            <option value="full">Full</option>
                            <option value="half">Half</option>
                            <option value="third">Third</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Order</label>
                        <input type="number" name="field_order" class="form-input w-24" value="{{ old('field_order') }}" placeholder="auto" min="0" step="1">
                    </div>
                    <div class="form-actions-bottom">
                        <label class="checkbox-row">
                            <input type="checkbox" name="is_required" value="1" id="add_req" @checked(old('is_required'))>
                            <span>Required</span>
                        </label>
                        <button type="submit" class="btn-primary" :disabled="submitting">
                            <x-icon name="plus" class="w-4 h-4" />
                            Add
                        </button>
                    </div>
                </div>
                <div class="form-field max-w-xl" x-show="ft === 'select' || ft === 'multiselect'" x-cloak>
                    <label class="form-label">Options <span class="text-[var(--color-on-surface-muted)] font-normal">(one per line)</span></label>
                    <textarea name="options" rows="4" class="form-textarea font-mono text-sm" placeholder="Option A&#10;Option B&#10;Option C">{{ old('options') }}</textarea>
                    <p class="form-help mt-1">Required for single select and multi-select fields.</p>
                </div>
                <div class="rounded-lg border border-[var(--color-border)] p-4 max-w-3xl">
                    <p class="text-sm font-medium text-[var(--color-on-surface)] mb-3">Show When (optional)</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-form.select name="visibility[field]" label="Source Field" :options="$visibilityFieldOptions" :selected="old('visibility.field')" empty="— None —" />
                        <x-form.select name="visibility[operator]" label="Operator" :options="$visibilityOperatorOptions" :selected="old('visibility.operator')" empty="— None —" />
                        <div class="form-field sm:col-span-3">
                            <x-form.textarea name="visibility[values][0]" label="Values (comma or newline separated)" :value="old('visibility.values.0')" rows="3" placeholder="Yes&#10;No" />
                        </div>
                    </div>
                </div>
            </form>
            @endif
        </div>
    </div>

    <x-table.index caption="Form fields">
        <x-table.head :columns="[
            ['label' => 'Order'],
            ['label' => 'Name'],
            ['label' => 'Label'],
            ['label' => 'Type'],
            ['label' => 'Width'],
            ['label' => 'Required'],
            ['label' => 'Actions', 'align' => 'right'],
        ]" />
        <tbody>
            @forelse($fields as $f)
                <tr>
                    <td class="text-[var(--color-on-surface-dim)]">{{ $f->field_order }}</td>
                    <td class="font-mono text-sm">{{ $f->field_name }}</td>
                    <td>{{ $f->field_label }}</td>
                    <td>{{ $f->field_type }}</td>
                    <td>{{ $f->field_width ?? 'full' }}</td>
                    <td>
                        <x-badge :type="$f->is_required ? 'info' : 'inactive'">
                            {{ $f->is_required ? 'Yes' : 'No' }}
                        </x-badge>
                    </td>
                    <td>
                        <div class="table-actions"
                             x-data="{ deleting: false, async doDelete(el) {
                                if (this.deleting) return;
                                const ok = await Alpine.store('confirm').ask('Delete field?', 'Remove this field? This cannot be undone.');
                                if (!ok) return;
                                this.deleting = true;
                                window.crmLockSubmitForm?.(el, 'Deleting...');
                                HTMLFormElement.prototype.submit.call(el);
                             }}">
                            <a href="{{ route('admin.field-logic.edit', $f) }}" class="btn-secondary text-xs px-2 py-1">
                                <x-icon name="pencil" class="w-3.5 h-3.5" />
                                Edit
                            </a>
                            <form method="POST" action="{{ route('admin.field-logic.destroy') }}" x-ref="delForm{{ $f->id }}" class="inline">
                                @csrf
                                <input type="hidden" name="id" value="{{ $f->id }}">
                                <button type="button" class="btn-danger text-xs px-2 py-1"
                                        :disabled="deleting"
                                        @click="doDelete($refs['delForm{{ $f->id }}'])">
                                    <x-icon name="trash" class="w-3.5 h-3.5" />
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7" message="No fields yet." description="Add a field above for this form." />
            @endforelse
        </tbody>
    </x-table.index>
@endsection
