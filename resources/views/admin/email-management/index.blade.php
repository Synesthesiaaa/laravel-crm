@extends('layouts.app')
@section('title', 'Email campaigns')
@section('header-title', 'Email campaigns')
@section('content')
<x-page-header title="Email campaigns" description="Prepare your audience, schedule delivery and follow each outcome.">
    <a class="btn-secondary" href="{{ route('admin.email-audiences.index') }}">Audiences</a>
    <a class="btn-secondary" href="{{ route('admin.email-templates.index') }}">Templates</a>
    @if(auth()->user()->isSuperAdmin())
        <a class="btn-secondary" href="{{ route('admin.email-configuration.index') }}">Email configuration</a>
    @endif
</x-page-header>
<x-validation-errors />
@if(session('success'))<x-alert type="success" class="mb-5">{{ session('success') }}</x-alert>@endif
@if($queueStatus['problem'])<x-alert type="warning" class="mb-5" title="Delivery needs attention">{{ $queueStatus['problem'] }}</x-alert>@endif
@if($preparationStatus['problem'])<x-alert type="warning" class="mb-5" title="Preparation needs attention">{{ $preparationStatus['problem'] }}</x-alert>@endif
@unless($schedulerHeartbeat)<x-alert type="warning" class="mb-5" title="Scheduler heartbeat unavailable">Scheduled campaigns wait for the server scheduler. Check its configuration before relying on a scheduled send.</x-alert>@endunless
@php
    $overviewMetrics = ['accepted' => 'SMTP accepted', 'delivered' => 'Confirmed delivered', 'failed' => 'Failed / blocked', 'bounced' => 'Bounced', 'opened' => 'Observed opens', 'clicked' => 'Observed clicks', 'unsubscribed' => 'Unsubscribed', 'uncertain' => 'Uncertain'];
@endphp
<dl class="mb-6 grid grid-cols-2 gap-x-6 gap-y-5 border-y border-[var(--color-border)] py-5 sm:grid-cols-3 xl:grid-cols-5" aria-label="Campaign totals">
    <div><dt class="text-sm text-[var(--color-on-surface-muted)]">Campaigns</dt><dd class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($campaigns->total()) }}</dd></div>
    @foreach($overviewMetrics as $key => $label)
        @php($available = !in_array($key, ['delivered','bounced','opened','clicked']) || (in_array($key, ['delivered','bounced']) ? $providerCoverage > 0 : ($key === 'opened' ? $openCoverage > 0 : $clickCoverage > 0)))
        <div><dt class="text-sm text-[var(--color-on-surface-muted)]">{{ $label }}</dt><dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $available ? number_format((int)($totals->{$key} ?? 0)) : '—' }}</dd></div>
    @endforeach
