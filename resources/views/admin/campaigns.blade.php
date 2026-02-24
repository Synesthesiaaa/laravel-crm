@extends('layouts.app')

@section('title', 'Campaigns')
@section('header-icon')<x-icon name="building-office" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Campaigns')

@section('content')
<x-page-header title="Campaigns" description="Manage campaign settings."
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Campaigns' => null]" />

<x-validation-errors />

{{-- Add campaign form --}}
<div class="md-card mb-6">
    <div class="px-6 py-4 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Add Campaign</h3>
    </div>
    <div class="p-6">
        <form method="POST" action="{{ route('admin.campaigns.store') }}"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-form.input name="code" label="Code" :value="old('code')" required placeholder="e.g. SALES2024" />
                <x-form.input name="name" label="Name" :value="old('name')" required />
                <x-form.input name="description" label="Description" :value="old('description')" />
            </div>
            <div class="mt-4">
                <button type="submit" class="btn-primary" :disabled="submitting">
                    <x-icon name="plus" class="w-4 h-4" />
                    Add Campaign
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Campaigns table --}}
<x-table.index caption="Campaigns list">
    <x-table.head :columns="[
        ['label' => 'Code'],
        ['label' => 'Name'],
        ['label' => 'Status'],
        ['label' => 'Actions', 'align' => 'right'],
    ]" />
    <tbody>
        @forelse($campaigns as $c)
            <tr x-data="{ editOpen: false }">
                <td><span class="font-mono font-semibold text-[var(--color-on-surface)] text-sm">{{ $c->code }}</span></td>
                <td>{{ $c->name }}</td>
                <td>
                    <x-badge :type="$c->is_active ? 'active' : 'inactive'">
                        {{ $c->is_active ? 'Active' : 'Inactive' }}
                    </x-badge>
                </td>
                <td>
                    <div class="table-actions">
                        <button type="button" class="btn-secondary text-xs px-2 py-1" @click="editOpen = !editOpen">
                            <x-icon name="pencil" class="w-3.5 h-3.5" />
                            <span x-text="editOpen ? 'Cancel' : 'Edit'">Edit</span>
                        </button>
                        <a href="{{ route('admin.forms.index', ['campaign' => $c->code]) }}" class="btn-ghost text-xs px-2 py-1">
                            <x-icon name="document-text" class="w-3.5 h-3.5" />
                            Forms
                        </a>
                        <div x-data="{ async del(form) {
                            const ok = await Alpine.store('confirm').ask('Deactivate campaign?', '{{ $c->name }} will be disabled.');
                            if (ok) form.submit();
                        }}">
                            <form method="POST" action="{{ route('admin.campaigns.destroy') }}" x-ref="delFormC{{ $c->id }}">
                                @csrf
                                <input type="hidden" name="id" value="{{ $c->id }}">
                                <button type="button" class="btn-danger text-xs px-2 py-1"
                                        @click="del($refs['delFormC{{ $c->id }}'])">
                                    <x-icon name="minus" class="w-3.5 h-3.5" />
                                    Deactivate
                                </button>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
            <tr x-show="editOpen" class="inline-edit-row" x-collapse>
                <td colspan="4">
                    <form method="POST" action="{{ route('admin.campaigns.update', $c) }}"
                          x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <x-form.input name="code" label="Code" :value="old('code', $c->code)" required />
                            <x-form.input name="name" label="Name" :value="old('name', $c->name)" required />
                            <x-form.input name="description" label="Description" :value="old('description', $c->description)" />
                            <x-form.input name="display_order" type="number" label="Display Order" :value="old('display_order', $c->display_order)" />
                        </div>
                        <div class="mt-3 flex items-center gap-4">
                            <x-form.checkbox name="is_active" label="Active" :checked="$c->is_active" />
                            <button type="submit" class="btn-primary text-sm" :disabled="submitting">
                                <x-icon name="check" class="w-4 h-4" />
                                <span x-text="submitting ? 'Saving...' : 'Update'">Update</span>
                            </button>
                        </div>
                    </form>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="4" message="No campaigns yet." />
        @endforelse
    </tbody>
</x-table.index>
@endsection
