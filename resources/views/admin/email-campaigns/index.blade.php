@extends('layouts.app')

@section('title', 'Email Campaigns')
@section('header-icon')<x-icon name="envelope" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Email Campaigns')

@section('content')
@php
    $oldForm = old('_email_campaign_form');
    $hasTemplates = $templates->isNotEmpty();
@endphp

<x-page-header title="Email Campaigns"
    description="Create reusable messages, prepare recipient lists, and manage email delivery."
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Email Campaigns' => null]">
    <a href="{{ route('admin.email-configuration.index') }}" class="btn-secondary text-sm">
        <x-icon name="cog-6-tooth" class="h-4 w-4" />
        Email configuration
    </a>
    <a href="#campaign-history" class="btn-secondary text-sm">
        <x-icon name="clock" class="h-4 w-4" />
        View campaigns
    </a>
</x-page-header>

<x-validation-errors />
@if(session('success'))
    <x-alert type="success" class="mb-4">{{ session('success') }}</x-alert>
@endif
@if(session('error'))
    <x-alert type="error" class="mb-4">{{ session('error') }}</x-alert>
@endif

<div class="mb-6 grid gap-4 sm:grid-cols-2">
    <div class="md-card md-card--static flex items-center gap-4 p-5">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[var(--color-surface-2)] text-[var(--color-primary)]">
            <x-icon name="document-text" class="h-6 w-6" />
        </span>
        <div>
            <p class="text-xs font-medium text-[var(--color-on-surface-muted)]">Available templates</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-[var(--color-on-surface)]">{{ number_format($templates->count()) }}</p>
        </div>
    </div>
    <div class="md-card md-card--static flex items-center gap-4 p-5">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[var(--color-surface-2)] text-[var(--color-primary)]">
            <x-icon name="envelope" class="h-6 w-6" />
        </span>
        <div>
            <p class="text-xs font-medium text-[var(--color-on-surface-muted)]">Email campaigns</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-[var(--color-on-surface)]">{{ number_format($campaigns->total()) }}</p>
        </div>
    </div>
</div>

<div class="mb-6 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-2)] px-5 py-4">
    <p class="text-sm font-semibold text-[var(--color-on-surface)]">Getting started</p>
    <p class="mt-1 text-sm leading-6 text-[var(--color-on-surface-muted)]">
        Create or import an email template, upload a CSV with <span class="font-mono">email</span> and
        <span class="font-mono">name</span> columns, then create a campaign. Review the details before starting delivery.
    </p>
</div>

