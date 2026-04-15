@extends('layouts.app')

@section('title', 'Attendance Statuses')
@section('header-icon')<x-icon name="clock" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Attendance Statuses')

@section('content')
<x-page-header title="Attendance Statuses"
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Attendance Statuses' => null]" />

<x-validation-errors />

<div class="md-card mb-6">
    <div class="px-6 py-4 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">{{ __('Add status') }}</h3>
        <p class="text-xs text-[var(--color-on-surface-muted)] mt-1">{{ __('Code: lowercase letters, numbers, underscores. Login/logout remain system-only.') }}</p>
    </div>
    <div class="p-6">
        <form method="POST" action="{{ route('admin.attendance-statuses.store') }}"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <x-form.input name="code" label="{{ __('Code') }}" required placeholder="e.g. training" />
                <x-form.input name="label" label="{{ __('Label') }}" required placeholder="{{ __('Training') }}" />
                <x-form.input name="sort_order" type="number" label="{{ __('Sort order') }}" value="0" />
                <div class="form-field">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn-primary" :disabled="submitting">
                        <x-icon name="plus" class="w-4 h-4" />
                        {{ __('Add') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<x-table.index caption="{{ __('Attendance status types') }}">
    <x-table.head :columns="[
        ['label' => __('Code')],
        ['label' => __('Label')],
        ['label' => __('Order')],
        ['label' => __('Status')],
        ['label' => __('Actions'), 'align' => 'right'],
    ]" />
    <tbody>
        @forelse($types as $type)
            <tr>
                <td><span class="font-mono font-semibold text-sm text-[var(--color-on-surface)]">{{ $type->code }}</span></td>
                <td>{{ $type->label }}</td>
                <td>{{ $type->sort_order }}</td>
                <td>
                    <x-badge :type="$type->is_active ? 'active' : 'inactive'">
                        {{ $type->is_active ? __('Active') : __('Inactive') }}
                    </x-badge>
                </td>
                <td>
                    <div class="table-actions" x-data="{ editOpen: false }">
                        <button type="button" class="btn-secondary text-xs px-2 py-1" @click="editOpen = !editOpen">
                            <x-icon name="pencil" class="w-3.5 h-3.5" />
                            <span x-text="editOpen ? '{{ __('Cancel') }}' : '{{ __('Edit') }}'">{{ __('Edit') }}</span>
                        </button>
                        @if($type->is_active)
                        <div x-data="{ async del(form) {
                            const ok = await Alpine.store('confirm').ask('{{ __('Deactivate?') }}', '{{ __('This status will no longer appear for new breaks.') }}');
                            if (ok) form.submit();
                        }}">
                            <form method="POST" action="{{ route('admin.attendance-statuses.destroy') }}" x-ref="delForm{{ $type->id }}">
                                @csrf
                                <input type="hidden" name="id" value="{{ $type->id }}">
                                <button type="button" class="btn-danger text-xs px-2 py-1"
                                        @click="del($refs['delForm{{ $type->id }}'])">
                                    <x-icon name="trash" class="w-3.5 h-3.5" />
                                    {{ __('Deactivate') }}
                                </button>
                            </form>
                        </div>
                        @endif
                        <div x-show="editOpen" x-collapse
                             style="display:none; position: absolute; right: 1rem; top: 100%; z-index: 20; background: var(--color-surface-2); border: 1px solid var(--color-border-strong); border-radius: 10px; padding: 1rem; min-width: 28rem; box-shadow: var(--shadow-3);">
                            <form method="POST" action="{{ route('admin.attendance-statuses.update', $type->id) }}"
                                  x-data="{ submitting: false }" @submit="submitting = true">
                                @csrf
                                @method('PUT')
                                <div class="grid grid-cols-3 gap-3">
                                    <x-form.input name="code" label="{{ __('Code') }}" :value="$type->code" />
                                    <x-form.input name="label" label="{{ __('Label') }}" :value="$type->label" />
                                    <x-form.input name="sort_order" type="number" label="{{ __('Order') }}" :value="$type->sort_order" />
                                </div>
                                <div class="mt-3 flex items-center gap-2">
                                    <label class="inline-flex items-center gap-2 text-sm text-[var(--color-on-surface-muted)]">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" class="rounded border-[var(--color-border-strong)]"
                                               @checked($type->is_active)>
                                        {{ __('Active') }}
                                    </label>
                                </div>
                                <div class="mt-3">
                                    <button type="submit" class="btn-primary text-sm" :disabled="submitting">
                                        <x-icon name="check" class="w-4 h-4" />
                                        {{ __('Update') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="5" message="{{ __('No attendance statuses.') }}" />
        @endforelse
    </tbody>
</x-table.index>
@endsection
