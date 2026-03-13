<?php

namespace App\Providers;

use App\Events\CallOriginated;
use App\Events\CallStateChanged;
use App\Events\DispositionSaved;
use App\Events\FormSubmitted;
use App\Events\LeadImported;
use App\Events\UserLoggedIn;
use App\Events\UserLoggedOut;
use App\Listeners\InvalidateDashboardCache;
use App\Listeners\LogCallOriginated;
use App\Listeners\LogCallStateChanged;
use App\Listeners\LogDispositionSaved;
use App\Listeners\LogFormSubmission;
use App\Listeners\LogSecurityEvent;
use App\Listeners\NotifyLeadImportComplete;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        FormSubmitted::class => [
            LogFormSubmission::class,
            InvalidateDashboardCache::class,
        ],
        DispositionSaved::class => [
            LogDispositionSaved::class,
        ],
        UserLoggedIn::class => [
            LogSecurityEvent::class,
        ],
        UserLoggedOut::class => [
            LogSecurityEvent::class,
        ],
        LeadImported::class => [
            NotifyLeadImportComplete::class,
        ],
        CallOriginated::class => [
            LogCallOriginated::class,
        ],
        CallStateChanged::class => [
            LogCallStateChanged::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