<div class="grid items-start gap-6 xl:grid-cols-12">
    <div class="space-y-6 xl:col-span-7">
        <x-admin.panel title="1. Create an email template"
            description="Set up the message your recipients will receive. You can reuse the same template for future campaigns.">
            <form method="POST" action="{{ route('admin.email-campaigns.templates.store') }}" class="space-y-5"
                  x-data="{ pdfEnabled: @js($oldForm === 'template' ? (bool) old('pdf_enabled', false) : false), submitting: false }"
                  @submit="submitting = true">
                @csrf
                <input type="hidden" name="_email_campaign_form" value="template">

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="form-field">
                        <label class="form-label" for="template-name">Template name <span aria-hidden="true">*</span></label>
                        <input id="template-name" name="name" type="text" class="form-input" maxlength="150"
                               value="{{ $oldForm === 'template' ? old('name') : '' }}"
                               placeholder="e.g. Customer update" required
                               aria-invalid="{{ $oldForm === 'template' && $errors->has('name') ? 'true' : 'false' }}"
                               @if($oldForm === 'template' && $errors->has('name')) aria-describedby="template-name-error" @endif>
                        @if($oldForm === 'template')
                            @error('name') <p id="template-name-error" class="form-error" role="alert">{{ $message }}</p> @enderror
                        @endif
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="template-subject">Email subject <span aria-hidden="true">*</span></label>
                        <input id="template-subject" name="subject" type="text" class="form-input" maxlength="255"
                               value="{{ $oldForm === 'template' ? old('subject') : '' }}" placeholder="Subject shown in the inbox" required
                               aria-invalid="{{ $oldForm === 'template' && $errors->has('subject') ? 'true' : 'false' }}">
                        @if($oldForm === 'template')
                            @error('subject') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                        @endif
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label" for="template-html-body">Email message <span aria-hidden="true">*</span></label>
                    <textarea id="template-html-body" name="html_body" rows="9" class="form-input w-full text-sm"
                              placeholder="Hello @{{name}},&#10;&#10;Write your message here." required
                              aria-describedby="template-html-help"
                              aria-invalid="{{ $oldForm === 'template' && $errors->has('html_body') ? 'true' : 'false' }}">{{ $oldForm === 'template' ? old('html_body') : '' }}</textarea>
                    <p id="template-html-help" class="mt-1 text-xs text-[var(--color-on-surface-dim)]">
                        Write plain text. Line breaks are kept. Use <code>@{{name}}</code> or <code>@{{email}}</code> to personalize each message.
                    </p>
                    @if($oldForm === 'template')
                        @error('html_body') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                    @endif
                </div>

                <div class="rounded-xl border border-[var(--color-border)] p-4">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="hidden" name="pdf_enabled" value="0">
                        <input name="pdf_enabled" type="checkbox" value="1" x-model="pdfEnabled"
                               class="mt-0.5 rounded border-[var(--color-border-strong)]"
                               @checked($oldForm === 'template' && (bool) old('pdf_enabled', false))
                               aria-controls="template-pdf-options">
                        <span>
                            <span class="block text-sm font-semibold text-[var(--color-on-surface)]">Include a PDF attachment</span>
                            <span class="mt-1 block text-xs leading-5 text-[var(--color-on-surface-muted)]">
                                Include PDF content and protect the attachment with a password of at least 8 characters.
                            </span>
                        </span>
                    </label>
                    <div id="template-pdf-options" x-show="pdfEnabled" x-cloak class="mt-4 space-y-4">
                        <div class="form-field">
                            <label class="form-label" for="template-pdf-body">PDF content</label>
                            <textarea id="template-pdf-body" name="pdf_body" rows="5" class="form-input w-full"
                                      placeholder="Text to include in the PDF attachment" :required="pdfEnabled"
                                      aria-invalid="{{ $oldForm === 'template' && $errors->has('pdf_body') ? 'true' : 'false' }}">{{ $oldForm === 'template' ? old('pdf_body') : '' }}</textarea>
                            @if($oldForm === 'template')
                                @error('pdf_body') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                            @endif
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="template-pdf-password">PDF password <span aria-hidden="true">*</span></label>
                            <input id="template-pdf-password" name="pdf_password" type="password" class="form-input"
                                   autocomplete="new-password" minlength="8" maxlength="128" :required="pdfEnabled"
                                   aria-describedby="template-pdf-password-help"
                                   aria-invalid="{{ $oldForm === 'template' && $errors->has('pdf_password') ? 'true' : 'false' }}">
                            <p id="template-pdf-password-help" class="mt-1 text-xs text-[var(--color-on-surface-dim)]">
                                At least 8 characters. The password will not be shown after saving.
                            </p>
                            @if($oldForm === 'template')
                                @error('pdf_password') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary" :disabled="submitting">
                        <x-icon name="plus" class="h-4 w-4" />
                        <span x-text="submitting ? 'Saving...' : 'Save template'">Save template</span>
                    </button>
                </div>
            </form>
        </x-admin.panel>

        <x-admin.panel title="Or import a document"
            description="Extract text from an existing PDF or image. Import creates a new template that you can review and edit below.">
            <form method="POST" action="{{ route('admin.email-campaigns.import') }}"
                  enctype="multipart/form-data" class="space-y-4"
                  x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                <input type="hidden" name="_email_campaign_form" value="import">
                <div class="form-field">
                    <label class="form-label" for="import-document">Document <span aria-hidden="true">*</span></label>
                    <input id="import-document" name="document" type="file"
                           accept=".pdf,.png,.jpg,.jpeg,.webp" class="form-input w-full cursor-pointer" required
                           aria-describedby="import-document-help"
                           aria-invalid="{{ $errors->has('document') ? 'true' : 'false' }}">
                    <p id="import-document-help" class="mt-1 text-xs text-[var(--color-on-surface-dim)]">
                        PDF, PNG, JPG or WebP, up to 10 MB. Check extracted text in Saved templates after import.
                    </p>
                    @error('document') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-secondary" :disabled="submitting">
                        <x-icon name="arrow-up-tray" class="h-4 w-4" />
                        <span x-text="submitting ? 'Importing...' : 'Import document'">Import document</span>
                    </button>
                </div>
            </form>
        </x-admin.panel>
    </div>

    <div class="space-y-6 xl:col-span-5">
        <x-admin.panel title="2. Prepare a campaign"
            description="Choose a template and upload recipients. The campaign is saved as a draft until you start sending.">
            @unless($hasTemplates)
                <div class="mb-4 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] p-3 text-sm text-[var(--color-on-surface-muted)]" role="status">
                    Create or import a template first to prepare an email campaign.
                </div>
            @endunless
            <form method="POST" action="{{ route('admin.email-campaigns.store') }}" enctype="multipart/form-data"
                  class="space-y-5" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                <input type="hidden" name="_email_campaign_form" value="campaign">

                <div class="form-field">
                    <label class="form-label" for="campaign-name">Campaign name <span aria-hidden="true">*</span></label>
                    <input id="campaign-name" name="name" type="text" class="form-input" maxlength="150"
                           value="{{ $oldForm === 'campaign' ? old('name') : '' }}"
                           placeholder="e.g. October customer outreach" required
                           aria-invalid="{{ $oldForm === 'campaign' && $errors->has('name') ? 'true' : 'false' }}">
                    @if($oldForm === 'campaign')
                        @error('name') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                    @endif
                </div>

                <div class="form-field">
                    <label class="form-label" for="campaign-template">Email template <span aria-hidden="true">*</span></label>
                    <select id="campaign-template" name="email_template_id" class="form-select" required
                            aria-invalid="{{ $errors->has('email_template_id') ? 'true' : 'false' }}">
                        <option value="">Select a template</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}" @selected((string) old('email_template_id') === (string) $template->id)>
                                {{ $template->name }}{{ $template->pdf_enabled ? ' · With PDF' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('email_template_id') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="form-field">
                    <label class="form-label" for="campaign-recipients">Recipients CSV <span aria-hidden="true">*</span></label>
                    <input id="campaign-recipients" name="recipient_csv" type="file"
                           accept=".csv,text/csv" class="form-input w-full cursor-pointer" required
                           aria-describedby="campaign-recipients-help"
                           aria-invalid="{{ $errors->has('recipient_csv') ? 'true' : 'false' }}">
                    <p id="campaign-recipients-help" class="mt-1 text-xs leading-5 text-[var(--color-on-surface-dim)]">
                        Upload a CSV with column headers <span class="font-mono">email,name</span>. Include one person per row.
                    </p>
                    @error('recipient_csv') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="form-field">
                        <label class="form-label" for="campaign-batch-size">Emails per batch</label>
                        <input id="campaign-batch-size" name="batch_size" type="number" min="1" max="100" step="1"
                               value="{{ old('batch_size', 25) }}" class="form-input" required
                               aria-describedby="campaign-batch-help"
                               aria-invalid="{{ $errors->has('batch_size') ? 'true' : 'false' }}">
                        <p id="campaign-batch-help" class="mt-1 text-xs text-[var(--color-on-surface-dim)]">1 to 100 emails.</p>
                        @error('batch_size') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="campaign-delay">Delay between batches</label>
                        <input id="campaign-delay" name="delay_seconds" type="number" min="1" max="3600" step="1"
                               value="{{ old('delay_seconds', 60) }}" class="form-input" required
                               aria-describedby="campaign-delay-help"
                               aria-invalid="{{ $errors->has('delay_seconds') ? 'true' : 'false' }}">
                        <p id="campaign-delay-help" class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Seconds (1 to 3600).</p>
                        @error('delay_seconds') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-2)] p-4">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" id="campaign-permission" name="confirmed_permission" value="1"
                               class="mt-0.5 rounded border-[var(--color-border-strong)]" required
                               @checked((bool) old('confirmed_permission', false))
                               aria-invalid="{{ $errors->has('confirmed_permission') ? 'true' : 'false' }}">
                        <span class="text-sm leading-5 text-[var(--color-on-surface)]">
                            I confirm that every recipient has given permission to receive these emails.
                            <span class="text-[var(--color-danger)]" aria-hidden="true">*</span>
                        </span>
                    </label>
                    @error('confirmed_permission') <p class="form-error mt-2" role="alert">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn-primary w-full justify-center" :disabled="submitting || {{ $hasTemplates ? 'false' : 'true' }}"
                        @disabled(!$hasTemplates)>
                    <x-icon name="check" class="h-4 w-4" />
                    <span x-text="submitting ? 'Creating...' : 'Create draft campaign'">Create draft campaign</span>
                </button>
            </form>
        </x-admin.panel>

    </div>
