@extends('layouts.app')

@section('title', 'Lead Lists')
@section('header-icon')<x-icon name="queue-list" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Lead Lists')

@section('content')
<x-page-header title="Lead Lists" description="Manage ViciDial-style lead lists per campaign."
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Lead Lists' => null]" />

<x-validation-errors />

{{-- Campaign filter + quick actions --}}
<div class="md-card mb-4">
    <div class="p-4 flex flex-wrap items-end gap-3">
        <form method="GET" action="{{ route('admin.leads.lists.index') }}" class="flex flex-wrap items-end gap-2">
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
        <div class="ml-auto flex gap-2">
            <a href="{{ route('admin.leads.fields.index', ['campaign' => $filterCampaign]) }}" class="btn-ghost text-sm">
                <x-icon name="adjustments-horizontal" class="w-4 h-4" /> Manage Fields
            </a>
            <a href="{{ route('admin.leads.export.all', ['campaign' => $filterCampaign, 'format' => 'xlsx']) }}" class="btn-ghost text-sm">
                <x-icon name="arrow-down-tray" class="w-4 h-4" /> Export All
            </a>
        </div>
    </div>
</div>

{{-- Create list --}}
<div class="md-card mb-6">
    <div class="px-6 py-4 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">New Lead List</h3>
    </div>
    <div class="p-6">
        <form method="POST" action="{{ route('admin.leads.lists.store') }}" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <input type="hidden" name="campaign_code" value="{{ $filterCampaign }}">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-form.input name="name" label="Name" :value="old('name')" required placeholder="e.g. Hot Leads Q2" />
                <x-form.input name="description" label="Description" :value="old('description')" />
                <x-form.input name="display_order" type="number" label="Display Order" :value="old('display_order', 0)" />
            </div>
            <div class="mt-4 flex items-center gap-4">
                <x-form.checkbox name="active" label="Active" :checked="old('active', true)" />
                <button type="submit" class="btn-primary" :disabled="submitting">
                    <x-icon name="plus" class="w-4 h-4" /> Create
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Lists table --}}
<x-table.index caption="Lead lists">
    <x-table.head :columns="[
        ['label' => 'ID'],
        ['label' => 'Name'],
        ['label' => 'Campaign'],
        ['label' => 'Leads'],
        ['label' => 'Status'],
        ['label' => 'Actions', 'align' => 'right'],
    ]" />
    <tbody>
        @forelse($lists as $list)
            <tr>
                <td class="font-mono text-xs">#{{ $list->id }}</td>
                <td>
                    <a href="{{ route('admin.leads.lists.show', $list) }}" class="font-semibold text-[var(--color-primary)] hover:underline">
                        {{ $list->name }}
                    </a>
                    @if($list->description)
                        <div class="text-xs text-[var(--color-on-surface-dim)]">{{ Str::limit($list->description, 80) }}</div>
                    @endif
                </td>
                <td><span class="font-mono text-xs">{{ $list->campaign_code }}</span></td>
                <td>{{ number_format($list->leads_count ?? 0) }}</td>
                <td>
                    <x-badge :type="$list->active ? 'active' : 'inactive'">
                        {{ $list->active ? 'Enabled' : 'Disabled' }}
                    </x-badge>
                </td>
                <td>
                    <div class="table-actions">
                        <a href="{{ route('admin.leads.leads.index', $list) }}" class="btn-ghost text-xs px-2 py-1">
                            <x-icon name="list-bullet" class="w-3.5 h-3.5" /> Leads
                        </a>
                        <a href="{{ route('admin.leads.import.form', $list) }}" class="btn-ghost text-xs px-2 py-1">
                            <x-icon name="arrow-up-tray" class="w-3.5 h-3.5" /> Import
                        </a>
                        <a href="{{ route('admin.leads.export.download', ['list' => $list, 'format' => 'xlsx']) }}" class="btn-ghost text-xs px-2 py-1">
                            <x-icon name="arrow-down-tray" class="w-3.5 h-3.5" /> Export
                        </a>
                        <form method="POST" action="{{ route('admin.leads.lists.toggle', $list) }}" class="inline">
                            @csrf
                            <input type="hidden" name="active" value="{{ $list->active ? 0 : 1 }}">
                            <button type="submit" class="{{ $list->active ? 'btn-warning' : 'btn-primary' }} text-xs px-2 py-1">
                                <x-icon :name="$list->active ? 'pause' : 'play'" class="w-3.5 h-3.5" />
                                {{ $list->active ? 'Disable' : 'Enable' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.leads.lists.load-hopper', $list) }}" class="inline">
                            @csrf
                            <button type="submit" class="btn-secondary text-xs px-2 py-1" @if(!$list->active) disabled @endif>
                                <x-icon name="rocket-launch" class="w-3.5 h-3.5" /> Load Hopper
                            </button>
                        </form>
                        <div x-data="{ async del(form) {
                            const ok = await Alpine.store('confirm').ask('Delete list?', '{{ $list->name }} and all its leads will be removed.');
                            if (ok) form.submit();
                        }}">
                            <form method="POST" action="{{ route('admin.leads.lists.destroy') }}" x-ref="delFormL{{ $list->id }}">
                                @csrf
                                <input type="hidden" name="id" value="{{ $list->id }}">
                                <button type="button" class="btn-danger text-xs px-2 py-1"
                                        @click="del($refs['delFormL{{ $list->id }}'])">
                                    <x-icon name="trash" class="w-3.5 h-3.5" /> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="6" message="No lead lists yet for this campaign." />
        @endforelse
    </tbody>
</x-table.index>

<div class="mt-4">
    {{ $lists->links() }}
</div>
@endsection
