@extends('layouts.app')

@section('title', 'Records List - Admin')
@section('header-icon')<x-icon name="table-cells" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Records List')

@php
    $filterOptions = $callHistory?->filterOptions ?? [];
    $statusOptions = array_combine($filterOptions['statuses'] ?? [], $filterOptions['statuses'] ?? []);
    $campaignOptions = array_combine($filterOptions['campaigns'] ?? [], $filterOptions['campaigns'] ?? []);
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
    $tabParams = request()->except('page');
@endphp

@section('content')
<nav class="mb-4 text-sm text-[var(--color-on-surface-dim)]" aria-label="Breadcrumb">
    <a href="{{ route('admin.dashboard') }}" class="link-primary">Admin</a>
    <span class="mx-1.5">/</span>
    <span class="text-[var(--color-on-surface-muted)]">Records List</span>
</nav>

<div class="md-card mb-4 md-card--static">
    <div class="px-4 pt-4 border-b border-[var(--color-border)]">
        <div class="flex flex-wrap gap-2 overflow-x-auto" role="tablist" aria-label="Records list sections">
            <a href="{{ route('admin.records.index', array_merge($tabParams, ['tab' => 'submissions'])) }}"
               role="tab"
               aria-selected="{{ $activeTab === 'submissions' ? 'true' : 'false' }}"
               id="records-tab-submissions"
               class="px-4 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap {{ $activeTab === 'submissions' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-dim)] hover:text-[var(--color-on-surface)]' }}">
                Submitted Records
            </a>
            <a href="{{ route('admin.records.index', array_merge($tabParams, ['tab' => 'calls'])) }}"
               role="tab"
               aria-selected="{{ $activeTab === 'calls' ? 'true' : 'false' }}"
               id="records-tab-calls"
               class="px-4 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap {{ $activeTab === 'calls' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-dim)] hover:text-[var(--color-on-surface)]' }}">
                Call History
            </a>
        </div>
    </div>
    <div class="p-4">
        <form method="GET" action="{{ route('admin.records.index') }}" class="filter-row">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <x-form.input name="start_date" type="date" label="Start Date" :value="request('start_date')" />
            <x-form.input name="end_date" type="date" label="End Date" :value="request('end_date')" />
            <x-form.input name="agent" label="Agent" :value="request('agent')" placeholder="VICIdial user" />
            @if($activeTab === 'calls')
                <x-form.input name="phone" type="tel" label="Phone" :value="request('phone')" placeholder="Phone number" />
                <x-form.select name="status" label="Status" :options="$statusOptions" :selected="request('status')" empty="All statuses" />
                <x-form.select name="disposition" label="Disposition" :options="$filterOptions['dispositions'] ?? []" :selected="request('disposition')" empty="All dispositions" />
                <x-form.select name="vicidial_campaign" label="VICIdial Campaign" :options="$campaignOptions" :selected="request('vicidial_campaign')" empty="All mapped campaigns" />
                <x-form.select name="direction" label="Direction" :options="['OUTBOUND' => 'Outbound', 'INBOUND' => 'Inbound']" :selected="request('direction')" empty="All directions" />
            @endif
            <div class="form-actions-bottom">
                <button type="submit" class="btn-primary"><x-icon name="funnel" class="w-4 h-4" /> Filter</button>
                <a href="{{ route('admin.records.index', ['tab' => $activeTab]) }}" class="btn-ghost">Clear</a>
            </div>
        </form>
    </div>
</div>

@if($activeTab === 'calls')
    @if(! $callHistory->available)
        <x-alert type="error" title="Call History unavailable" class="mb-4">
            {{ $callHistory->message }}
            <a href="{{ request()->fullUrl() }}" class="link-primary ml-1">Retry</a>
        </x-alert>
    @endif
    <x-table.index caption="Historical VICIdial call records" role="tabpanel" aria-labelledby="records-tab-calls">
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
        @if($callHistory->available && $callHistory->records->isEmpty())
            <x-table.empty :colspan="8" message="No calls were found." description="Try a wider date range or clear one of the filters." />
        @elseif($callHistory->available)
        <tbody>
            @foreach($callHistory->records as $row)
                <tr>
                    <td class="whitespace-nowrap text-[var(--color-on-surface-muted)] text-sm">{{ $row->callDate?->format('Y-m-d H:i') ?? '—' }}</td>
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
            @if($callHistory->available)
                <x-table.pagination :paginator="$callHistory->records" />
            @endif
        </x-slot:footer>
    </x-table.index>
@else
    <x-table.index caption="Submitted CRM records" role="tabpanel" aria-labelledby="records-tab-submissions">
        <x-table.head :columns="[
            ['label' => 'Date'],
            ['label' => 'Form'],
            ['label' => 'Agent'],
            ['label' => 'Phone'],
            ['label' => 'Status'],
        ]" />
        @if($submissions->isEmpty())
            <x-table.empty :colspan="5" message="No submitted records found." description="CRM form submissions for this campaign will appear here." />
        @else
        <tbody>
            @foreach($submissions as $row)
                <tr>
                    <td class="whitespace-nowrap text-[var(--color-on-surface-muted)] text-sm">{{ $row->created_at?->format('Y-m-d H:i') }}</td>
                    <td>{{ $row->form_type }}</td>
                    <td>{{ $row->agent }}</td>
                    <td class="font-mono text-sm">{{ $row->phone_number ?? '-' }}</td>
                    <td><x-badge type="active">{{ $row->status ?? 'RECORDED' }}</x-badge></td>
                </tr>
            @endforeach
        </tbody>
        @endif
        <x-slot:footer>
            <x-table.pagination :paginator="$submissions" />
        </x-slot:footer>
    </x-table.index>
@endif
@endsection
