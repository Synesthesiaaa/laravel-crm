@extends('layouts.app')
@section('title', 'Email templates')
@section('header-title', 'Email templates')
@section('content')
<x-page-header title="Email templates" description="Reusable messages for your email campaigns."
    :breadcrumbs="['Email campaigns' => route('admin.email-management.index'), 'Templates' => null]">
    <a href="{{ route('admin.email-templates.create') }}" class="btn-primary">Create template</a>
</x-page-header>
@if(session('success'))<p role="status" class="mb-5 text-sm text-[var(--color-success-fg)]">{{ session('success') }}</p>@endif
<form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
    <div class="form-field min-w-0 flex-1">
        <label for="template-search" class="form-label">Search templates</label>
        <input id="template-search" type="search" name="search" value="{{ $search }}" maxlength="150" class="form-input w-full" placeholder="Template name">
    </div>
    <button class="btn-secondary" type="submit">Search</button>
    @if($search !== '')<a class="btn-secondary" href="{{ route('admin.email-templates.index') }}">Clear</a>@endif
</form>
<div class="overflow-x-auto rounded-lg border border-[var(--color-border)]">
    <table class="w-full text-left text-sm">
        <thead class="bg-[var(--color-surface-2)]"><tr><th class="px-4 py-3 font-semibold">Template</th><th class="px-4 py-3 font-semibold">Format</th><th class="px-4 py-3 font-semibold">Updated</th><th class="px-4 py-3"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody class="divide-y divide-[var(--color-border)]">
        @forelse($templates as $template)
            <tr>
                <td class="px-4 py-4"><p class="font-semibold break-words">{{ $template->name }}</p><p class="mt-1 max-w-xl break-words text-[var(--color-on-surface-muted)]">{{ $template->subject }}</p></td>
                <td class="px-4 py-4">{{ $template->pdf_enabled ? 'Protected PDF' : (($template->body_format ?? 'text') === 'html' ? 'Visual / HTML' : 'Plain text') }}</td>
                <td class="whitespace-nowrap px-4 py-4 text-[var(--color-on-surface-muted)]">{{ $template->updated_at?->format('M j, Y') }}</td>
                <td class="px-4 py-3 text-right">
                    @if($template->pdf_enabled)
                        <a href="{{ route('admin.email-campaigns.index') }}" class="btn-secondary text-sm">Confidential workflow</a>
                    @else
                        <a href="{{ route('admin.email-templates.edit', $template) }}" class="btn-secondary text-sm" aria-label="Open {{ $template->name }}">Open template</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-5 py-10"><p class="font-semibold">{{ $search !== '' ? 'No matching templates' : 'Create your first email template' }}</p><p class="mt-2 text-[var(--color-on-surface-muted)]">{{ $search !== '' ? 'Try a different name or clear your search.' : 'Build a visual newsletter or write a plain-text message, then reuse it in campaigns.' }}</p></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $templates->links() }}</div>
@endsection
