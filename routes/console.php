<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Run `php artisan schedule:run` every minute via OS task scheduler (cron):
|   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
|
| On Windows (Task Scheduler):
|   Action: php C:\xampp\htdocs\laravel-crm\artisan schedule:run
|   Trigger: every 1 minute
|
*/

// Prune expired Spatie activity log records older than 90 days
Schedule::command('activitylog:clean --days=90')
    ->daily()
    ->at('01:00')
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Prune stale queue jobs and failed jobs (keep 7 days)
Schedule::command('queue:prune-failed --hours=168')
    ->daily()
    ->at('02:00')
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Clear expired cache entries (file driver only — Redis manages its own TTL)
Schedule::command('cache:prune-stale-tags')
    ->hourly()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Invalidate dashboard stats cache to force fresh data every hour
Schedule::call(function () {
    \Illuminate\Support\Facades\Cache::forget('admin_dashboard_stats');
    \Illuminate\Support\Facades\Cache::forget('dashboard_activity_trend');
    \Illuminate\Support\Facades\Cache::forget('dashboard_top_agents');
})->hourly()->name('invalidate-dashboard-cache')->withoutOverlapping();

// Reconcile stuck call sessions: force stale active calls to failed
Schedule::job(new \App\Jobs\ReconcileCallStateJob)
    ->everyFifteenMinutes()
    ->name('reconcile-call-state')
    ->withoutOverlapping(10);

// Daily log rotation reminder (rotate logs older than configured days)
Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Predictive hopper: top up NEW / due CALLBK leads for campaigns with predictive dialing enabled
Schedule::call(function () {
    if (! config('vicidial.hopper_auto_topup_enabled', true)) {
        return;
    }
    $svc = app(\App\Services\Leads\HopperLoaderService::class);
    foreach (\App\Models\Campaign::query()->where('is_active', true)->where('predictive_enabled', true)->get() as $c) {
        $svc->loadCampaign($c->code, 500);
    }
})->everyMinute()->name('hopper-topup')->withoutOverlapping(5);

// ViciDial vicidial_list poll — reconcile lead status when inbound_poll_enabled is true
Schedule::call(function () {
    if (! config('vicidial.inbound_poll_enabled', false)) {
        return;
    }
    Bus::dispatchSync(new \App\Jobs\ReconcileVicidialLeadStatusJob);
})->everyMinute()->name('vicidial-dispo-reconcile')->withoutOverlapping(5);

