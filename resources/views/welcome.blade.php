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
    <meta name="description" content="{{ $brandName }} brings campaign-aware CRM work, browser calling, forms, dispositions, callbacks, and operational visibility into one workspace.">
    <script>
      (function() {
        var t = 'dark';
        try { t = localStorage.getItem('theme') || 'dark'; } catch (e) {}
        document.documentElement.setAttribute('data-theme', t);
      })();
    </script>
    <title>{{ $brandName }} | Telephony-first CRM</title>
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
                <a class="transition-colors hover:text-[var(--color-on-surface)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--color-primary)]" href="#workflow">Workflow</a>
                <a class="transition-colors hover:text-[var(--color-on-surface)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--color-primary)]" href="#telephony">Telephony</a>
                <a class="transition-colors hover:text-[var(--color-on-surface)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--color-primary)]" href="#visibility">Reporting</a>
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
                        Telephony-first CRM for call-center operations
                    </p>
                    <h1 class="max-w-3xl text-4xl font-bold leading-[1.03] tracking-[-0.035em] text-[var(--color-on-surface)] sm:text-5xl lg:text-6xl">
                        Run campaign calls and CRM work from one connected workspace
                    </h1>
                    <p class="mt-6 max-w-2xl text-base leading-7 text-[var(--color-on-surface-muted)] sm:text-lg sm:leading-8">
                        Keep campaign-aware lead handling, browser calling, forms, dispositions, callbacks, and operational reporting together around your VICIdial and Asterisk workflow.
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
                            <span>Browser calling</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-[var(--color-on-surface-muted)]">
                            <x-icon name="list-bullet" class="h-4 w-4 shrink-0 text-[var(--color-primary)]" />
                            <span>Campaign-aware workflows</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-[var(--color-on-surface-muted)]">
                            <x-icon name="signal" class="h-4 w-4 shrink-0 text-[var(--color-primary)]" />
                            <span>Live call state</span>
                        </div>
                    </div>
                </div>

                <figure class="relative mx-auto w-full max-w-[620px] lg:max-w-none" aria-labelledby="landing-preview-caption">
                    <figcaption id="landing-preview-caption" class="sr-only">
                        Illustrative agent workspace preview showing lead context, browser calling, campaign capture, and disposition follow-up.
                    </figcaption>
                    <div aria-hidden="true" class="overflow-hidden rounded-2xl border border-[var(--color-border-strong)] bg-[var(--color-surface-card)] shadow-2xl shadow-black/25">
                        <div class="flex items-center justify-between gap-4 border-b border-[var(--color-border)] bg-[var(--color-surface-1)] px-4 py-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[var(--color-primary-muted)] text-[var(--color-primary)]">
                                    <x-icon name="signal" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-[var(--color-on-surface)]">Agent workspace</p>
                                    <p class="truncate text-xs text-[var(--color-on-surface-dim)]">Campaign context stays with the call</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-[var(--color-success-muted)] px-2.5 py-1 text-[11px] font-semibold text-[var(--color-success-fg)]">
                                <span class="h-1.5 w-1.5 rounded-full bg-[var(--color-success)]"></span>
                                Ready
                            </span>
                        </div>

                        <div class="grid gap-px bg-[var(--color-border)] sm:grid-cols-3">
                            @foreach ([
                                ['Lead context', 'Customer record', 'Campaign fields stay with the active lead.', 'user'],
                                ['Browser call', 'WebRTC connected', 'SIP.js keeps call state inside the workspace.', 'phone'],
                                ['Campaign capture', 'Form & follow-up', 'Save campaign data, disposition, and callback.', 'clipboard-document-check'],
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
                            <p class="text-xs font-semibold text-[var(--color-on-surface)]">Disposition and callback stay connected to the campaign workflow.</p>
                        </div>
                    </div>
                </figure>
            </div>
        </section>

        <section id="workflow" class="scroll-mt-24 border-b border-[var(--color-border)] bg-[var(--color-surface-1)]">
            <div class="mx-auto max-w-[1200px] px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                <div class="max-w-2xl">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--color-primary)]">One continuous workflow</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-[-0.025em] sm:text-4xl">Keep the call workflow in one place</h2>
                    <p class="mt-4 text-base leading-7 text-[var(--color-on-surface-muted)]">Agents move from lead context to call handling, campaign forms, dispositions, and follow-up without bouncing between disconnected tools.</p>
                </div>

                <ol class="mt-12 grid gap-8 md:grid-cols-4">
                    @foreach ([
                        ['01', 'Lead', 'Open the active lead with campaign-specific context and fields.', 'user'],
                        ['02', 'Call', 'Use browser telephony and keep call controls next to the record.', 'phone'],
                        ['03', 'Capture', 'Complete the right campaign form while the conversation is fresh.', 'clipboard-document-list'],
                        ['04', 'Close the loop', 'Save the disposition, callback, and next action in the same flow.', 'check-circle'],
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
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--color-primary)]">Built around your telephony stack</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-[-0.025em] sm:text-4xl">CRM context and call state stay connected</h2>
                    <p class="mt-4 max-w-xl text-base leading-7 text-[var(--color-on-surface-muted)]">The browser workspace is designed around VICIdial and Asterisk operations, so telephony state and CRM work can move together instead of living in separate systems.</p>
                </div>

                <div class="divide-y divide-[var(--color-border)] border-y border-[var(--color-border)]">
                    @foreach ([
                        ['Browser telephony', 'SIP.js and WebRTC keep calling inside the workspace, with call controls available alongside the lead.', 'computer-desktop'],
                        ['VICIdial workflows', 'Agent and non-agent integrations support campaign-aware calling, predictive dialing, transfers, and operational actions.', 'server'],
                        ['Asterisk connection', 'AMI integration supports telephony events and the call-state workflows the CRM needs to coordinate.', 'signal'],
                        ['Real-time updates', 'Reverb and Echo keep active call and supervisor experiences responsive to changing operational state.', 'arrow-path'],
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
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--color-primary)]">Operational visibility</p>
                        <h2 class="mt-3 text-3xl font-bold tracking-[-0.025em] sm:text-4xl">See the campaign state that matters</h2>
                    </div>
                    <p class="max-w-xl text-base leading-7 text-[var(--color-on-surface-muted)] lg:justify-self-end">Team Leaders and administrators can review campaign-scoped activity, reports, attendance, notifications, records, and history from the same operating environment.</p>
                </div>

                <div class="mt-12 grid gap-px overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-border)] md:grid-cols-3">
                    @foreach ([
                        ['Reports', 'Review call outcomes, campaign performance, and agent activity with role-appropriate reporting.', 'chart-bar'],
                        ['Attendance & history', 'Track attendance events and retain the operational history teams need for day-to-day review.', 'clock'],
                        ['Notifications & oversight', 'Surface workflow changes and administrative activity without leaving the CRM.', 'bell'],
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
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--color-primary)]">Clear operating boundaries</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-[-0.025em] sm:text-4xl">The right workspace for each role</h2>
                    <p class="mt-4 max-w-xl text-base leading-7 text-[var(--color-on-surface-muted)]">Campaign and role boundaries are part of the CRM workflow, so agents can stay focused while leaders and administrators keep the controls they need.</p>
                </div>

                <div class="divide-y divide-[var(--color-border)] border-y border-[var(--color-border)]">
                    @foreach ([
                        ['Agent', 'Lead handling, browser calls, campaign forms, dispositions, callbacks, and attendance.'],
                        ['Team Leader', 'Supervisor visibility, operational reports, attendance review, and team oversight.'],
                        ['Admin', 'Campaign operations, records, reporting, users, forms, and supporting configuration.'],
                        ['Super Admin', 'System-wide configuration, VICIdial servers, branding, retention, and administrative controls.'],
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
                <h2 class="text-3xl font-bold tracking-[-0.03em] sm:text-4xl">Keep calls, campaigns, and CRM work in one browser workspace</h2>
                <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-[var(--color-on-surface-muted)]">Open the workspace your team already uses for campaign-aware calling, capture, follow-up, reporting, and operations.</p>
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
            <p class="text-sm text-[var(--color-on-surface-dim)]">Telephony-first CRM for campaign operations.</p>
        </div>
    </footer>
</body>
</html>
