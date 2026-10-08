@extends('layouts.app')

@section('title', 'Email Configuration')
@section('header-icon')<x-icon name="cog-6-tooth" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Email Configuration')

@section('content')
@php
    $databaseEnabled = (bool) ($settings?->enabled ?? false);
    $savedHost = $settings?->host;
    $savedSenderName = $settings?->from_name;
    $savedSenderAddress = $settings?->from_address;
    $hasSavedPassword = $settings && filled($settings->getRawOriginal('password'));
    $environmentMailer = (string) config('mail.default');

    $activeHost = match ($mailSource) {
        'database' => $savedHost,
        'environment' => $environmentMailer === 'smtp'
            ? config('mail.mailers.smtp.host')
            : ($environmentMailer !== '' ? ucfirst($environmentMailer).' mailer' : null),
        default => null,
    };
    $activeSenderName = match ($mailSource) {
        'database' => $savedSenderName,
        'environment' => config('mail.from.name'),
        default => null,
    };
    $activeSenderAddress = match ($mailSource) {
        'database' => $savedSenderAddress,
        'environment' => config('mail.from.address'),
        default => null,
    };
    $sourceLabel = match ($mailSource) {
        'database' => 'Saved SMTP settings',
        'environment' => 'Server environment',
        default => 'Not configured',
    };
@endphp

<x-page-header title="Email Configuration"
    description="Configure the outgoing mail server and sender used by email campaigns."
    :breadcrumbs="[
        'Admin' => route('admin.dashboard'),
        'Email Campaigns' => route('admin.email-campaigns.index'),
        'Email Configuration' => null,
    ]">
    <a href="{{ route('admin.email-campaigns.index') }}" class="btn-secondary text-sm">
        <x-icon name="envelope" class="h-4 w-4" />
        Email campaigns
    </a>
</x-page-header>

@if(session('success'))
    <x-alert type="success" class="mb-4">{{ session('success') }}</x-alert>
@endif
@if(session('error'))
    <x-alert type="error" class="mb-4">{{ session('error') }}</x-alert>
@endif

<div class="mb-6 grid gap-4 md:grid-cols-3">
    <div class="md-card md-card--static p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-medium text-[var(--color-on-surface-muted)]">Mail source</p>
                <p class="mt-1 text-base font-semibold text-[var(--color-on-surface)]">{{ $sourceLabel }}</p>
            </div>
            <x-badge :type="$smtpReady ? 'active' : 'error'">{{ $smtpReady ? 'Configured' : 'Not configured' }}</x-badge>
        </div>
        <p class="mt-3 text-xs leading-5 text-[var(--color-on-surface-dim)]">
            @if($mailSource === 'database')
                Campaign mail is using the SMTP settings saved below.
            @elseif($mailSource === 'environment')
                Saved SMTP settings are not active, so mail uses the server's environment configuration.
            @else
                No usable outgoing mail configuration is currently available.
            @endif
        </p>
    </div>

    <div class="md-card md-card--static p-5">
        <p class="text-xs font-medium text-[var(--color-on-surface-muted)]">Current mail server</p>
        <p class="mt-1 break-all text-base font-semibold text-[var(--color-on-surface)]">{{ $activeHost ?: 'Not configured' }}</p>
        @if($settings && ! $databaseEnabled)
            <p class="mt-2 text-xs text-[var(--color-on-surface-dim)]">
                Saved SMTP host: <span class="font-mono">{{ $savedHost }}</span>
            </p>
        @endif
    </div>

    <div class="md-card md-card--static p-5">
        <p class="text-xs font-medium text-[var(--color-on-surface-muted)]">Current sender</p>
        <p class="mt-1 text-base font-semibold text-[var(--color-on-surface)]">{{ $activeSenderName ?: 'Not configured' }}</p>
        <p class="mt-1 break-all text-xs text-[var(--color-on-surface-muted)]">{{ $activeSenderAddress ?: 'No sender address' }}</p>
    </div>
</div>

