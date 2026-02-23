<?php

namespace App\Http\Middleware;

use App\Services\CampaignService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCampaignSelected
{
    public function __construct(
        protected CampaignService $campaignService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('campaign')) {
            return $next($request);
        }
        $campaigns = $this->campaignService->getCampaigns();
        $first = array_key_first($campaigns);
        if ($first) {
            $request->session()->put('campaign', $first);
            $request->session()->put('campaign_name', $campaigns[$first]['name'] ?? $first);
        }
        return $next($request);
    }
}
