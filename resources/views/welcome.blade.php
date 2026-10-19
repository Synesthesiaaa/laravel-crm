<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $brandName = trim((string) data_get($branding, 'name', config('app.name', 'CRM'))) ?: 'CRM';
        $faviconUrl = data_get($branding, 'favicon_path') ? data_get($branding, 'favicon_url', '/favicon.ico') : '/favicon.ico';
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ $brandName }} brings customer records, browser calls, campaign forms, and follow-ups into one focused call-center workspace.">
    <script>
        (function () {
            var t = 'dark';
            try { t = localStorage.getItem('theme') === 'light' ? 'light' : 'dark'; } catch (e) {}
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <title>{{ $brandName }} | Call Center CRM</title>
    <link rel="icon" href="{{ $faviconUrl }}">
    <link rel="shortcut icon" href="{{ $faviconUrl }}">
    @vite(['resources/css/app.css'])
</head>
<body class="landing-page">
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <header class="landing-header">
        <div class="landing-container landing-header-inner">
            <a href="{{ route('home') }}" class="landing-brand" aria-label="{{ $brandName }} home"><x-brand :branding="$branding" /></a>
            <nav class="landing-nav" aria-label="Primary navigation">
                <a href="#workflow">How it works</a>
                <a href="#visibility">Team access</a>
            </nav>
            <div class="landing-header-actions">
                <button type="button" id="theme-toggle" class="landing-theme" aria-label="Switch to light mode" title="Switch to light mode" aria-pressed="false">
                    <x-icon name="sun" class="theme-icon-dark h-5 w-5" />
                    <x-icon name="moon" class="theme-icon-light hidden h-5 w-5" />
                </button>
                <a href="{{ route('login') }}" class="landing-button landing-button-small">Sign in <x-icon name="chevron-right" class="h-4 w-4" /></a>
            </div>
        </div>
    </header>
    <main id="main-content" tabindex="-1">
        <section class="landing-hero" aria-labelledby="landing-title">
            <div class="landing-container">
                <p class="landing-eyebrow">Call center CRM</p>
                <div class="landing-split landing-hero-grid">
                    <h1 id="landing-title">Every call.<br>All the context.<br><span>One workspace.</span></h1>
                    <div class="landing-hero-details">
                        <p class="landing-intro">Customer records, browser calling, campaign forms, and follow-ups. Everything your team needs to handle the next conversation.</p>
                        <div class="landing-hero-actions">
                            <a href="{{ route('login') }}" class="landing-button">
                                Sign in to your workspace
                                <x-icon name="chevron-right" class="h-4 w-4" />
                            </a>
                            <a href="#workflow" class="landing-text-link">
                                See how it works
                                <x-icon name="chevron-down" class="h-4 w-4" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="workflow" class="landing-section" aria-labelledby="workflow-title">
            <div class="landing-container">
                <h2 id="workflow-title">From lead to follow-up.</h2>
                <ol class="landing-workflow" role="list">
                    @foreach ([
                        ['01', 'Open the lead', 'See customer details and the information for your assigned campaign.'],
                        ['02', 'Handle the call', 'Browser calling keeps the conversation beside the customer record.'],
                        ['03', 'Save the result', 'Complete campaign forms, add notes, and record the call outcome.'],
                        ['04', 'Set the follow-up', 'Schedule a callback with the context your next conversation needs.'],
                    ] as [$number, $title, $copy])
                        <li>
                            <span class="landing-step-number" aria-hidden="true">{{ $number }}</span>
                            <h3>{{ $title }}</h3>
                            <p>{{ $copy }}</p>
                        </li>
                    @endforeach
                </ol>
                <p id="telephony" class="landing-integration-note">
                    Calling connects with VICIdial and Asterisk. Available tools depend on your campaign and permissions.
                </p>
            </div>
        </section>

        <section id="visibility" class="landing-section landing-access" aria-labelledby="visibility-title">
            <div class="landing-container landing-split">
                <div class="landing-access-heading">
                    <h2 id="visibility-title">Access that fits the role.</h2>
                    <p class="landing-feature-copy">Reports, attendance, and activity history give supervisors an operational view. Campaign and role permissions define each person’s tools.</p>
                </div>
                <dl class="landing-roles">
                    @foreach ([
                        ['Agent', 'Work assigned leads, handle calls, and record outcomes.'],
                        ['Team Leader', 'Review team activity, attendance, and reports.'],
                        ['Admin', 'Manage operational records and reports within assigned permissions.'],
                        ['Super Admin', 'Configure campaigns, users, forms, telephony connections, and branding.'],
                    ] as [$role, $copy])
                        <div>
                            <dt>{{ $role }}</dt>
                            <dd>{{ $copy }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>
    </main>
    <footer class="landing-footer">
        <div class="landing-container landing-footer-inner"><a href="{{ route('home') }}" class="landing-brand" aria-label="{{ $brandName }} home"><x-brand :branding="$branding" /></a><a href="#main-content" class="landing-back-top">Back to top <x-icon name="chevron-down" class="h-4 w-4 rotate-180" /></a></div>
    </footer>
    <script>
        (function () {
            var html = document.documentElement;
            var btn = document.getElementById('theme-toggle');
            var dark = btn.querySelector('.theme-icon-dark');
            var light = btn.querySelector('.theme-icon-light');
            function applyTheme(t) {
                html.setAttribute('data-theme', t);
                try { localStorage.setItem('theme', t); } catch (e) {}
                dark.classList.toggle('hidden', t === 'light');
                light.classList.toggle('hidden', t !== 'light');
                var label = t === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
                btn.setAttribute('aria-label', label);
                btn.setAttribute('title', label);
                btn.setAttribute('aria-pressed', t === 'light' ? 'true' : 'false');
            }
            btn.addEventListener('click', function () {
                applyTheme(html.getAttribute('data-theme') === 'light' ? 'dark' : 'light');
            });
            applyTheme(html.getAttribute('data-theme') || 'dark');
        })();
    </script>
</body>
</html>
