@extends('layouts.app')
@section('title', 'Email audiences')
@section('header-title', 'Email audiences')
@section('content')
<x-page-header title="Email audiences" description="Prepare eligible recipients, record permission and reuse saved segments.">
    <a href="{{ route('admin.email-management.index') }}" class="btn-secondary">Email campaigns</a>
</x-page-header>
<x-validation-errors />
@if(session('success'))<x-alert type="success" class="mb-6">{{ session('success') }}</x-alert>@endif

<div class="space-y-8" x-data="{
    source: 'manual', busy: false, polling: false, run: null, error: '', timer: null, delay: 2500,
    base: @js(route('admin.email-audiences.imports.store')),
    async prepare(form) {
        this.busy = true; this.error = '';
        try {
            const response = await fetch(this.base, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Audience preparation could not start.');
            this.run = data; this.delay = 2500; this.schedule();
        } catch (error) { this.error = error.message; }
        finally { this.busy = false; }
    },
    schedule() {
        clearTimeout(this.timer);
        if (this.run &amp;&amp; ['queued', 'processing', 'applying'].includes(this.run.status)) this.timer = setTimeout(() => this.refresh(), this.delay);
    },
    async refresh() {
        if (!this.run || this.polling) return;
        clearTimeout(this.timer);
        if (document.hidden) { this.schedule(); return; }
        this.polling = true;
        try {
            const response = await fetch(this.base + '/' + this.run.id, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Progress could not be refreshed. Use Refresh progress to retry.');
            this.run = await response.json(); this.error = ''; this.delay = 2500;
        } catch (error) { this.error = error.message; this.delay = Math.min(this.delay * 2, 30000); }
        finally { this.polling = false; }
        this.schedule();
    },
    async action(action) {
        if (!this.run || this.busy) return;
        this.busy = true; this.error = '';
        try {
            const response = await fetch(this.base + '/' + this.run.id + (action === 'cancel' ? '' : '/' + action), { method: action === 'cancel' ? 'DELETE' : 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) }, body: JSON.stringify({ confirmed_audience: true }) });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'This audience action could not be completed.');
            this.run = data; this.schedule();
        } catch (error) { this.error = error.message; }
        finally { this.busy = false; }
    },
    destroy() { clearTimeout(this.timer); }
}">
    <section aria-labelledby="prepare-heading" class="md-card md-card--static p-5 sm:p-6">
        <h2 id="prepare-heading" class="text-lg font-semibold">Prepare recipients</h2>
        <p class="mt-2 max-w-3xl text-sm text-[var(--color-on-surface-muted)]">Create an empty campaign draft first. Preparation runs in the background and never sends email. Every new recipient requires recorded permission; CRM records do not establish consent.</p>
        <form class="mt-5 space-y-5" @submit.prevent="prepare($el)" enctype="multipart/form-data">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="form-field"><label class="form-label" for="audience-campaign">Campaign draft</label><select id="audience-campaign" name="campaign_id" class="form-select" required><option value="">Choose an attachment-free marketing draft</option>@foreach($drafts as $draft)<option value="{{ $draft->id }}">{{ $draft->name }}</option>@endforeach</select></div>
                <div class="form-field"><label class="form-label" for="audience-source">Recipient source</label><select id="audience-source" name="source" class="form-select" x-model="source"><option value="manual">Manual entry</option><option value="csv">CSV file</option><option value="xlsx">Excel XLSX</option><option value="crm">CRM records</option><option value="segment">Saved segment</option></select></div>
            </div>
            <div class="form-field"><label class="form-label" for="audience-duplicates">Duplicate addresses</label><select id="audience-duplicates" name="duplicate_policy" class="form-select"><option value="exclude">Keep first occurrence and exclude later duplicates</option><option value="reject">Reject the audience if any duplicate occurs</option></select></div>
            <div x-show="source === 'manual'" class="form-field"><label class="form-label" for="manual-recipients">Recipients, one email,name per line</label><textarea id="manual-recipients" name="manual_recipients" rows="5" class="form-input w-full" :required="source === 'manual'" :disabled="source !== 'manual'" placeholder="alice@example.com,Alice&#10;bob@example.com,Bob"></textarea><p class="mt-1 text-sm text-[var(--color-on-surface-muted)]">The name is optional. Up to 256 KiB of manual entries.</p></div>
            <div x-show="['csv', 'xlsx'].includes(source)" x-cloak class="form-field"><label class="form-label" for="audience-file">Recipient file</label><input id="audience-file" type="file" name="file" class="form-input w-full" :accept="source === 'xlsx' ? '.xlsx' : '.csv,.txt'" :required="['csv', 'xlsx'].includes(source)" :disabled="!['csv', 'xlsx'].includes(source)"><p class="mt-1 text-sm text-[var(--color-on-surface-muted)]">Use email,name headers; employee_id and period are optional. CSV up to 5 MiB, XLSX up to 10 MiB. Formulas and macros are rejected. Only the selected worksheet is imported.</p></div>
            <div x-show="source === 'xlsx'" x-cloak class="form-field"><label class="form-label" for="audience-worksheet">Worksheet number</label><input id="audience-worksheet" name="worksheet" type="number" class="form-input" min="1" max="10" value="1" :disabled="source !== 'xlsx'" :required="source === 'xlsx'"></div>
            <div x-show="source === 'crm'" x-cloak class="grid gap-4 sm:grid-cols-2">
                <div class="form-field"><label class="form-label" for="crm-model">CRM source</label><select id="crm-model" name="crm_model" class="form-select" :disabled="source !== 'crm'"><option value="hopper">Assigned lead hopper</option><option value="capture">Captured CRM records</option></select></div>
                <div class="form-field"><label class="form-label" for="crm-campaign">Source campaign code</label><input id="crm-campaign" name="campaign_code" class="form-input" maxlength="100" :required="source === 'crm'" :disabled="source !== 'crm'"></div>
                <div class="form-field"><label class="form-label" for="crm-email">Email field name</label><input id="crm-email" name="email_field" class="form-input" maxlength="100" placeholder="customer_email" :required="source === 'crm'" :disabled="source !== 'crm'"></div>
                <div class="form-field"><label class="form-label" for="crm-name">Name field name (optional)</label><input id="crm-name" name="name_field" class="form-input" maxlength="100" :disabled="source !== 'crm'"></div>
            </div>
            <div x-show="source === 'segment'" x-cloak class="form-field"><label class="form-label" for="audience-segment">Saved segment</label><select id="audience-segment" name="segment_id" class="form-select" :required="source === 'segment'" :disabled="source !== 'segment'"><option value="">Choose a saved segment</option>@foreach($segments as $segment)<option value="{{ $segment->id }}">{{ $segment->name }}</option>@endforeach</select></div>
            <fieldset class="space-y-4 border-t border-[var(--color-border)] pt-4"><legend class="form-label">Permission evidence</legend><label class="flex items-start gap-3 text-sm"><input type="checkbox" name="assert_permission" value="1" class="mt-1"><span>I assert documented permission for the recipients in this source. Existing opt-outs, withdrawn consent and suppressions still apply.</span></label><div class="grid gap-4 sm:grid-cols-2"><div class="form-field"><label class="form-label" for="permission-source">Permission source or reference</label><input id="permission-source" name="permission_source" class="form-input" maxlength="500" placeholder="Approved opt-in register reference"></div><div class="form-field"><label class="form-label" for="permission-date">Permission date</label><input id="permission-date" name="permission_at" type="date" class="form-input" max="{{ now()->toDateString() }}"></div></div></fieldset>
            <button type="submit" class="btn-primary" :disabled="busy">Prepare audience</button>
        </form>
    </section>

    <p x-show="error" x-cloak x-text="error" class="form-error" role="alert"></p>
    <section x-show="run" x-cloak class="md-card md-card--static p-5 sm:p-6" aria-labelledby="preview-heading">
        <h2 id="preview-heading" class="text-lg font-semibold">Audience preview</h2>
        <p class="mt-2 text-sm" aria-live="polite"><span x-text="run?.status"></span><span class="mx-2" aria-hidden="true">·</span><span x-text="run?.message"></span></p>
        <dl class="my-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">@foreach(['processed' => 'Processed', 'accepted' => 'Eligible', 'duplicates' => 'Duplicates', 'suppressed' => 'Suppressed', 'rejected' => 'Rejected', 'applied' => 'Added to draft'] as $key => $label)<div><dt class="text-sm text-[var(--color-on-surface-muted)]">{{ $label }}</dt><dd class="mt-1 text-xl font-semibold tabular-nums" x-text="run?.{{ $key }} ?? 0"></dd></div>@endforeach</dl>
        <ul class="mb-5 space-y-1 text-sm" aria-label="Rejected row reasons"><template x-for="item in run?.errors || []" :key="item.record_number"><li><span>Row </span><span x-text="item.record_number"></span><span>: </span><span x-text="item.error_code.replaceAll('_', ' ')"></span></li></template></ul>
        <p class="mb-4 text-sm text-[var(--color-on-surface-muted)]">Rejected rows require a corrected replacement source. Duplicate and suppressed addresses are excluded. Review these counts before adding eligible recipients to the draft.</p>
        <div class="flex flex-wrap gap-3"><button type="button" class="btn-primary" x-show="run?.status === 'ready' &amp;&amp; run?.accepted > 0" @click="action('apply')" :disabled="busy">Confirm audience and add to draft</button><button type="button" class="btn-secondary" @click="refresh()" :disabled="busy">Refresh progress</button><button type="button" class="btn-secondary" x-show="run?.status === 'failed' &amp;&amp; run?.rejected === 0" @click="action('resume')" :disabled="busy">Resume preparation</button><button type="button" class="btn-secondary" x-show="run &amp;&amp; !['applied','cancelled','expired'].includes(run.status)" @click="action('cancel')" :disabled="busy">Cancel preparation</button></div>
    </section>

    <section aria-labelledby="contacts-heading">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-4"><h2 id="contacts-heading" class="text-lg font-semibold">Contacts</h2><form method="GET" class="flex flex-wrap gap-3"><label class="sr-only" for="contact-search">Search contacts</label><input id="contact-search" type="search" name="q" value="{{ request('q') }}" class="form-input" placeholder="Search name or email"><button class="btn-secondary">Search</button></form></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Email contacts and recorded permission</caption><thead><tr class="border-b border-[var(--color-border)]"><th class="p-3">Name / email</th><th class="p-3">Permission</th><th class="p-3">Source</th><th class="p-3">Tags</th></tr></thead><tbody>@forelse($contacts as $contact)<tr class="border-b border-[var(--color-border)]"><td class="p-3"><span class="block font-medium">{{ $contact->name ?: 'Unnamed contact' }}</span><span>{{ $contact->email }}</span></td><td class="p-3">{{ str_replace('_', ' ', $contact->consent_status) }}<span class="block text-[var(--color-on-surface-muted)]">{{ $contact->consent_asserted_at?->toDateString() }}</span></td><td class="p-3">{{ $contact->source }}{{ $contact->campaign_code ? ' · '.$contact->campaign_code : '' }}</td><td class="p-3">{{ implode(', ', $contact->tags ?? []) }}</td></tr>@empty<tr><td colspan="4" class="p-5 text-[var(--color-on-surface-muted)]">No contacts match. Add a contact or prepare an audience.</td></tr>@endforelse</tbody></table></div><div class="mt-4">{{ $contacts->links() }}</div>
    </section>

    <div class="grid items-start gap-8 xl:grid-cols-2">
        <section class="md-card md-card--static p-5 sm:p-6" aria-labelledby="contact-add-heading"><h2 id="contact-add-heading" class="text-lg font-semibold">Add or update a contact</h2><form method="POST" action="{{ route('admin.email-audiences.contacts.store') }}" class="mt-5 space-y-4">@csrf<div class="form-field"><label class="form-label" for="contact-email">Email</label><input id="contact-email" name="email" type="email" class="form-input" maxlength="255" required></div><div class="form-field"><label class="form-label" for="contact-name">Name</label><input id="contact-name" name="name" class="form-input" maxlength="255"></div><div class="form-field"><label class="form-label" for="contact-tags">Tags, separated by commas</label><input id="contact-tags" name="tags" class="form-input" maxlength="500"></div><div class="form-field"><label class="form-label" for="contact-consent">Permission status</label><select id="contact-consent" name="consent_status" class="form-select"><option value="unknown">Unknown — ineligible for marketing</option><option value="asserted">Documented permission assertion</option><option value="withdrawn">Withdrawn</option></select></div><div class="form-field"><label class="form-label" for="contact-consent-source">Permission source (required for assertion)</label><input id="contact-consent-source" name="consent_source" class="form-input" maxlength="500"></div><div class="form-field"><label class="form-label" for="contact-consent-date">Permission date (required for assertion)</label><input id="contact-consent-date" name="consent_asserted_at" type="date" class="form-input" max="{{ now()->toDateString() }}"></div><button class="btn-primary">Save contact</button></form></section>
        <section class="md-card md-card--static p-5 sm:p-6" aria-labelledby="segments-heading"><h2 id="segments-heading" class="text-lg font-semibold">Saved segments</h2><ul class="mt-4 space-y-3">@forelse($segments as $segment)<li><span class="font-medium">{{ $segment->name }}</span><span class="block text-sm text-[var(--color-on-surface-muted)]">@foreach($segment->filters as $key => $value){{ str_replace('_',' ',$key) }}: {{ $value }}{{ ! $loop->last ? ' · ' : '' }}@endforeach</span></li>@empty<li class="text-sm text-[var(--color-on-surface-muted)]">No segments yet. Save filters to reuse an audience.</li>@endforelse</ul><form method="POST" action="{{ route('admin.email-audiences.segments.store') }}" class="mt-6 space-y-4">@csrf<div class="form-field"><label class="form-label" for="segment-name">Segment name</label><input id="segment-name" name="name" class="form-input" maxlength="150" required></div><div class="form-field"><label class="form-label" for="segment-tag">Tag</label><input id="segment-tag" name="filters[tag]" class="form-input" maxlength="100"></div><div class="form-field"><label class="form-label" for="segment-campaign">Source CRM campaign code</label><input id="segment-campaign" name="filters[campaign_code]" class="form-input" maxlength="100"></div><div class="form-field"><label class="form-label" for="segment-source">Contact source</label><select id="segment-source" name="filters[source]" class="form-select"><option value="">Any source</option>@foreach(['manual','csv','xlsx','crm','segment'] as $source)<option value="{{ $source }}">{{ strtoupper($source) }}</option>@endforeach</select></div><div class="form-field"><label class="form-label" for="segment-consent">Permission state</label><select id="segment-consent" name="filters[consent_status]" class="form-select"><option value="">Any state — eligibility still checked</option><option value="opted_in">Opted in</option><option value="asserted">Documented assertion</option></select></div><button class="btn-primary">Save segment</button></form></section>
    </div>
    <section aria-labelledby="recent-imports-heading"><h2 id="recent-imports-heading" class="mb-4 text-lg font-semibold">Recent preparations</h2><ul class="divide-y divide-[var(--color-border)]">@forelse($imports as $import)<li class="flex flex-wrap items-center justify-between gap-3 py-3"><div><span class="font-medium">Campaign #{{ $import->email_campaign_id }}</span><span class="block text-sm text-[var(--color-on-surface-muted)]">{{ $import->source }} · {{ $import->status }} · {{ number_format($import->accepted) }} eligible</span></div><button type="button" class="btn-secondary" @click="run = @js($import->only(['id','status'])); refresh()">View preparation</button></li>@empty<li class="py-3 text-sm text-[var(--color-on-surface-muted)]">No audience preparations yet.</li>@endforelse</ul></section>
</div>
@endsection
