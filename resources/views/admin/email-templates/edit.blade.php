@extends('layouts.app')
@section('title', $template->exists ? 'Edit email template' : 'Create email template')
@section('header-title', 'Email template')
@section('content')
<x-page-header :title="$template->exists ? $template->name : 'Create email template'" description="Compose a reusable message and preview it with synthetic recipient details."
    :breadcrumbs="['Templates' => route('admin.email-templates.index'), $template->exists ? 'Edit' : 'Create' => null]">
    <a class="btn-secondary" href="{{ route('admin.email-templates.index') }}">Back to templates</a>
</x-page-header>
<x-validation-errors />
@if(session('success'))<p role="status" class="mb-5 text-sm text-[var(--color-success-fg)]">{{ session('success') }}</p>@endif
@if($errors->getBag('templateTest')->any())
    <div role="alert" class="mb-5 rounded-lg border border-[var(--color-danger)] p-4">
        <p class="font-semibold">Template test needs attention</p>
        @foreach($errors->getBag('templateTest')->all() as $message)<p class="mt-2 text-sm">{{ $message }}</p>@endforeach
    </div>
@endif
@if($locked)
    <x-alert type="warning" class="mb-5">This template is used by an approved campaign. Its content is locked. Create a new template for changes.</x-alert>
@endif
<div data-email-template-editor data-preview-url="{{ route('admin.email-templates.preview') }}" data-locked="{{ $locked ? 'true' : 'false' }}" class="email-template-workspace min-w-0">
    @php($editorConfig = ['imageHosts' => config('email_campaigns.image_hosts', [])])
    <script type="application/json" data-editor-config>@json($editorConfig)</script>
    <form method="POST" action="{{ $template->exists ? route('admin.email-templates.update', $template) : route('admin.email-templates.store') }}" data-template-form class="space-y-6">
        @csrf
        @if($template->exists)@method('PUT')@endif
        <div class="grid gap-5 md:grid-cols-2">
            <div class="form-field"><label for="email-template-name" class="form-label">Template name</label><input id="email-template-name" name="name" value="{{ old('name', $template->name) }}" maxlength="150" class="form-input w-full" required @readonly($locked)></div>
            <div class="form-field"><label for="email-template-subject" class="form-label">Subject</label><input id="email-template-subject" name="subject" value="{{ old('subject', $template->subject) }}" maxlength="255" class="form-input w-full" required @readonly($locked)></div>
        </div>
        <div class="flex flex-wrap items-end gap-4">
            <div class="form-field"><label for="email-template-format" class="form-label">Message format</label><select id="email-template-format" name="body_format" data-body-format class="form-select" @disabled($locked)><option value="html" @selected(old('body_format', $template->body_format ?? 'text') === 'html')>Visual / HTML</option><option value="text" @selected(old('body_format', $template->body_format ?? 'text') === 'text')>Plain text</option></select></div>
            <p class="max-w-2xl text-sm text-[var(--color-on-surface-muted)]">Supported merge tags: <code>@{{name}}</code>, <code>@{{email}}</code>, <code>@{{employee_id}}</code>, <code>@{{period}}</code>. Use tags in visible text or the subject.</p>
        </div>
        <div data-visual-tools class="space-y-3">
            <div class="flex flex-wrap gap-2" role="group" aria-label="Composition mode">
                <button type="button" data-mode="visual" class="btn-secondary" aria-pressed="true">Visual editor</button>
                <button type="button" data-mode="source" class="btn-secondary" aria-pressed="false">HTML source</button>
            </div>
            <div data-visual-panel>
                <p class="mb-3 text-sm text-[var(--color-on-surface-muted)]">Drag blocks onto the message, or use the keyboard controls below. Saving and previewing sanitize the exported HTML.</p>
                <div class="mb-3 flex flex-wrap items-end gap-2" data-keyboard-tools>
                    <div class="form-field"><label for="template-block-type" class="form-label">Add a block</label><select id="template-block-type" data-block-type class="form-select"><option value="text">Text</option><option value="heading">Heading</option><option value="button">Link button</option><option value="divider">Divider</option></select></div>
                    <button type="button" data-add-block class="btn-secondary" @disabled($locked)>Add block</button>
                    <div class="form-field min-w-0"><label for="template-block-selection" class="form-label">Selected block</label><select id="template-block-selection" data-block-selection class="form-select max-w-full"><option value="">Select a top-level block</option></select></div>
                    <button type="button" data-move="up" class="btn-secondary" @disabled($locked)>Move up</button>
                    <button type="button" data-move="down" class="btn-secondary" @disabled($locked)>Move down</button>
                    <button type="button" data-remove-block class="btn-secondary" @disabled($locked)>Remove block</button>
                </div>
                <div data-editor-canvas class="email-visual-canvas overflow-hidden rounded-lg border border-[var(--color-border)]" aria-label="Visual newsletter editor"></div>
            </div>
        </div>
        <div data-source-panel class="form-field">
            <label for="email-template-body" data-body-label class="form-label">Message source</label>
            <textarea id="email-template-body" name="html_body" data-template-source rows="14" class="form-input w-full font-mono text-sm" maxlength="200000" required aria-describedby="email-source-help" @readonly($locked)>{{ old('html_body', $template->html_body) }}</textarea>
            <p id="email-source-help" class="mt-2 text-sm text-[var(--color-on-surface-muted)]">Visual HTML supports tables, text, links and inline styles. Images require a configured public HTTPS raster-image host. Scripts, forms, custom CSS URLs and active content are removed or rejected.</p>
        </div>
        <input type="hidden" name="design_json" value="" data-template-design>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--color-border)] pt-5">
            <p data-editor-status role="status" aria-live="polite" class="text-sm text-[var(--color-on-surface-muted)]">The HTML source remains available while the visual editor loads.</p>
            <button type="submit" class="btn-primary" data-template-save @disabled($locked)>Save template</button>
        </div>
    </form>
    <section class="mt-7 space-y-4 border-t border-[var(--color-border)] pt-6" aria-labelledby="template-preview-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="template-preview-heading" class="text-lg font-semibold">Message preview</h2>
            <div class="flex flex-wrap gap-2" role="group" aria-label="Preview controls"><button type="button" data-preview-width="desktop" class="btn-secondary" aria-pressed="true">Desktop</button><button type="button" data-preview-width="mobile" class="btn-secondary" aria-pressed="false">Mobile</button><button type="button" data-refresh-preview class="btn-secondary">Refresh preview</button></div>
        </div>
        <p class="text-sm text-[var(--color-on-surface-muted)]">Preview uses Sample Recipient and other synthetic values. Email clients can render layouts differently.</p>
        <p data-preview-error role="alert" class="text-sm text-[var(--color-danger-fg)]" hidden></p>
        <div class="email-preview-shell overflow-auto rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] p-3">
            <iframe title="Sanitized email preview" data-template-preview sandbox="" referrerpolicy="no-referrer" class="email-template-preview mx-auto block min-h-96 w-full border-0 bg-white"></iframe>
        </div>
    </section>
</div>
@if($template->exists)
    <form method="POST" action="{{ route('admin.email-templates.test', $template) }}" class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-lg border border-[var(--color-border)] p-5">
        @csrf
        <div><h2 class="font-semibold">Test the saved template</h2><p class="mt-1 max-w-2xl text-sm text-[var(--color-on-surface-muted)]">Explicitly send the saved message to your account address with synthetic merge values and no attachments. Save your changes first. Campaign approval is unchanged.</p></div>
        <button type="submit" class="btn-secondary">Send synthetic test to myself</button>
    </form>
@endif
@endsection
