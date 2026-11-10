@extends('layouts.app')
@section('title', 'Email suppressions')
@section('header-title', 'Email suppressions')
@section('content')
<x-page-header title="Email suppressions" description="Review addresses blocked from campaign delivery and add a manual block.">
    <a href="{{ route('admin.email-campaigns.index') }}" class="btn-secondary">Email campaigns</a>
</x-page-header>
<x-validation-errors />
@if(session('success'))<x-alert type="success" class="mb-5">{{ session('success') }}</x-alert>@endif
<div class="grid items-start gap-6 xl:grid-cols-3">
    <section class="xl:col-span-1">
        <x-admin.panel title="Add a suppression" description="Global suppressions affect every sender. Marketing opt-outs, complaints and hard bounces cannot be cleared here.">
            <form method="post" action="{{ route('admin.email-suppressions.store') }}" class="space-y-4">
                @csrf
                <div class="form-field"><label for="suppression-email" class="form-label">Email address</label><input id="suppression-email" type="email" name="email" value="{{ old('email') }}" class="form-input w-full" maxlength="255" required></div>
                <div class="form-field"><label for="suppression-purpose" class="form-label">Block purpose</label><select id="suppression-purpose" name="purpose" class="form-select w-full"><option value="all">All email campaigns</option><option value="marketing">Marketing</option><option value="transactional">Transactional</option><option value="payroll">Payroll</option></select></div>
                <button class="btn-primary" type="submit">Add suppression</button>
            </form>
        </x-admin.panel>
    </section>
    <section class="min-w-0 xl:col-span-2" aria-labelledby="suppression-history-heading">
        <h2 id="suppression-history-heading" class="mb-4 text-lg font-semibold">Suppression history</h2>
        <form method="get" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="form-field flex-1"><label for="suppression-search" class="form-label">Email starts with</label><input id="suppression-search" class="form-input w-full" name="search" value="{{ request('search') }}" maxlength="255"></div>
            <div class="form-field"><label for="suppression-reason" class="form-label">Reason</label><select id="suppression-reason" class="form-select" name="reason"><option value="">All reasons</option>@foreach(['manual' => 'Manual', 'hard_bounce' => 'Hard bounce', 'complaint' => 'Complaint', 'unsubscribe' => 'Unsubscribe'] as $value => $label)<option value="{{ $value }}" @selected(request('reason') === $value)>{{ $label }}</option>@endforeach</select></div>
            <button type="submit" class="btn-secondary">Filter</button>
        </form>
        <div class="overflow-x-auto rounded-lg border border-[var(--color-border)]">
            <table class="w-full text-left text-sm"><thead class="bg-[var(--color-surface-2)]"><tr><th scope="col" class="p-3">Address</th><th scope="col" class="p-3">Reason / purpose</th><th scope="col" class="p-3">Added</th><th scope="col" class="p-3">Action</th></tr></thead>
                <tbody>@forelse($suppressions as $record)<tr class="border-t border-[var(--color-border)]"><td class="break-all p-3">{{ $record->email }}</td><td class="p-3">{{ str_replace('_', ' ', ucfirst($record->reason)) }}<span class="block text-xs text-[var(--color-on-surface-muted)]">{{ ucfirst($record->purpose) }}</span></td><td class="whitespace-nowrap p-3">{{ $record->created_at->format('Y-m-d H:i') }}</td><td class="p-3">@if($record->reason === 'manual')<form method="post" action="{{ route('admin.email-suppressions.destroy', $record) }}">@csrf @method('delete')<button class="btn-secondary" type="submit" aria-label="Remove manual suppression for {{ $record->email }}">Remove</button></form>@else<span class="text-xs text-[var(--color-on-surface-muted)]">Protected</span>@endif</td></tr>@empty<tr><td colspan="4" class="p-6 text-[var(--color-on-surface-muted)]">No suppressions match these filters.</td></tr>@endforelse</tbody>
            </table>
        </div>
        <div class="mt-4">{{ $suppressions->links() }}</div>
    </section>
</div>
@endsection
