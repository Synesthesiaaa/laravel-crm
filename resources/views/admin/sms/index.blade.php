@extends('layouts.app')
@section('title', 'SMS Management')
@section('header-title', 'SMS Management')
@section('header-icon')<x-icon name="chat-bubble-left-right" class="w-5 h-5" />@endsection
@section('content')
<x-page-header title="SMS Management" description="Prepare consented audiences, manage GoIP channels, and track actual submission outcomes."
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'SMS Management' => null]">
    <a class="btn-primary" href="{{ route('admin.sms.index', ['workspace' => 'campaigns']) }}#create-campaign">Create campaign</a>
</x-page-header>
<x-validation-errors />
@if(session('success'))<x-alert type="success" class="mb-4">{{ session('success') }}</x-alert>@endif
@if(!config('sms.live_enabled') || config('sms.emergency_stop'))
<x-alert type="warning" class="mb-4">Live sending is disabled or the emergency stop is active. You can prepare drafts and review recipients.</x-alert>
@endif
<x-admin.tabs label="SMS workspaces" :active="$workspace" :tabs="[
    ['key' => 'overview', 'label' => 'Overview', 'href' => route('admin.sms.index')],
    ['key' => 'campaigns', 'label' => 'Campaigns', 'href' => route('admin.sms.index', ['workspace'=>'campaigns'])],
    ['key' => 'history', 'label' => 'Inbox & outbox', 'href' => route('admin.sms.index', ['workspace'=>'history'])],
    ['key' => 'templates', 'label' => 'Templates', 'href' => route('admin.sms.index', ['workspace'=>'templates'])],
    ['key' => 'gateways', 'label' => 'Gateways & channels', 'href' => route('admin.sms.index', ['workspace'=>'gateways'])],
    ['key' => 'reports', 'label' => 'Reports', 'href' => route('admin.sms.index', ['workspace'=>'reports'])],
]" />
<div class="space-y-6">
<form method="POST" action="{{ route('admin.sms.emergency-stop') }}">@csrf<button class="btn-secondary">Stop all SMS sending</button><p class="text-sm mt-2">Emergency stop disables every gateway and pauses active campaigns. Inbound opt-outs remain available.</p></form>
@if(in_array($workspace, ['overview', 'reports']))
    <dl class="grid grid-cols-2 gap-x-6 gap-y-4 border-y border-[var(--color-border)] py-5 lg:grid-cols-4">
    @foreach(['Campaigns' => $campaignTotal, 'Active campaigns' => $activeCampaigns, 'Queued messages' => ($counts['queued'] ?? 0), 'Being submitted' => ($counts['processing'] ?? 0), 'Submitted to Gateway' => ($counts['submitted'] ?? 0), 'Confirmed delivery' => ($counts['delivered'] ?? 0), 'Definitively failed' => ($counts['failed'] ?? 0), 'Unknown outcomes' => ($counts['unknown'] ?? 0), 'Received SMS' => ($counts['received'] ?? 0), 'Configured enabled channels' => $enabledChannels] as $label => $value)
        <div><dt class="text-sm text-[var(--color-on-surface-muted)]">{{ $label }}</dt><dd class="text-2xl tabular-nums mt-1">{{ number_format($value) }}</dd></div>
    @endforeach
    </dl>
    <p class="text-sm text-[var(--color-on-surface-muted)]">Gateway submission is not handset delivery. Delivery-report mapping has not been verified for the target firmware. Enabled channels indicate configuration, not live readiness.</p>
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="min-w-0" aria-labelledby="sms-volume"><h2 id="sms-volume" class="text-lg font-semibold mb-3">Message records · last 7 days</h2>
        <p class="text-sm mb-3">Includes preparation and inbound records; these totals do not imply successful sends.</p>
        @forelse($daily as $day)
            <div class="grid grid-cols-[6rem_minmax(0,1fr)_3rem] items-center gap-3 py-2"><span>{{ $day->day }}</span><meter class="w-full" min="0" max="{{ max(1, $daily->max('total')) }}" value="{{ $day->total }}" aria-label="{{ $day->day }} message records">{{ $day->total }}</meter><span class="text-right tabular-nums">{{ number_format($day->total) }}</span></div>
        @empty<p class="py-6 border-b border-[var(--color-border)]">No message records in the last 7 days. Create a campaign to start preparing recipients.</p>@endforelse
        </section>
        <section aria-labelledby="sms-health"><h2 id="sms-health" class="text-lg font-semibold mb-3">Operational checks</h2>
        <dl class="space-y-3 text-sm">
        <div><dt class="font-medium">Scheduler last observed</dt><dd>{{ $schedulerHeartbeat ?? 'No recent heartbeat. Check the scheduler.' }}</dd></div>
        <div><dt class="font-medium">SMS job last observed</dt><dd>{{ $workerHeartbeat ?? 'No recent job heartbeat. A quiet queue does not prove the worker is offline.' }}</dd></div>
        <div><dt class="font-medium">UDP receiver last observed</dt><dd>{{ $receiverHeartbeat ?? 'No recent receiver heartbeat.' }}</dd></div>
        <div><dt class="font-medium">Queue</dt><dd>{{ config('sms.queue_connection') }} / {{ config('sms.queue') }}</dd></div>
        </dl>
        <p class="mt-4 text-sm">Unknown submissions require an operator review before the affected channel is enabled again. No automatic resend is available.</p>
        </section>
    </div>
    <section><h2 class="text-lg font-semibold mb-3">Channel usage · Asia/Manila day</h2>
    @forelse($gateways as $gateway)
        @foreach($gateway->channels as $channel)
        <div class="flex flex-wrap items-center justify-between gap-3 py-3 border-b border-[var(--color-border)]"><span>{{ $gateway->name }} · CH{{ $channel->number }} · {{ $channel->enabled ? 'Enabled' : 'Disabled' }}</span><span class="tabular-nums">{{ $channel->usage_date?->toDateString() === now('Asia/Manila')->toDateString() ? $channel->used_today : 0 }} / {{ $channel->daily_limit }} attempts reserved</span></div>
        @endforeach
    @empty<p>No gateways configured. <a class="underline" href="{{ route('admin.sms.index', ['workspace'=>'gateways']) }}">Configure a GoIP gateway</a>.</p>@endforelse
    </section>
    @if($workspace === 'reports')
    <a class="btn-secondary" href="{{ route('admin.sms.report') }}">Export message status CSV</a>
    <p class="text-sm">Exports include recipient numbers and exclude message bodies. Access is audited. A submitted result does not certify delivery.</p>
    @endif
    <section><h2 class="text-lg font-semibold mb-3">Recent failures and unknown outcomes</h2>@forelse($failures as $failure)<div class="border-b border-[var(--color-border)] py-3"><p>{{ ucfirst($failure->status) }} · {{ $failure->reason }}</p><p class="text-sm">{{ $failure->campaign?->name ?? 'No campaign' }} · Message #{{ $failure->id }}</p>@if($failure->sms_campaign_id)<a class="underline inline-block py-2" href="{{ route('admin.sms.review', $failure->sms_campaign_id) }}">Review campaign outcome</a>@endif</div>@empty<p>No failed or unknown submissions recorded.</p>@endforelse</section>
