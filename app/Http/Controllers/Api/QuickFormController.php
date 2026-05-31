<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickFormController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
    ) {}

    public function bootstrap(Request $request): JsonResponse
    {
        $campaign = (string) ($request->session()->get('campaign', 'mbsales'));
        $campaignConfig = $this->campaignService->getCampaign($campaign);
        $forms = (array) ($campaignConfig['forms'] ?? []);

        if ($campaignConfig === null || $forms === []) {
            return response()->json([
                'success' => false,
                'message' => 'No campaign forms are configured for the active campaign.',
            ], 422);
        }

        $firstType = (string) array_key_first($forms);
        $firstConfig = (array) ($forms[$firstType] ?? []);

        return response()->json([
            'success' => true,
            'campaign' => $campaign,
            'campaign_name' => (string) ($campaignConfig['name'] ?? $campaign),
            'form_type' => $firstType,
            'form_name' => (string) ($firstConfig['name'] ?? $firstType),
            'form_url' => route('forms.show', ['type' => $firstType, 'campaign' => $campaign, 'widget_embed' => 1]),
        ]);
    }
}
