<?php

namespace App\Http\Middleware;

use App\Services\CampaignService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCampaignSelected
{
    public function __construct(
        protected CampaignService $campaignService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->campaignService->resolveCampaignForRequest($request);

        return $next($request);
    }
}
