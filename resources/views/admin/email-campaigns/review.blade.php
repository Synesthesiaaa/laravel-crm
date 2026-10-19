@extends('layouts.app')
@section('title', 'Review email campaign')
@section('header-title', 'Review email campaign')
@section('content')
<x-page-header :title="$campaign->name" description="Verify the recipient and PDF mapping before authorizing delivery."
    :breadcrumbs="['Email Campaigns' => route('admin.email-campaigns.index'), 'Review' => null]">
    <a href="{{ route('admin.email-campaigns.index') }}" class="btn-secondary">Back to campaigns</a>
    <a href="{{ route('admin.email-campaigns.report', $campaign) }}" class="btn-secondary">Redacted status CSV</a>
</x-page-header>
<x-email-workflow :current="$campaign->status === 'draft' ? 'review' : 'delivery'" />
@if(session('success'))
    <p role="status" class="mb-4 text-sm text-[var(--color-success-fg)]">{{ session('success') }}</p>
@endif
@if($errors->getBag('syntheticTest')->any())
    <div role="alert" class="mb-6 rounded-lg border border-[var(--color-danger)] p-4">
        <h2 class="font-semibold">Synthetic test needs attention</h2>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
            @foreach($errors->getBag('syntheticTest')->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
        <p class="mt-2 text-sm">Campaign details and PDF mappings are unchanged.</p>
        <a href="{{ route('admin.email-configuration.index') }}" class="mt-2 inline-flex min-h-10 items-center text-sm text-[var(--color-primary)] hover:underline">Open Email Configuration</a>
    </div>
@endif
@if($errors->any() || $preflight['errors'])
    <div role="alert" tabindex="-1" class="mb-6 rounded-lg border border-[var(--color-danger)] p-4">
        <h2 class="font-semibold">Sending is blocked</h2>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
            @foreach(array_unique([...$errors->all(), ...$preflight['errors']]) as $error)<li>{{ $error }}</li>@endforeach
        </ul>
        <p class="mt-2 text-sm">Resolve the listed issues before queueing the campaign. Re-import recipients and documents only when their mapping needs correction.</p>
    </div>
@endif
<dl class="mb-6 grid gap-4 rounded-lg border border-[var(--color-border)] p-4 sm:grid-cols-2 lg:grid-cols-4">
    <div><dt class="text-sm text-[var(--color-on-surface-muted)]">Purpose</dt><dd class="mt-1 font-semibold">{{ ucfirst($campaign->purpose) }}</dd></div>
    <div><dt class="text-sm text-[var(--color-on-surface-muted)]">Status</dt><dd class="mt-1 font-semibold">{{ str_replace('_', ' ', ucfirst($campaign->status)) }}</dd></div>
    <div><dt class="text-sm text-[var(--color-on-surface-muted)]">Sender</dt><dd class="mt-1 break-all">{{ $sender }}</dd></div>
    <div><dt class="text-sm text-[var(--color-on-surface-muted)]">Recipients</dt><dd class="mt-1 tabular-nums">{{ number_format($campaign->recipient_count) }}</dd></div>
</dl>
<div class="mb-6 flex flex-wrap gap-x-6 gap-y-2 text-sm">
    @foreach(['pending' => 'Queued / pending', 'sending' => 'Sending', 'accepted' => 'Accepted by provider', 'sent' => 'Accepted (historical)', 'skipped' => 'Skipped', 'blocked' => 'Blocked', 'failed' => 'Failed', 'uncertain' => 'Uncertain', 'cancelled' => 'Cancelled'] as $state => $label)
        <p>{{ $label }}: <span class="font-semibold tabular-nums">{{ $counts[$state] ?? 0 }}</span></p>
    @endforeach
</div>
<p class="mb-4 text-sm text-[var(--color-on-surface-muted)]">Provider acceptance does not confirm inbox delivery. Uncertain messages require provider reconciliation and cannot be blindly retried.</p>
<x-table.index caption="Recipient and attachment mapping" x-data x-init="$el.querySelector('.table-scroll-wrap').setAttribute('tabindex', '0')">
    <x-table.head :columns="[['label' => 'Recipient'], ['label' => 'Personal PDF'], ['label' => 'State']]" />
    <tbody>
        @foreach($recipients as $recipient)
            <tr>
                <td><p>{{ $recipient->name ?: 'Recipient' }}</p><p class="mt-1 break-all text-sm text-[var(--color-on-surface-muted)]">{{ $recipient->email }}</p>
                    @if($recipient->employee_id)<p class="mt-1 text-sm">Identifier: {{ \Illuminate\Support\Str::mask($recipient->employee_id, '*', 0, max(0, strlen($recipient->employee_id) - 3)) }} · {{ $recipient->period }}</p>@endif
                </td>
                <td>
                    @forelse($documents->where('email_campaign_recipient_id', $recipient->id) as $document)
                        <a class="inline-flex min-h-10 items-center break-all text-sm text-[var(--color-primary)] hover:underline" href="{{ route('admin.email-campaigns.document', [$campaign, $document]) }}">{{ $document->filename }} · Preview PDF</a>
                        <p class="text-sm">{{ $document->purged_at ? 'Purged' : 'Verified at import; checked again before sending' }}</p>
                    @empty
                        <p class="text-sm text-[var(--color-on-surface-muted)]">No personal PDF</p>
                    @endforelse
                    @if($campaign->delivery_snapshot['template']['pdf_enabled'] ?? $campaign->template->pdf_enabled)<p class="mt-1 text-sm">Additional generated template PDF</p>@endif
                </td>
                <td><p>{{ $recipient->status === 'sent' ? 'Accepted (historical)' : ucfirst($recipient->status) }}</p>
                    @if($recipient->error)<p class="mt-1 max-w-sm text-sm text-[var(--color-on-surface-muted)]">{{ $recipient->error }}</p>@endif
                    @if($recipient->status === 'uncertain')
                        <details class="mt-3 max-w-sm">
                            <summary class="min-h-10 cursor-pointer text-sm font-semibold">Record provider evidence</summary>
                            <form method="POST" action="{{ route('admin.email-campaigns.reconcile', [$campaign, $recipient]) }}" class="mt-3 space-y-3">@csrf
                                <label class="form-label" for="outcome-{{ $recipient->id }}">Verified outcome</label>
                                <select id="outcome-{{ $recipient->id }}" name="outcome" class="form-select"><option value="accepted">Provider accepted</option><option value="not_submitted">Provider confirms no submission</option></select>
                                <label class="form-label" for="case-{{ $recipient->id }}">Provider case ID (no personal data)</label>
                                <input id="case-{{ $recipient->id }}" name="provider_case" required maxlength="64" pattern="[A-Za-z0-9_-]+" class="form-input">
                                <label class="form-label" for="accepted-{{ $recipient->id }}">Provider acceptance time in {{ config('app.timezone') }} (required if accepted)</label>
                                <input id="accepted-{{ $recipient->id }}" name="accepted_at" type="datetime-local" class="form-input">
                                <label class="flex items-start gap-2 text-sm"><input name="confirmed_evidence" value="1" type="checkbox" required class="mt-1"><span>I verified provider evidence. Absence of evidence is not confirmation of no submission.</span></label>
                                <button class="btn-secondary">Record outcome without sending</button>
                            </form>
                        </details>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
    <x-slot:footer><x-table.pagination :paginator="$recipients" /></x-slot:footer>
</x-table.index>
@if($documents->whereNull('email_campaign_recipient_id')->isNotEmpty())
    <section class="mt-6" aria-labelledby="shared-heading">
        <h2 id="shared-heading" class="text-lg font-semibold">Shared PDFs — sent to every recipient</h2>
        <ul class="mt-2 space-y-1">
            @foreach($documents->whereNull('email_campaign_recipient_id') as $document)
                <li><a class="inline-flex min-h-10 items-center break-all text-[var(--color-primary)] hover:underline" href="{{ route('admin.email-campaigns.document', [$campaign, $document]) }}">{{ $document->filename }} · Preview PDF</a></li>
            @endforeach
        </ul>
    </section>
@endif
@if($campaign->status === 'draft')
    <details class="mt-6 border-t border-[var(--color-border)] pt-4">
        <summary class="min-h-10 cursor-pointer font-semibold">Edit draft details</summary>
        <form method="POST" action="{{ route('admin.email-campaigns.update', $campaign) }}" class="mt-4 grid gap-4 sm:grid-cols-2">@csrf @method('PUT')
            <div><label for="draft-name" class="form-label">Campaign name</label><input id="draft-name" name="name" value="{{ $campaign->name }}" required maxlength="150" class="form-input"></div>
            <div><label for="draft-template" class="form-label">Template</label><select id="draft-template" name="email_template_id" class="form-select">@foreach(\App\Models\EmailTemplate::orderBy('name')->get() as $template)<option value="{{ $template->id }}" @selected($template->id === $campaign->email_template_id)>{{ $template->name }}</option>@endforeach</select></div>
            <div><label for="draft-batch" class="form-label">Emails per batch</label><input id="draft-batch" name="batch_size" type="number" min="1" max="100" value="{{ $campaign->batch_size }}" required class="form-input"></div>
            <div><label for="draft-delay" class="form-label">Delay between batches (seconds)</label><input id="draft-delay" name="delay_seconds" type="number" min="1" max="3600" value="{{ $campaign->delay_seconds }}" required class="form-input"></div>
            <button class="btn-secondary justify-self-start">Save draft details</button>
        </form>
    </details>
    <section class="mt-8 space-y-4 border-t border-[var(--color-border)] pt-6" aria-labelledby="confirmation-heading">
        <h2 id="confirmation-heading" class="text-lg font-semibold">Verify and confirm</h2>
        <form method="POST" action="{{ route('admin.email-campaigns.dry-run', $campaign) }}">@csrf
            <button class="btn-secondary" @disabled(count($preflight['errors']) > 0)>Run internal dry-run</button>
            <p class="mt-2 text-sm text-[var(--color-on-surface-muted)]">Verifies all mappings, variables and PDF hashes without contacting SMTP.</p>
        </form>
        <form method="POST" action="{{ route('admin.email-campaigns.test-self', $campaign) }}">@csrf
            <button class="btn-secondary">Send synthetic test to myself</button>
            <p class="mt-2 text-sm text-[var(--color-on-surface-muted)]">Sends a real SMTP test to your account email using synthetic content and no personal PDF. Requires enabled Email Configuration; works independently of bulk sending and the queue worker.</p>
        </form>
        @if($campaign->dry_run_hash && hash_equals($campaign->dry_run_hash, (string) $preflight['hash']))
            <form method="POST" action="{{ route('admin.email-campaigns.send', $campaign) }}" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirmed_mapping" value="1" required class="mt-1"><span>I verified every recipient ↔ PDF mapping, the sender, and the authorized {{ $campaign->purpose }} purpose.</span></label>
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirmed_live_send" value="1" required class="mt-1"><span>I authorize live sending to {{ number_format($campaign->recipient_count) }} recipients. An in-flight message may complete after cancellation.</span></label>
                <button class="btn-primary" :disabled="submitting"><span x-text="submitting ? 'Queueing…' : 'Confirm and queue campaign'">Confirm and queue campaign</span></button>
            </form>
        @endif
    </section>
@elseif(in_array($campaign->status, ['queued', 'sending'], true))
    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('admin.email-campaigns.review', $campaign) }}" class="btn-secondary">Refresh progress</a>
        <form method="POST" action="{{ route('admin.email-campaigns.cancel', $campaign) }}">@csrf<button class="btn-secondary">Cancel unclaimed emails</button></form>
    </div>
@elseif($campaign->status === 'needs_attention')
    <form method="POST" action="{{ route('admin.email-campaigns.retry', $campaign) }}" class="mt-6 space-y-3">@csrf
        <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirmed_retry" value="1" required class="mt-1"><span>I authorize revalidated pre-send failures only. Uncertain messages remain excluded.</span></label>
        <button class="btn-secondary">Retry verified pre-send failures</button>
    </form>
@endif
@endsection