</dl>
<p class="mb-6 text-sm text-[var(--color-on-surface-muted)]">Accepted means the outgoing transport accepted a message. Confirmed delivery is receiving-server acceptance reported by the provider. {{ $providerCoverage }} campaign(s) have provider-event coverage. Opens and clicks are observations and may include automated scanners.</p>
<div class="grid items-start gap-7 xl:grid-cols-3">
    <section class="min-w-0 xl:col-span-2" aria-labelledby="history-heading">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h2 id="history-heading" class="text-lg font-semibold">Campaign history</h2><a href="{{ route('admin.email-management.index') }}" class="btn-secondary">Refresh</a></div>
        <form method="GET" class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div><label for="campaign-search" class="form-label">Search campaigns</label><input id="campaign-search" name="q" class="form-input w-full" maxlength="150" value="{{ request('q') }}" placeholder="Campaign name"></div>
            <div><label for="campaign-state" class="form-label">Status</label><select id="campaign-state" name="status" class="form-select w-full"><option value="">All statuses</option>@foreach(['draft','scheduled','queued','processing','sending','paused','completed','partially_failed','failed','needs_attention','cancelled'] as $state)<option value="{{ $state }}" @selected(request('status') === $state)>{{ ucfirst(str_replace('_',' ',$state)) }}</option>@endforeach</select></div>
            <div><label for="campaign-from" class="form-label">Created from</label><input id="campaign-from" name="from" type="date" class="form-input w-full" value="{{ request('from') }}"></div>
            <div><label for="campaign-to" class="form-label">Created through</label><input id="campaign-to" name="to" type="date" class="form-input w-full" value="{{ request('to') }}"></div>
            <div class="flex items-end"><button class="btn-secondary" type="submit">Apply filters</button></div>
        </form>
        <div class="overflow-x-auto rounded-lg border border-[var(--color-border)]">
            <table class="w-full text-left text-sm"><thead class="bg-[var(--color-surface-2)]"><tr><th class="p-3">Campaign</th><th class="p-3">Status</th><th class="p-3">Audience</th><th class="p-3">Accepted</th><th class="p-3">Created</th></tr></thead><tbody class="divide-y divide-[var(--color-border)]">
                @forelse($campaigns as $campaign)<tr><td class="p-3"><a class="font-semibold text-[var(--color-primary)] underline underline-offset-4" href="{{ route('admin.email-management.show', $campaign) }}">{{ $campaign->name }}</a><span class="mt-1 block text-xs text-[var(--color-on-surface-muted)]">{{ $campaign->template->name }}</span></td><td class="p-3">{{ ucfirst(str_replace('_',' ',$campaign->status)) }}</td><td class="p-3 tabular-nums">{{ number_format($campaign->recipient_count) }}</td><td class="p-3 tabular-nums">{{ number_format($campaign->sent_count) }}</td><td class="whitespace-nowrap p-3">{{ $campaign->created_at->format('M j, Y') }}</td></tr>
                @empty<tr><td colspan="5" class="p-6 text-[var(--color-on-surface-muted)]">No campaigns match. Create a reusable template and prepare your first campaign.</td></tr>@endforelse
            </tbody></table>
        </div>
        <div class="mt-4">{{ $campaigns->links() }}</div>
    </section>
    <x-admin.panel title="Prepare a campaign" description="Start with a draft. Audience review comes before approval and delivery.">
        @if($templates->isEmpty())<p class="mb-4 text-sm">Create a template before preparing a campaign.</p><a class="btn-primary" href="{{ route('admin.email-templates.create') }}">Create template</a>@else
        <form method="POST" action="{{ route('admin.email-management.store') }}" class="space-y-4">@csrf
            <div><label for="new-name" class="form-label">Campaign name</label><input id="new-name" name="name" class="form-input w-full" value="{{ old('name') }}" maxlength="150" required></div>
            <div><label for="new-template" class="form-label">Template</label><select id="new-template" name="email_template_id" class="form-select w-full" required>@foreach($templates as $template)<option value="{{ $template->id }}" @selected((string)old('email_template_id') === (string)$template->id)>{{ $template->name }}</option>@endforeach</select></div>
            <div class="grid grid-cols-2 gap-3"><div><label for="new-batch" class="form-label">Batch size</label><input id="new-batch" name="batch_size" type="number" min="1" max="100" value="{{ old('batch_size', 25) }}" class="form-input w-full" required></div><div><label for="new-delay" class="form-label">Interval (seconds)</label><input id="new-delay" name="delay_seconds" type="number" min="1" max="3600" value="{{ old('delay_seconds', 60) }}" class="form-input w-full" required></div></div>
            <fieldset class="space-y-3"><legend class="mb-2 text-sm font-semibold">Optional engagement tracking</legend><label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="tracking_opens" value="1" @checked(old('tracking_opens'))>Observe opens</label><label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="tracking_clicks" value="1" @checked(old('tracking_clicks'))>Observe link clicks</label><label class="flex min-h-11 items-start gap-3"><input class="mt-1" type="checkbox" name="confirmed_tracking" value="1" @checked(old('confirmed_tracking'))><span class="text-sm">I have reviewed permission and privacy requirements for the tracking selected above.</span></label></fieldset>
            <button class="btn-primary" type="submit">Create draft</button>
        </form>@endif
        @if(auth()->user()->isSuperAdmin())<p class="mt-5 border-t border-[var(--color-border)] pt-4 text-sm"><a class="text-[var(--color-primary)] underline" href="{{ route('admin.email-campaigns.index') }}">Private document delivery and legacy campaigns</a></p>@endif
    </x-admin.panel>
</div>
@endsection