</div>

<x-admin.panel title="Saved templates" class="mt-6"
    description="Review extracted content, edit each message, and configure a password-protected PDF attachment before sending.">
    @if($hasTemplates)
        <ul class="space-y-3">
            @foreach($templates as $template)
                @php
                    $editing = $oldForm === 'edit_template'
                        && (string) old('_editing_template_id') === (string) $template->id;
                @endphp
                <li class="rounded-xl border border-[var(--color-border)] p-4"
                    x-data="{ editOpen: @js($editing), pdfEnabled: @js($editing ? (bool) old('pdf_enabled', false) : (bool) $template->pdf_enabled), submitting: false }">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="break-words text-sm font-semibold text-[var(--color-on-surface)]">{{ $template->name }}</p>
                            <p class="mt-1 break-words text-xs text-[var(--color-on-surface-muted)]">{{ $template->subject }}</p>
                            @if($template->pdf_enabled)
                                <span class="mt-1 inline-block text-xs font-medium text-[var(--color-primary)]">Password-protected PDF</span>
                            @else
                                <span class="mt-1 inline-block text-xs text-[var(--color-on-surface-dim)]">Email only</span>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            @if($template->pdf_enabled)
                                <a href="{{ route('admin.email-campaigns.preview', $template) }}"
                                   class="btn-secondary text-xs px-3 py-1.5"
                                   aria-label="Preview PDF for {{ $template->name }}">
                                    <x-icon name="eye" class="h-4 w-4" />
                                    Preview PDF
                                </a>
                            @endif
                            <button type="button" class="btn-secondary text-xs px-3 py-1.5"
                                    @click="editOpen = !editOpen"
                                    :aria-expanded="editOpen.toString()"
                                    aria-controls="template-editor-{{ $template->id }}">
                                <x-icon name="pencil" class="h-4 w-4" />
                                <span x-text="editOpen ? 'Close editor' : 'Edit template'">Edit template</span>
                            </button>
                        </div>
                    </div>

                    <div id="template-editor-{{ $template->id }}" x-show="editOpen" x-cloak x-collapse
                         class="mt-4 border-t border-[var(--color-border)] pt-4">
                        <form method="POST" action="{{ route('admin.email-campaigns.templates.update', $template) }}"
                              class="space-y-4" @submit="submitting = true">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_email_campaign_form" value="edit_template">
                            <input type="hidden" name="_editing_template_id" value="{{ $template->id }}">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="form-field">
                                    <label class="form-label" for="edit-name-{{ $template->id }}">Template name <span aria-hidden="true">*</span></label>
                                    <input id="edit-name-{{ $template->id }}" name="name" type="text" class="form-input"
                                           maxlength="150" required value="{{ $editing ? old('name') : $template->name }}"
                                           aria-invalid="{{ $editing && $errors->has('name') ? 'true' : 'false' }}">
                                    @if($editing)
                                        @error('name') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                                    @endif
                                </div>
                                <div class="form-field">
                                    <label class="form-label" for="edit-subject-{{ $template->id }}">Email subject <span aria-hidden="true">*</span></label>
                                    <input id="edit-subject-{{ $template->id }}" name="subject" type="text" class="form-input"
                                           maxlength="255" required value="{{ $editing ? old('subject') : $template->subject }}"
                                           aria-invalid="{{ $editing && $errors->has('subject') ? 'true' : 'false' }}">
                                    @if($editing)
                                        @error('subject') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                                    @endif
                                </div>
                            </div>

                            <div class="form-field">
                                <label class="form-label" for="edit-html-{{ $template->id }}">Email message <span aria-hidden="true">*</span></label>
                                <textarea id="edit-html-{{ $template->id }}" name="html_body" rows="8"
                                          class="form-input w-full text-sm" required
                                          aria-invalid="{{ $editing && $errors->has('html_body') ? 'true' : 'false' }}">{{ $editing ? old('html_body') : $template->html_body }}</textarea>
                                <p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">
                                    Plain text; use <code>@{{name}}</code> and <code>@{{email}}</code> for personalization.
                                </p>
                                @if($editing)
                                    @error('html_body') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                                @endif
                            </div>

                            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] p-4">
                                <label class="flex cursor-pointer items-start gap-3">
                                    <input type="hidden" name="pdf_enabled" value="0">
                                    <input name="pdf_enabled" type="checkbox" value="1" x-model="pdfEnabled"
                                           @checked($editing ? (bool) old('pdf_enabled', false) : (bool) $template->pdf_enabled)
                                           class="mt-0.5 rounded border-[var(--color-border-strong)]"
                                           aria-controls="edit-pdf-options-{{ $template->id }}">
                                    <span class="text-sm font-semibold text-[var(--color-on-surface)]">Include password-protected PDF</span>
                                </label>
                                <div id="edit-pdf-options-{{ $template->id }}" x-show="pdfEnabled" x-cloak class="mt-4 grid gap-4 md:grid-cols-2">
                                    <div class="form-field">
                                        <label class="form-label" for="edit-pdf-body-{{ $template->id }}">PDF content <span aria-hidden="true">*</span></label>
                                        <textarea id="edit-pdf-body-{{ $template->id }}" name="pdf_body" rows="6"
                                                  class="form-input w-full" :required="pdfEnabled"
                                                  aria-invalid="{{ $editing && $errors->has('pdf_body') ? 'true' : 'false' }}">{{ $editing ? old('pdf_body') : $template->pdf_body }}</textarea>
                                        @if($editing)
                                            @error('pdf_body') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                                        @endif
                                    </div>
                                    <div class="form-field">
                                        <label class="form-label" for="edit-pdf-password-{{ $template->id }}">PDF password</label>
                                        <input id="edit-pdf-password-{{ $template->id }}" name="pdf_password" type="password"
                                               class="form-input" autocomplete="new-password" minlength="8" maxlength="128"
                                               :required="pdfEnabled && {{ $template->pdf_enabled ? 'false' : 'true' }}"
                                               aria-describedby="edit-pdf-help-{{ $template->id }}"
                                               aria-invalid="{{ $editing && $errors->has('pdf_password') ? 'true' : 'false' }}">
                                        <p id="edit-pdf-help-{{ $template->id }}" class="mt-2 text-xs leading-5 text-[var(--color-on-surface-dim)]">
                                            {{ $template->pdf_enabled
                                                ? 'Leave blank to keep the current password, or enter at least 8 characters to replace it.'
                                                : 'Set a password of at least 8 characters when enabling the PDF.' }}
                                            Saved passwords are never displayed.
                                        </p>
                                        @if($editing)
                                            @error('pdf_password') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="btn-primary" :disabled="submitting">
                                    <x-icon name="check" class="h-4 w-4" />
                                    <span x-text="submitting ? 'Saving...' : 'Save changes'">Save changes</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <p class="py-6 text-center text-sm text-[var(--color-on-surface-muted)]">
            No templates yet. Create one or import a document to get started.
        </p>
    @endif
