<!DOCTYPE html>
<html lang="en">
<head>
    @php
        $guestBrandName = data_get($branding, 'name', config('app.name', 'CRM'));
        $guestFaviconUrl = data_get($branding, 'favicon_path')
            ? data_get($branding, 'favicon_url', '/favicon.ico')
            : '/favicon.ico';
        $campaignCount = is_array($campaigns) ? count($campaigns) : 0;
        $singleCampaignCode = $campaignCount === 1 ? array_key_first($campaigns) : null;
        $singleCampaignConfig = $singleCampaignCode !== null ? ($campaigns[$singleCampaignCode] ?? []) : [];
        $singleCampaignName = $singleCampaignConfig['name'] ?? $singleCampaignCode;
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
      (function() {
        var t = 'dark';
        try { t = localStorage.getItem('theme') || 'dark'; } catch (e) {}
        document.documentElement.setAttribute('data-theme', t);
      })();
    </script>
    <title>Login | {{ $guestBrandName }}</title>
    <link rel="icon" href="{{ $guestFaviconUrl }}">
    <link rel="shortcut icon" href="{{ $guestFaviconUrl }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body>
    <div class="login-root">
        <button type="button" id="theme-toggle" class="theme-toggle login-theme-toggle" aria-label="Switch to light mode" title="Switch to light mode" aria-pressed="false">
            <svg class="theme-icon-dark" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <svg class="theme-icon-light hidden" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        </button>

        <main class="login-content" aria-labelledby="login-title">
            <div class="login-layout" data-login-layout>
                <div class="login-context" data-login-context>
                    <x-brand :branding="$branding" variant="login" class="login-context-brand" />
                </div>

                <section class="login-glass-card" aria-labelledby="login-title" data-login-form>
                    <div class="login-form-heading">
                        <h1 id="login-title">Sign in to your CRM account</h1>
                        <p id="login-description" class="login-sub">Use your CRM username and password.</p>
                    </div>

                    @if (session('status'))
                        <div id="login-status" class="login-alert login-alert--success" role="status">
                            <x-icon name="check-circle" class="login-alert-icon" />
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif
                    @if ($errors->any())
                        <div id="login-error" class="login-alert login-alert--error" role="alert">
                            <x-icon name="exclamation-circle" class="login-alert-icon" />
                            <div class="login-alert-content">
                                <strong class="login-alert-title">Sign-in couldn't be completed</strong>
                                <span class="login-alert-copy">Check the highlighted fields and try again. <a href="#login-help">Need access help?</a></span>
                            </div>
                        </div>
                    @endif

                    <form id="login-form" method="POST" action="{{ route('login') }}" aria-describedby="login-description" aria-busy="false">
                        @csrf

                        <div class="login-field login-field-float @if ($errors->has('username')) login-field--invalid @endif">
                            <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" placeholder=" " @if ($errors->has('username')) aria-invalid="true" aria-describedby="username-error" @endif>
                            <span class="login-field-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </span>
                            <label for="username">Username</label>
                            @if ($errors->has('username'))
                                <p id="username-error" class="login-field-error">{{ $errors->first('username') }}</p>
                            @endif
                        </div>

                        <div class="login-field login-field-float @if ($errors->has('password')) login-field--invalid @endif">
                            <input id="password" class="login-password-input" type="password" name="password" required autocomplete="current-password" placeholder=" " @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
                            <span class="login-field-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                            <button type="button" id="password-toggle" class="login-password-toggle" aria-controls="password" aria-label="Show password" title="Show password" aria-pressed="false">
                                <span data-login-password-label>Show</span>
                            </button>
                            <label for="password">Password</label>
                            @if ($errors->has('password'))
                                <p id="password-error" class="login-field-error">{{ $errors->first('password') }}</p>
                            @endif
                        </div>

                        @if ($campaignCount === 1)
                            <fieldset class="login-workspace-field login-workspace-field--readonly @if ($errors->has('campaign')) login-field--invalid @endif" data-login-campaign data-login-campaign-readonly>
                                <legend class="login-fieldset-label">Starting campaign</legend>
                                <div class="login-readonly-context">
                                    <span class="login-field-icon login-field-icon--readonly" aria-hidden="true">
                                        <x-icon name="list-bullet" class="login-field-icon-svg" />
                                    </span>
                                    <div>
                                        <span class="login-readonly-label">Campaign</span>
                                        <strong>{{ $singleCampaignName }}</strong>
                                    </div>
                                </div>
                                <input type="hidden" name="campaign" value="{{ $singleCampaignCode }}">
                                <p id="campaign-help" class="login-field-help">You will start in this campaign after signing in.</p>
                                @if ($errors->has('campaign'))
                                    <p id="campaign-error" class="login-field-error">{{ $errors->first('campaign') }}</p>
                                @endif
                            </fieldset>
                        @elseif ($campaignCount > 1)
                            <fieldset class="login-workspace-field" data-login-campaign>
                                <legend class="login-fieldset-label">Starting campaign</legend>
                                <div class="login-field login-field--select @if ($errors->has('campaign')) login-field--invalid @endif">
                                    <div class="login-select-wrap">
                                        <span class="login-field-icon login-field-icon--select" aria-hidden="true">
                                            <x-icon name="list-bullet" class="login-field-icon-svg" />
                                        </span>
                                        <select id="campaign" name="campaign" class="login-select" aria-label="Starting campaign" @if ($errors->has('campaign')) aria-invalid="true" aria-describedby="campaign-help campaign-error" @else aria-describedby="campaign-help" @endif>
                                            @foreach ($campaigns as $code => $config)
                                                <option value="{{ $code }}" {{ old('campaign', array_key_first($campaigns)) === $code ? 'selected' : '' }}>
                                                    {{ $config['name'] ?? $code }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <p id="campaign-help" class="login-field-help">You will start in this campaign after signing in.</p>
                                    @if ($errors->has('campaign'))
                                        <p id="campaign-error" class="login-field-error">{{ $errors->first('campaign') }}</p>
                                    @endif
                                </div>
                            </fieldset>
                        @endif

                        <div id="login-progress" class="login-progress" role="status" aria-live="polite" aria-atomic="true" hidden>
                            <span class="login-progress-indicator" aria-hidden="true"></span>
                            <span data-login-progress-message>Signing in. Please wait.</span>
                        </div>

                        <div class="login-field login-field--submit">
                            <button type="submit" class="login-btn" data-login-submit aria-describedby="login-progress" aria-busy="false">
                                <span data-login-submit-label>Sign in</span>
                            </button>
                        </div>
                    </form>

                    <div id="login-help" class="login-help" tabindex="-1">
                        <strong>Need access help?</strong>
                        <span>Contact your supervisor or help desk if your account is locked or you still can't sign in.</span>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <script>
      (function() {
        var html = document.documentElement;
        var btn = document.getElementById('theme-toggle');
        var iconDark = btn && btn.querySelector('.theme-icon-dark');
        var iconLight = btn && btn.querySelector('.theme-icon-light');
        var form = document.getElementById('login-form');
        var submit = form && form.querySelector('[data-login-submit]');
        var submitLabel = submit && submit.querySelector('[data-login-submit-label]');
        var progress = document.getElementById('login-progress');
        var progressMessage = progress && progress.querySelector('[data-login-progress-message]');
        var password = document.getElementById('password');
        var passwordToggle = document.getElementById('password-toggle');
        var passwordToggleLabel = passwordToggle && passwordToggle.querySelector('[data-login-password-label]');
        function applyTheme(theme) {
          html.setAttribute('data-theme', theme);
          try { localStorage.setItem('theme', theme); } catch (e) {}
          if (iconDark) iconDark.classList.toggle('hidden', theme === 'light');
          if (iconLight) iconLight.classList.toggle('hidden', theme !== 'light');
          if (btn) {
            var nextThemeLabel = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
            btn.setAttribute('title', nextThemeLabel);
            btn.setAttribute('aria-label', nextThemeLabel);
            btn.setAttribute('aria-pressed', theme === 'light' ? 'true' : 'false');
          }
        }
        if (btn) btn.addEventListener('click', function() {
          btn.classList.remove('login-theme-toggle--spin');
          void btn.offsetWidth;
          btn.classList.add('login-theme-toggle--spin');
          var next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
          applyTheme(next);
          setTimeout(function() { btn.classList.remove('login-theme-toggle--spin'); }, 560);
        });
        if (passwordToggle && password && passwordToggleLabel) {
            passwordToggle.addEventListener('click', function() {
              var isVisible = password.type === 'text';
              password.type = isVisible ? 'password' : 'text';
              var nextPasswordLabel = isVisible ? 'Show password' : 'Hide password';
              passwordToggleLabel.textContent = isVisible ? 'Show' : 'Hide';
              passwordToggle.setAttribute('aria-label', nextPasswordLabel);
              passwordToggle.setAttribute('title', nextPasswordLabel);
              passwordToggle.setAttribute('aria-pressed', isVisible ? 'false' : 'true');
            });
        }
        if (form && submit) {
          function resetLoginSubmissionState() {
            form.setAttribute('aria-busy', 'false');
            submit.disabled = false;
            submit.setAttribute('aria-busy', 'false');
            submit.classList.remove('login-btn--loading');
            if (submitLabel) submitLabel.textContent = 'Sign in';
            if (progress) progress.hidden = true;
          }
          form.addEventListener('submit', function(event) {
            if (form.getAttribute('aria-busy') === 'true') {
              event.preventDefault();
              return;
            }
            form.setAttribute('aria-busy', 'true');
            submit.disabled = true;
            submit.setAttribute('aria-busy', 'true');
            submit.classList.add('login-btn--loading');
            if (submitLabel) submitLabel.textContent = 'Signing in…';
            if (progress) progress.hidden = false;
            if (progressMessage) progressMessage.textContent = 'Signing in. Please wait.';
          });
          window.addEventListener('pageshow', function(event) {
            if (event.persisted) resetLoginSubmissionState();
          });
        }
        applyTheme(html.getAttribute('data-theme') || 'dark');
      })();
    </script>
</body>
</html>
