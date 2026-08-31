<?php

namespace App\Providers;

use App\Contracts\Repositories\DispositionRepositoryInterface;
use App\Models\AgentScreenField;
use App\Models\Campaign;
use App\Models\DispositionCode;
use App\Models\Form;
use App\Models\FormField;
use App\Models\VicidialServer;
use App\Observers\ActivityObserver;
use App\Observers\CampaignConfigurationObserver;
use App\Policies\CampaignPolicy;
use App\Policies\DispositionCodePolicy;
use App\Policies\FormPolicy;
use App\Policies\UserPolicy;
use App\Policies\VicidialServerPolicy;
use App\Services\BrandingService;
use App\Services\CampaignService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->ensureTempDirectoryForPhp();
        $this->registerRepositoryBindings();
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        View::composer('*', function ($view): void {
            $view->with('branding', app(BrandingService::class)->resolve());
        });

        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(\App\Models\User::class, UserPolicy::class);
        Gate::policy(Form::class, FormPolicy::class);
        Gate::policy(DispositionCode::class, DispositionCodePolicy::class);
        Gate::policy(VicidialServer::class, VicidialServerPolicy::class);

        Campaign::observe(CampaignConfigurationObserver::class);
        Form::observe(CampaignConfigurationObserver::class);
        FormField::observe(CampaignConfigurationObserver::class);
        AgentScreenField::observe(CampaignConfigurationObserver::class);
        DispositionCode::observe(CampaignConfigurationObserver::class);
        Activity::observe(ActivityObserver::class);

        View::composer(['layouts.app', 'layouts.sidebar'], function ($view) {
            $view->with('user', Auth::user());
            $view->with('campaignConfig', app(CampaignService::class)->getCampaign((string) session('campaign', '')) ?? ['forms' => []]);
            $view->with('dispositionCodes', Auth::user()
                ? app(DispositionRepositoryInterface::class)->getForCampaign((string) session('campaign', ''))
                : collect());
        });
    }

    /**
     * Use app storage for PHP temp files to avoid tempnam() notice on PHP 8.4+
     * (e.g. "file created in the system's temporary directory" when compiling Blade).
     */
    private function ensureTempDirectoryForPhp(): void
    {
        $tempDir = storage_path('framework/temp');
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }
        if (! getenv('TMPDIR') && is_dir($tempDir)) {
            putenv('TMPDIR='.$tempDir);
        }
    }

    private function registerRepositoryBindings(): void
    {
        $bindings = [
            \App\Contracts\Repositories\CampaignRepositoryInterface::class => \App\Repositories\CampaignRepository::class,
            \App\Contracts\Repositories\UserRepositoryInterface::class => \App\Repositories\UserRepository::class,
            \App\Contracts\Repositories\FormSubmissionRepositoryInterface::class => \App\Repositories\FormSubmissionRepository::class,
            \App\Contracts\Repositories\FormFieldRepositoryInterface::class => \App\Repositories\FormFieldRepository::class,
            \App\Contracts\Repositories\DispositionRepositoryInterface::class => \App\Repositories\DispositionRepository::class,
            \App\Contracts\Repositories\VicidialServerRepositoryInterface::class => \App\Repositories\VicidialServerRepository::class,
            \App\Contracts\Repositories\AttendanceRepositoryInterface::class => \App\Repositories\AttendanceRepository::class,
        ];

        foreach ($bindings as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }
}