</x-admin.panel>

<section id="campaign-history" class="mt-6" aria-labelledby="campaign-history-title">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 id="campaign-history-title" class="text-lg font-semibold text-[var(--color-on-surface)]">Campaign history</h2>
            <p class="mt-1 text-sm text-[var(--color-on-surface-muted)]">
                Track recipient counts, delivery progress, and available campaign actions.
            </p>
        </div>
        <span class="text-xs text-[var(--color-on-surface-muted)]">{{ number_format($campaigns->total()) }} total campaigns</span>
    </div>

    <x-table.index caption="Email campaigns">
        <x-table.head :columns="[
            ['label' => 'Campaign'],
            ['label' => 'Status'],
            ['label' => 'Recipients', 'align' => 'right'],
            ['label' => 'Delivery'],
            ['label' => 'Created'],
            ['label' => 'Actions', 'align' => 'right'],
        ]" />
        @if($campaigns->isNotEmpty())
            <tbody>
                @foreach($campaigns as $campaign)
                    @php
                        $status = (string) $campaign->status;
                        $recipientCount = max(0, (int) $campaign->recipient_count);
                        $sentCount = max(0, (int) $campaign->sent_count);
                        $failedCount = max(0, (int) $campaign->failed_count);
                        $skippedCount = max(0, (int) $campaign->skipped_count);
                        $processed = min($recipientCount, $sentCount + $failedCount + $skippedCount);
                        $badgeType = match ($status) {
                            'completed' => 'active',
                            'sending' => 'info',
                            'queued' => 'pending',
                            'cancelled' => 'inactive',
                            default => 'primary',
                        };
                    @endphp
                    <tr>
                        <td>
                            <span class="font-medium text-[var(--color-on-surface)]">{{ $campaign->name }}</span>
                            @if($campaign->template)
                                <span class="mt-1 block text-xs text-[var(--color-on-surface-muted)]">{{ $campaign->template->name }}</span>
                            @endif
                        </td>
                        <td><x-badge :type="$badgeType">{{ ucfirst($status) }}</x-badge></td>
                        <td class="text-right tabular-nums">{{ number_format($recipientCount) }}</td>
                        <td>
                            <div class="min-w-[11rem] space-y-1.5">
                                <p class="text-xs tabular-nums text-[var(--color-on-surface-muted)]">
                                    <span class="font-medium text-[var(--color-on-surface)]">{{ number_format($sentCount) }}</span> sent
                                    <span aria-hidden="true">·</span>
                                    <span class="{{ $failedCount > 0 ? 'text-[var(--color-danger)]' : '' }}">{{ number_format($failedCount) }} failed</span>
                                    <span aria-hidden="true">·</span>
                                    {{ number_format($skippedCount) }} skipped
                                </p>
                                <progress max="{{ max(1, $recipientCount) }}" value="{{ $processed }}"
                                          class="h-1.5 w-full rounded-full accent-[var(--color-primary)]"
                                          aria-label="Recipients processed for {{ $campaign->name }}"></progress>
                            </div>
                        </td>
                        <td class="whitespace-nowrap text-xs text-[var(--color-on-surface-muted)]">
                            {{ $campaign->created_at?->format('M j, Y') ?? '—' }}
                        </td>
                        <td>
                            <div class="flex justify-end gap-2">
                                @if($status === 'draft')
                                    <form method="POST" action="{{ route('admin.email-campaigns.send', $campaign) }}"
                                          onsubmit="return confirm('Start sending this campaign to its recipients?');">
                                        @csrf
                                        <button type="submit" class="btn-primary text-xs px-3 py-1.5"
                                                aria-label="Start sending campaign {{ $campaign->name }}">
                                            <x-icon name="paper-airplane" class="h-3.5 w-3.5" />
                                            Send
                                        </button>
                                    </form>
                                @elseif(in_array($status, ['queued', 'sending'], true))
                                    <form method="POST" action="{{ route('admin.email-campaigns.cancel', $campaign) }}"
                                          onsubmit="return confirm('Cancel this campaign and stop pending emails?');">
                                        @csrf
                                        <button type="submit" class="btn-secondary text-xs px-3 py-1.5"
                                                aria-label="Cancel campaign {{ $campaign->name }}">
                                            Cancel
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-[var(--color-on-surface-dim)]">No actions</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        @else
            <x-table.empty :colspan="6" message="No email campaigns yet."
                description="Create your first draft above. It will appear here before sending." />
        @endif
        <x-slot:footer>
            <x-table.pagination :paginator="$campaigns" />
        </x-slot:footer>
    </x-table.index>
</section>
@endsection
