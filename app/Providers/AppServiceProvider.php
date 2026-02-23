<?php

namespace App\Providers;

use App\Services\CampaignService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer(['layouts.app', 'layouts.sidebar'], function ($view) {
            $view->with('user', Auth::user());
            $view->with('campaignConfig', app(CampaignService::class)->getCampaign(session('campaign', 'mbsales')) ?? ['forms' => []]);
        });
    }
}
