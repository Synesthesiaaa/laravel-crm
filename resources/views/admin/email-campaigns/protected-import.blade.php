@extends('layouts.app')
@section('title', 'Prepare protected email draft')
@section('header-title', 'Prepare protected email draft')
@section('content')
<x-page-header title="Prepare protected email draft" description="Verify the recipient manifest and private documents before campaign review.">
    <a href="{{ route('admin.email-campaigns.index') }}" class="btn-secondary">Email campaigns</a>
</x-page-header>
<x-validation-errors />
@if(session('success'))<x-alert type="success" class="mb-6">{{ session('success') }}</x-alert>@endif
<div class="space-y-8" x-data="{
    run: @js($run), busy: false, polling: false, timer: null, delay: 3000, error: '', updated: '',
    statusUrl: @js(route('admin.email-campaigns.protected-imports.status', $run['id'])),
    actionUrl: @js(route('admin.email-campaigns.protected-imports.show', $run['id'])),
    init() { this.updated = new Date().toLocaleTimeString(); this.schedule(); },
    schedule() { clearTimeout(this.timer); if (['queued', 'processing'].includes(this.run.status)) this.timer = setTimeout(() => this.refresh(), this.delay); },
    async refresh() {
        if (this.polling) return;
        clearTimeout(this.timer);
        if (document.hidden) { this.schedule(); return; }
        this.polling = true;
        try {
            const response = await fetch(this.statusUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Preparation status could not be refreshed. Use Refresh status to retry.');
            this.run = await response.json(); this.updated = new Date().toLocaleTimeString(); this.error = ''; this.delay = 3000;
        } catch (error) { this.error = error.message; this.delay = Math.min(this.delay * 2, 30000); }
        finally { this.polling = false; }
        this.schedule();
    },
    async action(action) {
        if (this.busy) return;
        this.busy = true;
        try {
            const response = await fetch(this.actionUrl + (action === 'cancel' ? '' : '/resume'), { method: action === 'cancel' ? 'DELETE' : 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) } });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Preparation action could not be completed.');
            this.run = data; this.error = ''; this.updated = new Date().toLocaleTimeString(); this.delay = 3000; this.schedule();
        } catch (error) { this.error = error.message; }
        finally { this.busy = false; }
    },
    destroy() { clearTimeout(this.timer); }
}">
    <section class="md-card md-card--static p-5 sm:p-6" aria-labelledby="preparation-heading">
        <h2 id="preparation-heading" class="text-lg font-semibold">Draft preparation</h2>
        <p class="mt-2 max-w-3xl text-sm text-[var(--color-on-surface-muted)]">Your upload is stored in encrypted private staging. Background verification checks CSV fields, exact recipient-to-PDF mappings, document integrity and configured scanning policies. Preparation creates a draft only; sending still requires review and explicit approval.</p>
        <dl class="my-6 grid grid-cols-2 gap-5 md:grid-cols-4">
            <div><dt class="text-sm text-[var(--color-on-surface-muted)]">Status</dt><dd class="mt-1 font-semibold" x-text="run.status.replaceAll('_', ' ')"></dd></div>
            <div><dt class="text-sm text-[var(--color-on-surface-muted)]">Sources prepared</dt><dd class="mt-1 font-semibold tabular-nums"><span x-text="run.prepared_files"></span> / <span x-text="run.file_count"></span></dd></div>
            <div><dt class="text-sm text-[var(--color-on-surface-muted)]">Uploaded bytes</dt><dd class="mt-1 font-semibold tabular-nums" x-text="Number(run.source_bytes).toLocaleString()"></dd></div>
            <div><dt class="text-sm text-[var(--color-on-surface-muted)]">Last checked</dt><dd class="mt-1 font-semibold tabular-nums" x-text="updated"></dd></div>
        </dl>
        <p class="mb-4 text-sm" aria-live="polite" x-text="run.status === 'ready' ? 'Draft prepared. Open review to verify the intended recipients and documents.' : run.status === 'queued' ? 'Waiting for document preparation to start.' : run.status === 'processing' ? 'Private sources are being prepared and verified. All sources prepared does not mean document verification has finished.' : run.status === 'cancelled' ? 'Preparation cancelled. No draft was approved or sent.' : run.status === 'expired' ? 'Staging expired. Upload the authorized source again.' : 'Preparation needs attention. Correct validation errors in a replacement upload or resume after restoring an interrupted worker.'"></p>
        <p x-show="run.error" x-cloak class="form-error mb-4" x-text="run.error" role="alert"></p>
        <p x-show="error" x-cloak class="form-error mb-4" x-text="error" role="alert"></p>
        <div class="flex flex-wrap gap-3"><a x-show="run.status === 'ready'" x-cloak :href="run.review_url" class="btn-primary">Review prepared draft</a><button type="button" @click="refresh()" :disabled="polling || busy" class="btn-secondary">Refresh status</button><button type="button" x-show="run.status === 'failed'" x-cloak @click="action('resume')" :disabled="busy" class="btn-secondary">Resume interrupted preparation</button><button type="button" x-show="['queued','processing','failed'].includes(run.status)" x-cloak @click="action('cancel')" :disabled="busy" class="btn-secondary">Cancel preparation</button></div>
        <p class="mt-4 text-sm text-[var(--color-on-surface-muted)]">Staging expires at <time datetime="{{ $run['expires_at'] }}">{{ \Illuminate\Support\Carbon::parse($run['expires_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i T') }}</time>. If a queued preparation does not advance, ask your administrator to check the document preparation worker.</p>
    </section>
    <section aria-labelledby="recent-protected-heading"><h2 id="recent-protected-heading" class="mb-4 text-lg font-semibold">Recent protected preparations</h2><ul class="divide-y divide-[var(--color-border)]">@foreach($recent as $item)<li class="flex flex-wrap items-center justify-between gap-3 py-3"><div><span class="font-medium">{{ $item->status === 'ready' ? 'Draft #'.$item->email_campaign_id : 'Protected preparation' }}</span><span class="block text-sm text-[var(--color-on-surface-muted)]">{{ $item->created_at->format('Y-m-d H:i') }} · {{ $item->status }} · {{ number_format($item->file_count) }} source files</span></div><a class="btn-secondary" href="{{ route('admin.email-campaigns.protected-imports.show', $item) }}">View preparation</a></li>@endforeach</ul></section>
</div>
@endsection
