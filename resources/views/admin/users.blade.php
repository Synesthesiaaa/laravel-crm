@extends('layouts.app')

@section('title', 'User Access')
@section('header-icon')<x-icon name="users" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'User Access')

@section('content')
<x-page-header title="User Access" description="Manage staff accounts and roles."
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'User Access' => null]" />

<x-validation-errors />

{{-- Add user form --}}
<x-admin.panel title="Add New User" description="Create a staff account and optionally configure its VICIdial and browser-calling defaults." class="mb-6">
        <form method="POST" action="{{ route('admin.users.store') }}"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <x-form.input name="username"  label="Username"  :value="old('username')"  required />
                <x-form.input name="full_name" label="Full Name" :value="old('full_name')" required />
                <x-form.input name="password" type="password" label="Password" autocomplete="new-password" required />
                <x-form.input name="password_confirmation" type="password" label="Confirm Password" autocomplete="new-password" required />
                <x-form.select name="role" label="Role"
                    :options="\App\Models\User::roleOptions()"
                    :selected="old('role', 'Agent')" :empty="false" />
            </div>
            <x-admin.user-telephony-fields :campaign-options="$campaignOptions ?? []" />
            <div class="mt-4">
                <button type="submit" class="btn-primary" :disabled="submitting">
                    <x-icon name="plus" class="w-4 h-4" />
                    <span x-text="submitting ? 'Adding...' : 'Add User'">Add User</span>
                </button>
            </div>
        </form>
</x-admin.panel>

{{-- Users table --}}
<x-table.index caption="User accounts">
    <x-table.head :columns="[
        ['label' => 'Username'],
        ['label' => 'Full Name'],
        ['label' => 'Role'],
        ['label' => 'Actions', 'align' => 'right'],
    ]" />
    @forelse($users as $usr)
    <tbody x-data="{ editOpen: {{ ($errors->any() && old('_editing') == $usr->id) ? 'true' : 'false' }} }">
            <tr>
                <td>
                    <div class="font-medium text-[var(--color-on-surface)]">{{ $usr->username }}</div>
                </td>
                <td>{{ $usr->full_name ?? $usr->name }}</td>
                <td>
                    <x-badge :type="$usr->role === 'Super Admin' ? 'error' : ($usr->role === 'Admin' ? 'warning' : ($usr->role === 'Team Leader' ? 'info' : 'inactive'))">
                        {{ $usr->role }}
                    </x-badge>
                </td>
                <td>
                    <div class="table-actions">
                        <button type="button" class="btn-secondary text-xs px-2 py-1" @click="editOpen = !editOpen">
                            <x-icon name="pencil" class="w-3.5 h-3.5" />
                            <span x-text="editOpen ? 'Cancel' : 'Edit'">Edit</span>
                        </button>
                        @if($usr->id !== auth()->id())
                        <div x-data="{ deleting: false, async del(form) {
                            if (this.deleting) return;
                            const ok = await Alpine.store('confirm').ask('Delete user?', 'Remove {{ $usr->username }} from the system. This cannot be undone.');
                            if (!ok) return;
                            this.deleting = true;
                            window.crmLockSubmitForm?.(form, 'Deleting...');
                            HTMLFormElement.prototype.submit.call(form);
                        }}">
                            <form method="POST" action="{{ route('admin.users.destroy') }}" x-ref="delForm{{ $usr->id }}">
                                @csrf
                                <input type="hidden" name="id" value="{{ $usr->id }}">
                                <button type="button" class="btn-danger text-xs px-2 py-1"
                                        :disabled="deleting"
                                        @click="del($refs['delForm{{ $usr->id }}'])">
                                    <x-icon name="trash" class="w-3.5 h-3.5" />
                                    Delete
                                </button>
                            </form>
                        </div>
                        @else
                            <span class="text-xs text-[var(--color-on-surface-dim)]">(you)</span>
                        @endif
                    </div>
                </td>
            </tr>
            {{-- Inline edit row --}}
            <tr x-show="editOpen" class="inline-edit-row" x-collapse>
                <td colspan="4">
                    <form method="POST" action="{{ route('admin.users.update', $usr) }}"
                          x-data="{ submitting: false }" @submit="submitting = true; $el.prepend(document.createElement('input')).name='_editing', $el.querySelector('input[name=_editing]').value='{{ $usr->id }}'">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                            <x-form.input name="username" label="Username" :value="old('username', $usr->username)" required />
                            <x-form.input name="full_name" label="Full Name" :value="old('full_name', $usr->full_name ?? $usr->name)" required />
                            <x-form.select name="role" label="Role"
                                :options="\App\Models\User::roleOptions()"
                                :selected="old('role', $usr->role)" :empty="false" />
                            <x-form.input name="password" type="password" label="New Password" autocomplete="new-password" help="Leave blank to keep" />
                            <x-form.input name="password_confirmation" type="password" label="Confirm" autocomplete="new-password" />
                        </div>
                        <x-admin.user-telephony-fields :user="$usr" :campaign-options="$campaignOptions ?? []" />
                        <div class="mt-4">
                            <button type="submit" class="btn-primary text-sm" :disabled="submitting">
                                <x-icon name="check" class="w-4 h-4" />
                                <span x-text="submitting ? 'Saving...' : 'Update User'">Update User</span>
                            </button>
                        </div>
                    </form>
                </td>
            </tr>
    </tbody>
        @empty
        <tbody><tr><td colspan="4" class="py-8 text-center text-[var(--color-on-surface-muted)]">No users yet.</td></tr></tbody>
        @endforelse
    @if($users instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <x-slot:footer>
            <x-table.pagination :paginator="$users" />
        </x-slot:footer>
    @endif
</x-table.index>
@endsection
