<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $brandName = trim((string) data_get($branding, 'name', config('app.name', 'CRM'))) ?: 'CRM';
        $faviconUrl = data_get($branding, 'favicon_path')
            ? data_get($branding, 'favicon_url', '/favicon.ico')
            : '/favicon.ico';
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ $brandName }} keeps customer details, calls, forms, follow-ups, and reports together in one easy-to-use workspace.">
    <script>
      (function() {
        var t = 'dark';
        try { t = localStorage.getItem('theme') || 'dark'; } catch (e) {}
        document.documentElement.setAttribute('data-theme', t);
      })();
    </script>
    <title>{{ $brandName }} | Call Center CRM</title>
    <link rel="icon" href="{{ $faviconUrl }}">
    <link rel="shortcut icon" href="{{ $faviconUrl }}">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen overflow-x-hidden bg-[var(--color-surface)] text-[var(--color-on-surface)] antialiased">
    <a href="#main-content"
       class="sr-only z-[100] rounded-md bg-[var(--color-primary)] px-4 py-3 font-semibold text-[var(--color-primary-foreground)] focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
        Skip to main content
    </a>

    <header class="sticky top-0 z-50 border-b border-[var(--color-border)] bg-[color-mix(in_srgb,var(--color-surface)_88%,transparent)] backdrop-blur-xl">
        <div class="mx-auto flex h-16 max-w-[1200px] items-center justify-between gap-5 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="min-w-0 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--color-primary)]" aria-label="{{ $brandName }} home">
                <x-brand
                    :branding="$branding"
                    class="max-w-[calc(100vw-8.5rem)] sm:max-w-xs [&>span:last-child]:truncate [&>span:last-child]:whitespace-nowrap"
                />
            </a>

            <nav class="hidden items-center gap-7 text-sm font-medium text-[var(--color-on-surface-muted)] md:flex" aria-label="Primary navigation">
                <a class="transition-colors hover:text-[var(--color-on-surface)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--color-primary)]" href="#workflow">How it works</a>
                <a class="transition-colors hover:text-[var(--color-on-surface)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--color-primary)]" href="#telephony">Calling</a>
                <a class="transition-colors hover:text-[var(--color-on-surface)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--color-primary)]" href="#visibility">Reports</a>
            </nav>

            <a href="{{ route('login') }}"
               class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-[var(--color-primary-foreground)] transition hover:bg-[var(--color-primary-hover)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--color-primary)]">
                Sign in
                <x-icon name="arrow-right-on-rectangle" class="h-4 w-4" />
            </a>
        </div>
    </header>

    <main id="main-content">
        <section class="relative isolate overflow-hidden border-b border-[var(--color-border)]">
            <div class="pointer-events-none absolute inset-0 -z-10" style="background: radial-gradient(circle at 72% 18%, color-mix(in srgb, var(--color-primary) 12%, transparent), transparent 34%);"></div>
            <div class="mx-auto grid max-w-[1200px] items-center gap-12 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-[0.9fr_1.1fr] lg:gap-14 lg:px-8 lg:py-24">
                <div class="max-w-2xl">
                    <p class="mb-5 text-xs font-bold uppercase tracking-[0.14em] text-[var(--color-primary)]">
                        One simple workspace for call center teams
                    </p>
                    <h1 class="max-w-3xl text-4xl font-bold leading-[1.03] tracking-[-0.035em] text-[var(--color-on-surface)] sm:text-5xl lg:text-6xl">
                        Handle calls, customer details, and follow-ups in one place
                    </h1>
                    <p class="mt-6 max-w-2xl text-base leading-7 text-[var(--color-on-surface-muted)] sm:text-lg sm:leading-8">
                        See the customer you are speaking with, handle the call, fill in the right form, save the call result, schedule a follow-up, and check reports without jumping between different tools.
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('login') }}"
                           class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-[var(--color-primary)] px-6 text-sm font-semibold text-[var(--color-primary-foreground)] transition hover:bg-[var(--color-primary-hover)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--color-primary)]">
                            Sign in
                            <x-icon name="chevron-right" class="h-4 w-4" />
                        </a>
                        <a href="#workflow"
                           class="inline-flex min-h-12 items-center justify-center rounded-lg border border-[var(--color-border-strong)] bg-[var(--color-surface-1)] px-6 text-sm font-semibold text-[var(--color-on-surface)] transition hover:bg-[var(--color-surface-2)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--color-primary)]">
                            See how it works
                        </a>
                    </div>

                    <div class="mt-9 grid gap-3 border-t border-[var(--color-border)] pt-6 sm:grid-cols-3">
                        <div class="flex items-center gap-2 text-sm text-[var(--color-on-surface-muted)]">
                            <x-icon name="phone" class="h-4 w-4 shrink-0 text-[var(--color-primary)]" />
                            <span>Call from your browser</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-[var(--color-on-surface-muted)]">
                            <x-icon name="list-bullet" class="h-4 w-4 shrink-0 text-[var(--color-primary)]" />
                            <span>Customer details in one place</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-[var(--color-on-surface-muted)]">
                            <x-icon name="signal" class="h-4 w-4 shrink-0 text-[var(--color-primary)]" />
                            <span>Up-to-date call status</span>
                        </div>
                    </div>
                </div>

                <figure class="relative mx-auto w-full max-w-[620px] lg:max-w-none" aria-labelledby="landing-preview-caption">
                    <figcaption id="landing-preview-caption" class="sr-only">
                        Example agent workspace showing customer details, browser calling, form entry, call results, and follow-up.
                    </figcaption>
                    <div aria-hidden="true" class="overflow-hidden rounded-2xl border border-[var(--color-border-strong)] bg-[var(--color-surface-card)] shadow-2xl shadow-black/25">
                        <div class="flex items-center justify-between gap-4 border-b border-[var(--color-border)] bg-[var(--color-surface-1)] px-4 py-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[var(--color-primary-muted)] text-[var(--color-primary)]">
                                    <x-icon name="signal" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-[var(--color-on-surface)]">Agent workspace</p>
                                    <p class="truncate text-xs text-[var(--color-on-surface-dim)]">Customer details stay with the call</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-[var(--color-success-muted)] px-2.5 py-1 text-[11px] font-semibold text-[var(--color-success-fg)]">
                                <span class="h-1.5 w-1.5 rounded-full bg-[var(--color-success)]"></span>
                                Ready
                            </span>
                        </div>

                        <div class="grid gap-px bg-[var(--color-border)] sm:grid-cols-3">
                            @foreach ([
                                ['Customer details', 'Know who you are speaking with', 'Keep the customer record and the information your team needs beside the call.', 'user'],
                                ['Calling', 'Handle calls in the browser', 'Keep call controls inside the same workspace instead of switching screens.', 'phone'],
                                ['After the call', 'Save the result and next step', 'Record what happened and schedule a follow-up when one is needed.', 'clipboard-document-check'],
                            ] as [$eyebrow, $title, $copy, $icon])
                                <div class="bg-[var(--color-surface-card)] p-5">
                                    <div>
                                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--color-primary-muted)] text-[var(--color-primary)]">
                                            <x-icon :name="$icon" class="h-4 w-4" />
                                        </span>
                                        <p class="mt-5 text-[11px] font-bold uppercase tracking-[0.12em] text-[var(--color-on-surface-dim)]">{{ $eyebrow }}</p>
                                        <p class="mt-2 text-sm font-semibold text-[var(--color-on-surface)]">{{ $title }}</p>
                                        <p class="mt-1 text-xs leading-5 text-[var(--color-on-surface-muted)]">{{ $copy }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex items-center gap-3 border-t border-[var(--color-border)] bg-[var(--color-primary-muted)] px-5 py-4">
                            <x-icon name="check-circle" class="h-5 w-5 shrink-0 text-[var(--color-primary)]" />
                            <p class="text-xs font-semibold text-[var(--color-on-surface)]">The call result and next follow-up stay connected to the customer record.</p>
                        </div>
                    </div>
                </figure>
            </div>
        </section>

        <section id="workflow" class="scroll-mt-24 border-b border-[var(--color-border)] bg-[var(--color-surface-1)]">
            <div class="mx-auto max-w-[1200px] px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                <div class="max-w-2xl">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--color-primary)]">Simple from start to finish</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-[-0.025em] sm:text-4xl">Everything an agent needs for a call</h2>
                    <p class="mt-4 text-base leading-7 text-[var(--color-on-surface-muted)]">Agents can open a customer, handle the call, complete the right form, save the result, and set the next step without moving between separate systems.</p>
                </div>

                <ol class="mt-12 grid gap-8 md:grid-cols-4">
                    @foreach ([
                        ['01', 'Open the customer', 'See the customer record and the information needed for the assigned campaign.', 'user'],
                        ['02', 'Handle the call', 'Use the call controls beside the customer record in the browser.', 'phone'],
                        ['03', 'Fill in the form', 'Enter the important details while the conversation is still fresh.', 'clipboard-document-list'],
                        ['04', 'Save what happens next', 'Record the call result and schedule a follow-up when needed.', 'check-circle'],
                    ] as [$number, $title, $copy, $icon])
                        <li class="relative border-t border-[var(--color-border-strong)] pt-5">
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-xs font-bold tracking-[0.12em] text-[var(--color-on-surface-dim)]">{{ $number }}</span>
                                <x-icon :name="$icon" class="h-5 w-5 text-[var(--color-primary)]" />
                            </div>
                            <h3 class="mt-6 text-lg font-semibold">{{ $title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-[var(--color-on-surface-muted)]">{{ $copy }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section id="telephony" class="scroll-mt-24 border-b border-[var(--color-border)]">
            <div class="mx-auto grid max-w-[1200px] gap-12 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-[0.9fr_1.1fr] lg:items-start lg:px-8">
                <div class="lg:sticky lg:top-28">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--color-primary)]">Calling made easier</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-[-0.025em] sm:text-4xl">Keep the call beside the customer record</h2>
                    <p class="mt-4 max-w-xl text-base leading-7 text-[var(--color-on-surface-muted)]">The CRM works with your VICIdial and Asterisk setup so agents can handle calls and customer information from the same browser workspace.</p>
                </div>

                <div class="divide-y divide-[var(--color-border)] border-y border-[var(--color-border)]">
                    @foreach ([
                        ['Call from the browser', 'Agents can use call controls without leaving the customer workspace.', 'computer-desktop'],
                        ['Works with VICIdial', 'Keep the calling process your team already uses while bringing the related CRM work into one place.', 'server'],
                        ['Keeps call status updated', 'The workspace can reflect changes in the call so agents and supervisors can follow what is happening.', 'signal'],
                        ['Built for day-to-day call center work', 'Calls, transfers, customer records, and follow-up actions are designed to work together.', 'arrow-path'],
                    ] as [$title, $copy, $icon])
                        <div class="grid gap-4 py-6 sm:grid-cols-[3rem_1fr]">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[var(--color-surface-2)] text-[var(--color-primary)]">
                                <x-icon :name="$icon" class="h-5 w-5" />
                            </span>
                            <div>
                                <h3 class="text-base font-semibold">{{ $title }}</h3>
                                <p class="mt-1 text-sm leading-6 text-[var(--color-on-surface-muted)]">{{ $copy }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="visibility" class="scroll-mt-24 border-b border-[var(--color-border)] bg-[var(--color-surface-1)]">
            <div class="mx-auto max-w-[1200px] px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                <div class="grid gap-12 lg:grid-cols-2 lg:items-end">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--color-primary)]">Easy-to-read information</p>
                        <h2 class="mt-3 text-3xl font-bold tracking-[-0.025em] sm:text-4xl">See what your team is doing</h2>
                    </div>
                    <p class="max-w-xl text-base leading-7 text-[var(--color-on-surface-muted)] lg:justify-self-end">Team Leaders and administrators can check call activity, reports, attendance, notifications, records, and history from the same workspace.</p>
                </div>

                <div class="mt-12 grid gap-px overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-border)] md:grid-cols-3">
                    @foreach ([
                        ['Reports', 'Review call results, campaign activity, and agent activity in a format made for the user\'s role.', 'chart-bar'],
                        ['Attendance & history', 'Check attendance and look back at past activity when the team needs to review what happened.', 'clock'],
                        ['Notifications & oversight', 'See important updates and administrative activity without leaving the CRM.', 'bell'],
                    ] as [$title, $copy, $icon])
                        <article class="bg-[var(--color-surface-card)] p-6 sm:p-7">
                            <x-icon :name="$icon" class="h-6 w-6 text-[var(--color-primary)]" />
                            <h3 class="mt-8 text-lg font-semibold">{{ $title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-[var(--color-on-surface-muted)]">{{ $copy }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="border-b border-[var(--color-border)]">
            <div class="mx-auto grid max-w-[1200px] gap-10 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-[0.85fr_1.15fr] lg:px-8">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--color-primary)]">Made for each team member</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-[-0.025em] sm:text-4xl">Each role sees the tools it needs</h2>
                    <p class="mt-4 max-w-xl text-base leading-7 text-[var(--color-on-surface-muted)]">Agents can stay focused on customers and calls, while Team Leaders and administrators get the extra tools they need to manage the operation.</p>
                </div>

                <div class="divide-y divide-[var(--color-border)] border-y border-[var(--color-border)]">
                    @foreach ([
                        ['Agent', 'Customer records, calls, forms, call results, follow-ups, and attendance.'],
                        ['Team Leader', 'Team activity, reports, attendance review, and day-to-day supervision.'],
                        ['Admin', 'Campaigns, records, reports, users, forms, and system settings.'],
                        ['Super Admin', 'Company-wide settings, VICIdial connections, branding, data retention, and administrator controls.'],
                    ] as [$role, $copy])
                        <div class="grid gap-2 py-5 sm:grid-cols-[8rem_1fr] sm:gap-6">
                            <p class="font-semibold text-[var(--color-on-surface)]">{{ $role }}</p>
                            <p class="text-sm leading-6 text-[var(--color-on-surface-muted)]">{{ $copy }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="relative overflow-hidden bg-[var(--color-surface-1)]">
            <div class="pointer-events-none absolute inset-0" style="background: radial-gradient(circle at 50% 100%, color-mix(in srgb, var(--color-primary) 10%, transparent), transparent 42%);"></div>
            <div class="relative mx-auto max-w-3xl px-4 py-16 text-center sm:px-6 sm:py-20 lg:px-8 lg:py-24">
                <h2 class="text-3xl font-bold tracking-[-0.03em] sm:text-4xl">One place for calls, customer work, and follow-ups</h2>
                <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-[var(--color-on-surface-muted)]">Sign in to handle customer records, calls, forms, follow-ups, reports, and daily call center work from one browser workspace.</p>
                <a href="{{ route('login') }}"
                   class="mt-8 inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-[var(--color-primary)] px-6 text-sm font-semibold text-[var(--color-primary-foreground)] transition hover:bg-[var(--color-primary-hover)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--color-primary)]">
                    Sign in
                    <x-icon name="chevron-right" class="h-4 w-4" />
                </a>
            </div>
        </section>
    </main>

    <footer class="border-t border-[var(--color-border)] bg-[var(--color-surface)]">
        <div class="mx-auto flex max-w-[1200px] flex-col gap-4 px-4 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <x-brand :branding="$branding" />
            <p class="text-sm text-[var(--color-on-surface-dim)]">A simpler CRM workspace for call center teams.</p>
        </div>
    </footer>
</body>
</html>