@endif

@if(in_array($workspace, ['overview', 'campaigns']))
    <section><h2 class="text-lg font-semibold mb-3">{{ $workspace === 'overview' ? 'Recent campaigns' : 'SMS campaigns' }}</h2>
    <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
        <input type="hidden" name="workspace" value="campaigns">
        <div><label class="form-label" for="campaign-search">Campaign name</label><input class="form-input" id="campaign-search" name="q" maxlength="100" value="{{ request('q') }}"></div>
        <div><label class="form-label" for="campaign-status">Status</label><select class="form-input" id="campaign-status" name="status"><option value="">All statuses</option>@foreach(['draft','scheduled','queued','running','paused','completed','canceled','failed'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
        <button class="btn-secondary">Filter campaigns</button>
    </form>
    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="SMS campaigns">
    <table class="w-full text-sm"><thead><tr class="text-left border-b border-[var(--color-border)]"><th scope="col" class="p-3">Campaign</th><th scope="col" class="p-3">State</th><th scope="col" class="p-3 text-right">Recipient rows</th><th scope="col" class="p-3">Scheduled · Manila</th><th scope="col" class="p-3">Action</th></tr></thead><tbody>
    @forelse(($workspace === 'overview' ? $campaigns->take(5) : $campaigns) as $campaign)
    <tr class="border-b border-[var(--color-border)]"><th scope="row" class="p-3 text-left font-medium">{{ $campaign->name }}<span class="block font-normal text-[var(--color-on-surface-muted)]">{{ $campaign->gateway->name }}</span></th><td class="p-3">{{ ucfirst($campaign->status) }}</td><td class="p-3 text-right tabular-nums">{{ number_format($campaign->messages_count) }} rows<span class="block text-[var(--color-on-surface-muted)]">{{ $campaign->eligible_count }} eligible · {{ $campaign->submitted_count }} submitted<br>{{ $campaign->delivered_count }} delivered · {{ $campaign->failed_count }} failed</span></td><td class="p-3">{{ $campaign->scheduled_at?->timezone('Asia/Manila')->format('M j, Y H:i') ?? 'After confirmation' }}</td><td class="p-3"><a class="btn-secondary" href="{{ route('admin.sms.review', $campaign) }}">Review</a></td></tr>
    @empty<tr><td colspan="5" class="p-6">No campaigns found. Create a draft and add recipients with documented consent.</td></tr>@endforelse
    </tbody></table></div>
    @if($workspace === 'campaigns')<div class="mt-4">{{ $campaigns->links() }}</div>@else<a class="inline-block underline mt-4" href="{{ route('admin.sms.index', ['workspace'=>'campaigns']) }}">View all campaigns</a>@endif
    </section>
@endif

@if($workspace === 'campaigns')
<section id="create-campaign"><x-admin.create-panel title="Create an SMS campaign" description="Create a draft, add an audience, then explicitly confirm eligible recipients." :open="true">
<form method="POST" action="{{ route('admin.sms.store') }}" class="space-y-5" x-data="{ body: @js(old('body', '')), estimate() { const basic = @js(\App\Services\Sms\SmsText::GSM_BASIC); const ext = @js(\App\Services\Sms\SmsText::GSM_EXTENDED); let units=0; for (const c of this.body) { if(basic.includes(c)) units++; else if(ext.includes(c)) units+=2; else return 'Unicode · '+this.body.length+' UTF-16 units · '+(this.body.length<=70?1:Math.ceil(this.body.length/67))+' segment(s). Sending requires verified Unicode support.'; } return 'GSM-7 · '+units+' units · '+(units<=160?1:Math.ceil(units/153))+' segment(s). The current profile permits one segment.'; } }">
@csrf
<div class="grid gap-4 md:grid-cols-2">
<div><label class="form-label" for="sms-name">Campaign name</label><input id="sms-name" class="form-input" name="name" required maxlength="150" value="{{ old('name') }}"></div>
<div><label class="form-label" for="sms-category">Purpose</label><select id="sms-category" class="form-input" name="category"><option value="operational">Operational · purpose-specific consent required</option><option value="marketing" @selected(old('category') === 'marketing')>Marketing · STOP instructions and verified inbound required</option></select></div>
<div><label class="form-label" for="sms-gateway">Sender gateway</label><select id="sms-gateway" class="form-input" name="sms_gateway_id" required><option value="">Select a configured gateway</option>@foreach($gateways as $gateway)<option value="{{ $gateway->id }}" @selected(old('sms_gateway_id') == $gateway->id)>{{ $gateway->name }} · {{ $gateway->verified_at ? 'Profile verified by operator' : 'Unverified' }}</option>@endforeach</select></div>
<div><label class="form-label" for="sms-schedule">Schedule · Asia/Manila (optional)</label><input id="sms-schedule" class="form-input" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"></div>
</div>
<fieldset><legend class="form-label">Eligible channels · select only channels on the chosen gateway</legend><div class="flex flex-wrap gap-4">@forelse($gateways as $gateway)@foreach($gateway->channels as $channel)<label class="inline-flex min-h-11 items-center gap-2"><input type="checkbox" name="channel_ids[]" value="{{ $channel->id }}" @checked(in_array($channel->id, old('channel_ids', [])))> {{ $gateway->name }} · CH{{ $channel->number }} · {{ $channel->enabled ? 'Enabled' : 'Disabled' }}</label>@endforeach @empty<p>Configure your gateway and actual SMS channels first.</p>@endforelse</div></fieldset>
<div><label class="form-label" for="sms-template">Use a saved template (optional)</label><select class="form-input" id="sms-template" @change="if($event.target.selectedOptions[0].dataset.body) body=$event.target.selectedOptions[0].dataset.body"><option value="">Choose template</option>@foreach($templates as $template)<option data-body="{{ $template->body }}">{{ $template->name }}</option>@endforeach</select></div>
<div><label class="form-label" for="sms-body">Message body</label><textarea id="sms-body" name="body" class="form-input min-h-32" required maxlength="1000" x-model="body" aria-describedby="sms-estimate sms-variables">{{ old('body') }}</textarea><p id="sms-estimate" class="text-sm mt-2" aria-live="polite" x-text="estimate()">Segment estimate is validated on the server.</p><p id="sms-variables" class="text-sm mt-2">Supported variables: <code>@{{recipient_name}}</code> and <code>@{{campaign_name}}</code>. Marketing must include instructions to reply STOP.</p></div>
<div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label" for="quiet-start">Quiet hours start · Manila hour</label><input id="quiet-start" class="form-input" type="number" name="quiet_start" min="0" max="23" value="{{ old('quiet_start', 20) }}" required></div><div><label class="form-label" for="quiet-end">Quiet hours end · Manila hour</label><input id="quiet-end" class="form-input" type="number" name="quiet_end" min="0" max="23" value="{{ old('quiet_end', 8) }}" required></div></div>
<div><label class="form-label" for="sms-description">Description and internal notes (optional)</label><textarea id="sms-description" class="form-input" name="description" maxlength="2000">{{ old('description') }}</textarea></div>
<button class="btn-primary" @disabled($gateways->isEmpty())>Create draft and review recipients</button>
</form></x-admin.create-panel></section>
@endif

@if($workspace === 'history')
<section><h2 class="text-lg font-semibold mb-3">Inbox & outbox</h2>
<form method="POST" action="{{ route('admin.sms.history-filter') }}" class="flex flex-wrap gap-3 items-end mb-4">@csrf<div><label class="form-label" for="history-direction">Direction</label><select class="form-input" id="history-direction" name="direction"><option value="">Both directions</option><option value="inbound" @selected(request('direction') === 'inbound')>Inbound</option><option value="outbound" @selected(request('direction') === 'outbound')>Outbound</option></select></div><div><label class="form-label" for="history-phone">Exact E.164 mobile number</label><input class="form-input" id="history-phone" name="phone" maxlength="16" value="{{ $phoneFilter }}"></div><div><label class="form-label" for="history-status">Status</label><select class="form-input" id="history-status" name="status"><option value="">All statuses</option>@foreach(['queued','processing','submitted','delivered','unknown','failed','canceled','received'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div><button class="btn-secondary">Filter history</button></form>
@forelse($messages as $message)
<article class="border-b border-[var(--color-border)] py-4 space-y-2"><div class="flex flex-wrap justify-between gap-2"><div class="font-medium"><form method="POST" action="{{ route('admin.sms.history-filter') }}" class="inline">@csrf<input type="hidden" name="phone" value="{{ $message->phone }}"><button class="underline min-h-11" type="submit">{{ $message->phone ?? 'Invalid destination excluded' }}</button></form> · {{ $message->direction }}</div><span>{{ $message->status === 'submitted' ? 'Submitted to Gateway' : ucfirst($message->status) }} @if($message->direction === 'inbound' && !$message->read_at) · Unread @endif</span></div><p class="text-sm text-[var(--color-on-surface-muted)]">{{ $message->created_at->timezone('Asia/Manila')->format('M j, Y H:i') }} · {{ $message->campaign?->name ?? 'Incoming SMS' }} · {{ $message->channel?->gateway?->name ?? 'No channel reserved' }}</p><details class="group"><summary class="cursor-pointer min-h-11 flex items-center"><x-icon name="chevron-down" class="h-4 w-4 shrink-0 mr-2 group-open:rotate-180" />View message and details</summary><p class="whitespace-pre-wrap break-words py-2">{{ $message->body ?? 'No message generated.' }}</p><p class="text-sm">{{ $message->reason }}</p></details>@if($message->direction === 'inbound' && !$message->read_at)<form method="POST" action="{{ route('admin.sms.read', $message) }}">@csrf<button class="btn-secondary">Mark read</button></form>@endif</article>
@empty<p class="py-6">No matching messages. Inbound messages appear only after the firmware profile and receiver are configured and verified.</p>@endforelse
<div class="mt-4">{{ $messages->links() }}</div></section>
@endif

@if($workspace === 'templates')
<x-admin.create-panel title="Create an SMS template" description="Templates use only CRM-backed recipient and campaign names." :open="true"><form class="space-y-4" method="POST" action="{{ route('admin.sms.templates.store') }}">@csrf<label class="form-label" for="template-name">Template name</label><input id="template-name" name="name" class="form-input" required maxlength="150"><label class="form-label" for="template-body">Body · @{{recipient_name}} and @{{campaign_name}} supported</label><textarea id="template-body" name="body" class="form-input min-h-32" required maxlength="1000"></textarea><button class="btn-primary">Save template</button></form></x-admin.create-panel>
@forelse($templates as $template)<details class="group border-b border-[var(--color-border)] py-4"><summary class="min-h-11 cursor-pointer font-medium"><x-icon name="chevron-down" class="h-4 w-4 shrink-0 mr-2 group-open:rotate-180" />{{ $template->name }}</summary><form class="space-y-3 mt-3" method="POST" action="{{ route('admin.sms.templates.update', $template) }}">@csrf @method('PUT')<label class="form-label" for="template-name-{{ $template->id }}">Name</label><input id="template-name-{{ $template->id }}" name="name" class="form-input" required maxlength="150" value="{{ $template->name }}"><label class="form-label" for="template-body-{{ $template->id }}">Body</label><textarea id="template-body-{{ $template->id }}" name="body" class="form-input min-h-32" required maxlength="1000">{{ $template->body }}</textarea><p class="text-sm">{{ \App\Services\Sms\SmsText::estimate($template->body)['encoding'] }} · {{ \App\Services\Sms\SmsText::estimate($template->body)['segments'] }} estimated segment(s) before substitution.</p><button class="btn-secondary">Update template</button></form></details>@empty<p>No templates saved.</p>@endforelse
{{ $templates->links() }}
@endif

@if($workspace === 'gateways')
<p>Only explicitly allowlisted IPv4 destinations can be saved. Do not change existing SIP, VICIDial or dialplan settings. Outbound HTTP and inbound SMS Server UDP are separate profiles.</p>
<x-admin.create-panel title="Add a GoIP gateway" description="Record actual firmware and verified interfaces. Saving does not send SMS." :open="$gateways->isEmpty()">@include('admin.sms.gateway-form', ['gateway' => null])</x-admin.create-panel>
@foreach($gateways as $gateway)
<section class="border-t border-[var(--color-border)] pt-5"><h2 class="text-lg font-semibold">{{ $gateway->name }}</h2><p class="text-sm my-2">{{ $gateway->firmware }} · {{ $gateway->verified_at ? 'Operator-verified profile' : 'Unverified profile' }} · Last successful send: {{ $gateway->last_contact_at?->diffForHumans() ?? 'Never observed' }}</p>
<form method="POST" action="{{ route('admin.sms.connection', $gateway) }}">@csrf<button class="btn-secondary">Test network connection</button></form>
<details class="group my-4"><summary class="min-h-11 flex items-center cursor-pointer"><x-icon name="chevron-down" class="h-4 w-4 shrink-0 mr-2 group-open:rotate-180" />Edit gateway settings</summary>@include('admin.sms.gateway-form', ['gateway' => $gateway])</details>
<h3 class="font-semibold mt-5 mb-3">Configured channels</h3>
@forelse($gateway->channels as $channel)<div class="border-b border-[var(--color-border)] py-3"><p>CH{{ $channel->number }} · {{ $channel->client_id ?? 'No SMS Server client ID' }} · {{ $channel->enabled ? 'Enabled' : 'Disabled' }}</p><p class="text-sm text-[var(--color-on-surface-muted)]">Interval {{ $channel->interval_seconds }} seconds · Daily limit {{ $channel->daily_limit }} · Last communication {{ $channel->last_contact_at?->diffForHumans() ?? 'Not observed' }}</p><p class="text-sm tabular-nums">{{ $channel->queued_count }} queued assignments · {{ $channel->processing_count }} processing · {{ $channel->submitted_count }} submitted · {{ $channel->failure_count }} failed/unknown</p><p class="text-sm">Queued campaigns are assigned a channel only at submission.</p></div>@empty<p>No channels configured. Add only the channel addresses verified on this gateway.</p>@endforelse
<details class="group mt-3"><summary class="min-h-11 flex items-center cursor-pointer"><x-icon name="chevron-down" class="h-4 w-4 shrink-0 mr-2 group-open:rotate-180" />Add or update a channel</summary><form class="space-y-4 mt-3" method="POST" action="{{ route('admin.sms.channels.store', $gateway) }}">@csrf
<div class="grid gap-4 md:grid-cols-3">@foreach(['number'=>['Channel number',1,32,1], 'interval_seconds'=>['Sending interval (seconds)',30,86400,30], 'daily_limit'=>['Daily attempt limit',1,10000,100]] as $field => $settings)<div><label class="form-label" for="ch-{{ $gateway->id }}-{{ $field }}">{{ $settings[0] }}</label><input class="form-input" type="number" id="ch-{{ $gateway->id }}-{{ $field }}" name="{{ $field }}" min="{{ $settings[1] }}" max="{{ $settings[2] }}" value="{{ $settings[3] }}" required></div>@endforeach</div>
<div class="grid gap-4 md:grid-cols-2"><div><label class="form-label" for="client-{{ $gateway->id }}">SMS Server client ID (optional)</label><input class="form-input" id="client-{{ $gateway->id }}" name="client_id" maxlength="100"></div><div><label class="form-label" for="channel-secret-{{ $gateway->id }}">Replace SMS Server password (blank preserves existing)</label><input class="form-input" type="password" id="channel-secret-{{ $gateway->id }}" name="password" autocomplete="new-password" maxlength="255"></div></div>
<label class="flex items-center min-h-11 gap-2"><input type="checkbox" name="enabled" value="1"> Enable this verified physical channel</label><button class="btn-secondary">Save channel</button></form></details></section>
@endforeach
@endif

@if(in_array($workspace, ['history','campaigns']))
<x-admin.create-panel title="Consent and suppression" description="Record actual evidence or revoke consent. An inbound reply never grants consent automatically.">
<div class="grid gap-6 lg:grid-cols-2"><form method="POST" class="space-y-3" action="{{ route('admin.sms.consent') }}">@csrf
<h2 class="font-semibold">Record consent evidence</h2><label class="form-label" for="consent-phone">Mobile number</label><input id="consent-phone" class="form-input" name="phone" required maxlength="50"><label class="form-label" for="consent-category">Purpose</label><select id="consent-category" class="form-input" name="category"><option value="operational">Operational</option><option value="marketing">Marketing</option></select><label class="form-label" for="consent-source">Evidence source or reference</label><input id="consent-source" class="form-input" name="source" required maxlength="255"><label class="form-label" for="consent-at">Consented at · include timezone</label><input id="consent-at" class="form-input" name="consented_at" type="text" placeholder="2026-10-20T09:00:00+08:00" required><label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="confirm_evidence" value="1" required>I verified the evidence for this purpose.</label><button class="btn-secondary">Record evidence</button></form>
<form method="POST" class="space-y-3" action="{{ route('admin.sms.revoke') }}">@csrf<h2 class="font-semibold">Revoke consent and suppress</h2><label class="form-label" for="suppress-phone">Mobile number</label><input id="suppress-phone" class="form-input" name="phone" required maxlength="50"><p>Revokes recorded consent and cancels queued messages. Submitted messages cannot be recalled.</p><button class="btn-secondary">Revoke and suppress recipient</button></form></div>
</x-admin.create-panel>
@endif
</div>
@endsection