<div class="grid items-start gap-6 xl:grid-cols-12">
    <div class="xl:col-span-8">
        <x-admin.panel title="SMTP settings"
            description="Save the mail server connection and sender identity used for campaign delivery.">
            @if($errors->hasAny(['enabled', 'host', 'port', 'encryption', 'username', 'password', 'from_name', 'from_address']))
                <x-alert type="error" title="Email configuration was not saved" class="mb-5">
                    Review the highlighted fields below and try again.
                </x-alert>
            @endif

            <form method="POST" action="{{ route('admin.email-configuration.update') }}"
                  class="space-y-6" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                @method('PUT')

                <section>
                    <div class="flex flex-col gap-3 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-2)] p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-[var(--color-on-surface)]">Use saved SMTP settings</p>
                            <p class="mt-1 text-xs leading-5 text-[var(--color-on-surface-muted)]">
                                When enabled, email campaigns use this database configuration. When disabled, the application falls back to its server environment mail settings.
                            </p>
                        </div>
                        <label class="inline-flex shrink-0 cursor-pointer items-center gap-2 text-sm font-medium text-[var(--color-on-surface)]">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" name="enabled" value="1"
                                   class="h-4 w-4 rounded border-[var(--color-border-strong)]"
                                   @checked((bool) old('enabled', $settings?->enabled ?? false))>
                            Enabled
                        </label>
                    </div>
                    @error('enabled')
                        <p class="form-error mt-2" role="alert">{{ $message }}</p>
                    @enderror
                </section>

                <section>
                    <div class="mb-4">
                        <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Connection</h3>
                        <p class="mt-1 text-xs text-[var(--color-on-surface-muted)]">
                            TLS commonly uses port 587. SSL commonly uses port 465. Use the port provided by your email provider.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.input name="host" label="SMTP host"
                            :value="$settings?->host" required maxlength="255"
                            placeholder="smtp.example.com"
                            help="Hostname or IP address supplied by your email provider." />

                        <x-form.input name="port" type="number" label="SMTP port"
                            :value="$settings?->port ?? 587" required min="1" max="65535"
                            help="Typical ports: 587 for TLS, 465 for SSL." />

                        <x-form.select name="encryption" label="Connection security"
                            :options="['tls' => 'TLS (recommended)', 'ssl' => 'SSL', 'none' => 'None']"
                            :selected="$settings?->encryption ?? 'tls'" :empty="false" required
                            help="TLS on port 587 is the usual default for authenticated SMTP." />

                        <x-form.input name="username" label="SMTP username"
                            :value="$settings?->username" maxlength="255"
                            autocomplete="username"
                            placeholder="account@example.com"
                            help="Leave blank only if your SMTP server does not require authentication." />

                        <div class="sm:col-span-2">
                            <x-form.input name="password" type="password" label="SMTP password"
                                maxlength="2048" autocomplete="new-password"
                                :help="$hasSavedPassword
                                    ? 'A password is already saved. Leave this field blank to keep it unchanged.'
                                    : 'Enter the SMTP password if the server requires authentication. It is stored encrypted and never shown here.'" />
                        </div>
                    </div>
                </section>

                <section class="border-t border-[var(--color-border)] pt-5">
                    <div class="mb-4">
                        <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Sender identity</h3>
                        <p class="mt-1 text-xs text-[var(--color-on-surface-muted)]">
                            This name and address appear in the From field of campaign emails.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.input name="from_name" label="From name"
                            :value="$settings?->from_name" required maxlength="255"
                            placeholder="Company Support" />

                        <x-form.input name="from_address" type="email" label="From email address"
                            :value="$settings?->from_address" required maxlength="255"
                            placeholder="support@example.com" autocomplete="email" />
                    </div>
                </section>

                <div class="flex justify-end border-t border-[var(--color-border)] pt-5">
                    <button type="submit" class="btn-primary" :disabled="submitting">
                        <x-icon name="check" class="h-4 w-4" />
                        <span x-text="submitting ? 'Saving...' : 'Save email configuration'">Save email configuration</span>
                    </button>
                </div>
            </form>
        </x-admin.panel>
    </div>

    <div class="xl:col-span-4">
        <x-admin.panel title="Send a test email"
            description="Verify the active outgoing mail connection with a single test message.">
            @if($errors->has('recipient'))
                <x-alert type="error" title="Test email was not sent" class="mb-4">
                    Enter a valid recipient email address.
                </x-alert>
            @endif

            @unless($smtpReady)
                <x-alert type="warning" title="Email service not configured" class="mb-4">
                    Save and enable a working SMTP connection before sending a test email.
                </x-alert>
            @endunless

            <form method="POST" action="{{ route('admin.email-configuration.test') }}"
                  class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                <x-form.input name="recipient" type="email" label="Test recipient"
                    required maxlength="255" autocomplete="email"
                    placeholder="you@example.com"
                    help="A short connection test will be sent to this address." />

                <button type="submit" class="btn-secondary w-full justify-center"
                        :disabled="submitting || {{ $smtpReady ? 'false' : 'true' }}"
                        @disabled(! $smtpReady)>
                    <x-icon name="paper-airplane" class="h-4 w-4" />
                    <span x-text="submitting ? 'Sending...' : 'Send test email'">Send test email</span>
                </button>
            </form>

            <div class="mt-5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-[var(--color-on-surface-muted)]">Security note</p>
                <p class="mt-2 text-xs leading-5 text-[var(--color-on-surface-dim)]">
                    Saved SMTP passwords are encrypted and are never displayed back in this page. Leaving the password field blank preserves the current saved credential.
                </p>
            </div>
        </x-admin.panel>
    </div>
</div>
@endsection
