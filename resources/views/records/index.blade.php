@extends('layouts.app')

@section('title', 'Call History')
@section('header-icon')<x-icon name="clipboard-document-list" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Call History')

@php
    $filterOptions = $historyPage->filterOptions;
    $statusOptions = array_combine($filterOptions['statuses'] ?? [], $filterOptions['statuses'] ?? []);
    $campaignOptions = array_combine($filterOptions['campaigns'] ?? [], $filterOptions['campaigns'] ?? []);
    $agentOptions = collect($filterOptions['agents'] ?? [])->mapWithKeys(
        static fn (array $agent): array => [$agent['value'] => $agent['label']],
    )->all();
    $formatDuration = static function (?int $seconds): string {
        if ($seconds === null) {
            return '—';
        }
        $seconds = max(0, $seconds);
        if ($seconds >= 3600) {
            return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        }
        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    };
    $statusBadge = static fn (?string $status): string => match (strtoupper((string) $status)) {
        'SALE' => 'active',
        'NEW', 'QUEUE', 'INCALL', 'INQUEUE' => 'pending',
        'DROP', 'ABANDON', 'NAN' => 'error',
        default => 'inactive',
    };
    $sortBy = request('sort', 'called_at');
    $sortDir = request('dir', 'desc');
    $hasFilters = request()->hasAny(['start_date', 'end_date', 'agent', 'phone', 'status', 'disposition', 'vicidial_campaign', 'direction']);
@endphp

@section('content')
<nav class="mb-4 text-sm text-[var(--color-on-surface-dim)]" aria-label="Breadcrumb">
    <span class="text-[var(--color-on-surface-muted)]">Call History</span>
</nav>

<div class="md-card mb-4 md-card--static">
    <div class="p-4">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div>
                <h2 class="text-sm font-semibold text-[var(--color-on-surface)]">Historical VICIdial calls</h2>
                <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">
                    Source: VICIdial historical logs · {{ count($filterOptions['campaigns'] ?? []) }} mapped campaign{{ count($filterOptions['campaigns'] ?? []) === 1 ? '' : 's' }}
                </p>
            </div>
            @if($historyPage->available)
                <x-badge type="active">Source available</x-badge>
            @else
                <x-badge type="error">Source unavailable</x-badge>
            @endif
        </div>
        <form method="GET" action="{{ route('records.index') }}" class="filter-row">
            <x-form.input name="start_date" type="date" label="Start Date" :value="request('start_date')" />
            <x-form.input name="end_date" type="date" label="End Date" :value="request('end_date')" />
            <x-form.select name="agent" label="Agent" :options="$agentOptions" :selected="request('agent')" empty="All agents" />
            <x-form.input name="phone" type="tel" label="Phone" :value="request('phone')" placeholder="Phone number" />
            <x-form.select name="status" label="Status" :options="$statusOptions" :selected="request('status')" empty="All statuses" />
            <x-form.select name="disposition" label="Disposition" :options="$filterOptions['dispositions'] ?? []" :selected="request('disposition')" empty="All dispositions" />
            <x-form.select name="vicidial_campaign" label="VICIdial Campaign" :options="$campaignOptions" :selected="request('vicidial_campaign')" empty="All mapped campaigns" />
            <x-form.select name="direction" label="Direction" :options="['OUTBOUND' => 'Outbound', 'INBOUND' => 'Inbound']" :selected="request('direction')" empty="All directions" />
            <div class="form-actions-bottom">
                <button type="submit" class="btn-primary">
                    <x-icon name="funnel" class="w-4 h-4" />
                    Filter
                </button>
                @if($hasFilters)
                    <a href="{{ route('records.index') }}" class="btn-ghost">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

@if(! $historyPage->available)
    <x-alert type="error" title="Call History unavailable" class="mb-4">
        {{ $historyPage->message }}
        <a href="{{ request()->fullUrl() }}" class="link-primary ml-1">Retry</a>
    </x-alert>
@endif

<x-table.index caption="Historical VICIdial call history">
    <x-table.head :sort-by="$sortBy" :sort-dir="$sortDir" :columns="[
        ['label' => 'Date/Time', 'key' => 'called_at', 'sortable' => true],
        ['label' => 'Agent', 'key' => 'agent', 'sortable' => true],
        ['label' => 'Phone'],
        ['label' => 'Status', 'key' => 'status', 'sortable' => true],
        ['label' => 'Disposition'],
        ['label' => 'Duration', 'key' => 'duration', 'sortable' => true],
        ['label' => 'Campaign', 'key' => 'vicidial_campaign', 'sortable' => true],
        ['label' => 'Details'],
    ]" />
    @if($historyPage->available && $history->isEmpty())
        <x-table.empty :colspan="8" message="No calls were found." description="Try a wider date range or clear one of the filters." />
    @elseif($historyPage->available)
    <tbody>
        @foreach($history as $row)
            <tr>
                <td class="whitespace-nowrap text-[var(--color-on-surface-muted)] text-sm">
                    {{ $row->callDate?->format('Y-m-d H:i') ?? '—' }}
                </td>
                <td>
                    <div>{{ $row->agentDisplayName }}</div>
                    @if($row->crmUserId === null && $row->vicidialUser)
                        <div class="text-xs text-[var(--color-on-surface-dim)]">{{ $row->vicidialUser }} · CRM user unavailable</div>
                    @elseif($row->vicidialUser)
                        <div class="text-xs text-[var(--color-on-surface-dim)]">{{ $row->vicidialUser }}</div>
                    @endif
                </td>
                <td class="font-mono text-sm">{{ $row->phoneNumber ?? '—' }}</td>
                <td><x-badge :type="$statusBadge($row->status)">{{ $row->status !== '' ? $row->status : 'Unknown' }}</x-badge></td>
                <td>{{ $row->dispositionLabel }}@if($row->dispositionCode) <span class="text-xs text-[var(--color-on-surface-dim)]">({{ $row->dispositionCode }})</span>@endif</td>
                <td class="font-mono text-sm">{{ $formatDuration($row->durationSeconds) }}</td>
                <td class="font-mono text-sm">{{ $row->vicidialCampaignId ?: '—' }}</td>
                <td>
                    <details class="text-sm">
                        <summary class="cursor-pointer text-[var(--color-primary)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">View</summary>
                        <dl class="mt-2 min-w-48 space-y-1 text-xs text-[var(--color-on-surface-dim)]">
                            <div class="flex justify-between gap-3"><dt>Direction</dt><dd>{{ $row->callDirection }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Lead ID</dt><dd>{{ $row->leadId ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>List ID</dt><dd>{{ $row->vicidialListId ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Wait time</dt><dd>{{ $formatDuration($row->waitSeconds) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>End reason</dt><dd>{{ $row->rawEndReason ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Call ID</dt><dd class="font-mono">{{ $row->uniqueCallId }}</dd></div>
                        </dl>
                    </details>
                </td>
            </tr>
        @endforeach
    </tbody>
    @else
        <x-table.empty :colspan="8" message="Call History could not be loaded." description="Retry when the VICIdial connection is available." />
    @endif
    <x-slot:footer>
        @if($historyPage->available)
            <x-table.pagination :paginator="$history" />
        @endif
    </x-slot:footer>
</x-table.index>
@endsection
