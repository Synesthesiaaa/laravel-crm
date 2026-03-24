@extends('layouts.app')

@section('title', 'Pause Codes')
@section('header-icon')<x-icon name="pause" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Pause Codes')

@section('content')
<x-page-header title="Pause Codes"
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Pause Codes' => null]" />

<x-validation-errors />

<p class="text-sm text-[var(--color-on-surface-muted)] mb-6 max-w-3xl">
    Manage pause codes shown to agents in the VICIdial session panel. Codes must match what is allowed in VICIdial.
    When an agent sets a pause code, an attendance event is logged with that code.
</p>

<div class="md-card mb-6">
    <div class="px-6 py-4 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Add Pause Code</h3>
    </div>
    <div class="p-6">
        <form method="POST" action="{{ route('admin.pause-codes.store') }}"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <x-form.input name="code" label="Code" required placeholder="e.g. BREAK" maxlength="32" />
                <x-form.input name="label" label="Label" required placeholder="Coffee break" />
                <x-form.input name="sort_order" type="number" label="Sort Order" value="0" />
                <div class="form-field">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn-primary" :disabled="submitting">
                        <x-icon name="plus" class="w-4 h-4" />
                        Add
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<x-table.index caption="Pause codes">
    <x-table.head :columns="[
        ['label' => 'Code'],
        ['label' => 'Label'],
        ['label' => 'Order'],
        ['label' => 'Status'],
        ['label' => 'Actions', 'align' => 'right'],
    ]" />
    <tbody>
        @forelse($codes as $code)
            <tr>
                <td><span class="font-mono font-semibold text-sm text-[var(--color-on-surface)]">{{ $code->code }}</span></td>
                <td>{{ $code->label }}</td>
                <td>{{ $code->sort_order }}</td>
                <td>
                    <x-badge :type="$code->is_active ? 'active' : 'inactive'">
                        {{ $code->is_active ? 'Active' : 'Inactive' }}
                    </x-badge>
                </td>
                <td>
                    <div class="table-actions" x-data="{ editOpen: false }">
                        <button type="button" class="btn-secondary text-xs px-2 py-1" @click="editOpen = !editOpen">
                            <x-icon name="pencil" class="w-3.5 h-3.5" />
                            <span x-text="editOpen ? 'Cancel' : 'Edit'">Edit</span>
                        </button>
                        <div x-data="{ async del(form) {
                            const ok = await Alpine.store('confirm').ask('Delete pause code?', 'Remove {{ $code->code }}.');
                            if (ok) form.submit();
                        }}">
                            <form method="POST" action="{{ route('admin.pause-codes.destroy') }}" x-ref="delFormP{{ $code->id }}">
                                @csrf
                                <input type="hidden" name="id" value="{{ $code->id }}">
                                <button type="button" class="btn-danger text-xs px-2 py-1"
                                        @click="del($refs['delFormP{{ $code->id }}'])">
                                    <x-icon name="trash" class="w-3.5 h-3.5" />
                                    Delete
                                </button>
                            </form>
                        </div>
                        <div x-show="editOpen" x-collapse
                             style="display:none; position: absolute; right: 1rem; top: 100%; z-index: 20; background: var(--color-surface-2); border: 1px solid var(--color-border-strong); border-radius: 10px; padding: 1rem; min-width: 28rem; box-shadow: var(--shadow-3);">
                            <form method="POST" action="{{ route('admin.pause-codes.update', $code) }}"
                                  x-data="{ submitting: false }" @submit="submitting = true">
                                @csrf
                                @method('PUT')
                                <div class="grid grid-cols-1 gap-3">
                                    <x-form.input name="code" label="Code" :value="$code->code" required />
                                    <x-form.input name="label" label="Label" :value="$code->label" required />
                                    <x-form.input name="sort_order" type="number" label="Order" :value="$code->sort_order" />
                                    <div class="form-field flex items-center gap-2">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" id="is_active_{{ $code->id }}" value="1"
                                               class="rounded border-[var(--color-border)]" {{ $code->is_active ? 'checked' : '' }}>
                                        <label for="is_active_{{ $code->id }}" class="text-sm text-[var(--color-on-surface)]">Active</label>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="submit" class="btn-primary text-sm" :disabled="submitting">
                                        <x-icon name="check" class="w-4 h-4" />
                                        Update
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="5" message="No pause codes yet. Add one above or run the database seeder." />
        @endforelse
    </tbody>
</x-table.index>
@endsection
