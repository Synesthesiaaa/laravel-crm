<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/*
 * Use app storage for PHP temp directory so tempnam() does not trigger
 * "file created in the system's temporary directory" (PHP 8.4+).
 */
$storageTempDir = dirname(__DIR__).'/storage/framework/temp';
if (! is_dir($storageTempDir)) {
    @mkdir($storageTempDir, 0755, true);
}
if (! getenv('TMPDIR') && is_dir($storageTempDir)) {
    putenv('TMPDIR='.$storageTempDir);
}

/*
 * Suppress PHP 8.4+ tempnam() notice so it does not become an ErrorException
 * when Laravel compiles Blade views (e.g. Horizon dashboard, error pages).
 */
set_error_handler(function (int $severity, string $message, string $file, int $line): ?bool {
    if (($severity === \E_DEPRECATED || $severity === \E_WARNING)
        && str_contains($message, 'tempnam()')
        && str_contains($message, "system's temporary directory")) {
        return true;
    }

    return null;
}, \E_DEPRECATED | \E_WARNING);

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \App\Providers\EventServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/ami',
            'api/webhooks/vicidial-events',
        ]);
        $middleware->alias([
            'campaign' => \App\Http\Middleware\EnsureCampaignSelected::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'telephony_feature' => \App\Http\Middleware\EnsureTelephonyFeatureEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->booted(function (): void {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('vicidial', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('form-submit', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('csv-import', function (Request $request) {
            return Limit::perHour(2)->by($request->user()?->id ?: $request->ip());
        });
    })
    ->create();
