@extends('layouts.app')

@section('title', 'Leads - ' . $list->name)
@section('header-icon')<x-icon name="user-group" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Leads')

@section('content')
<x-page-header :title="'Leads: ' . $list->name" :description="'Campaign: ' . $list->campaign_code"
    :breadcrumbs="[
        'Admin' => route('admin.dashboard'),
        'Lead Lists' => route('admin.leads.lists.index', ['campaign' => $list->campaign_code]),
        $list->name => route('admin.leads.lists.show', $list),
        'Leads' => null,
    ]" />

<x-validation-errors />

{{-- Filters + actions --}}
<div class="md-card mb-4">
    <div class="p-4 flex flex-wrap items-end gap-3">
        <form method="GET" action="{{ route('admin.leads.leads.index', $list) }}" class="flex flex-wrap items-end gap-2 flex-1">
            <div>
                <label class="text-xs text-[var(--color-on-surface-dim)]">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, phone, email..." class="form-input">
            </div>
            <div>
                <label class="text-xs text-[var(--color-on-surface-dim)]">Status</label>
                <input type="text" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="NEW, DNC..." class="form-input">
            </div>
            <div>
                <label class="text-xs text-[var(--color-on-surface-dim)]">Enabled</label>
                <select name="enabled" class="form-select">
                    <option value="">Any</option>
                    <option value="1" @selected(($filters['enabled'] ?? '') === '1')>Yes</option>
                    <option value="0" @selected(($filters['enabled'] ?? '') === '0')>No</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary text-sm">
                <x-icon name="funnel" class="w-4 h-4" /> Filter
            </button>
        </form>
        <div class="flex gap-2">
            <a href="{{ route('admin.leads.leads.create', $list) }}" class="btn-primary text-sm">
                <x-icon name="plus" class="w-4 h-4" /> Add Lead
            </a>
        </div>
    </div>
</div>

{{-- Leads table with bulk actions --}}
<form method="POST" action="{{ route('admin.leads.leads.bulk', $list) }}" x-data="{ selected: [], toggleAll(ev){ const checked = ev.target.checked; document.querySelectorAll('.lead-row-cb').forEach(c => c.checked = checked); this.selected = checked ? Array.from(document.querySelectorAll('.lead-row-cb')).map(c => c.value) : []; } }">
    @csrf
    <div class="flex items-center gap-2 mb-2">
        <select name="action" class="form-select text-sm">
            <option value="">Bulk action...</option>
            <option value="enable">Enable</option>
            <option value="disable">Disable</option>
            <option value="mark_dnc">Mark DNC</option>
            <option value="reset_status">Reset status to NEW</option>
            <option value="delete">Delete</option>
        </select>
        <button type="submit" class="btn-secondary text-sm">Apply</button>
        <span class="text-xs text-[var(--color-on-surface-dim)]" x-text="selected.length + ' selected'"></span>
    </div>

    <x-table.index caption="Leads">
        <thead>
            <tr>
                <th class="w-8"><input type="checkbox" @change="toggleAll($event)"></th>
                @foreach($columns as $col)
                    <th class="text-left text-xs uppercase">{{ $headers[$col] ?? $col }}</th>
                @endforeach
                <th class="text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($leads as $lead)
                <tr>
                    <td><input type="checkbox" name="ids[]" value="{{ $lead->id }}" class="lead-row-cb" x-model="selected"></td>
                    @foreach($columns as $col)
                        <td class="text-sm">
                            @php
                                $value = array_key_exists($col, $lead->getAttributes())
                                    ? $lead->getAttribute($col)
                                    : data_get($lead->custom_fields ?? [], $col);
                            @endphp
                            @if(is_array($value))
                                <span class="font-mono text-xs">{{ json_encode($value) }}</span>
                            @elseif($col === 'status')
                                <x-badge :type="$value === 'DNC' ? 'danger' : ($value === 'NEW' ? 'active' : 'muted')">{{ $value }}</x-badge>
                            @elseif($col === 'enabled')
                                <x-badge :type="$value ? 'active' : 'inactive'">{{ $value ? 'Yes' : 'No' }}</x-badge>
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    @endforeach
                    <td>
                        <div class="table-actions">
                            <a href="#" class="btn-primary text-xs px-2 py-1"
                               onclick="event.preventDefault(); window.location='/agent?prefill_phone={{ urlencode($lead->phone_number) }}&prefill_lead_id={{ $lead->id }}&campaign={{ urlencode($list->campaign_code) }}'">
                                <x-icon name="phone" class="w-3.5 h-3.5" /> Dial
                            </a>
                            <a href="{{ route('admin.leads.leads.edit', ['list' => $list, 'lead' => $lead]) }}" class="btn-ghost text-xs px-2 py-1">
                                <x-icon name="pencil" class="w-3.5 h-3.5" /> Edit
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="count($columns) + 2" message="No leads match the current filters." />
            @endforelse
        </tbody>
    </x-table.index>
</form>

<div class="mt-4">
    {{ $leads->links() }}
</div>
@endsection
