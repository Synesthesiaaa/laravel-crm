<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCampaignRequest;
use App\Http\Requests\Admin\UpdateCampaignRequest;
use App\Http\Requests\Admin\UpdateCampaignVicidialMappingRequest;
use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\VicidialServer;
use App\Services\CampaignService;
use App\Services\Telephony\CrmCampaignVicidialScopeResolver;
use App\Services\Telephony\VicidialCampaignCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CampaignsController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected CrmCampaignVicidialScopeResolver $scopeResolver,
        protected VicidialCampaignCatalogService $campaignCatalog,
    ) {}

    public function index(Request $request): View
    {
        $campaigns = Campaign::withCount('forms')->orderBy('display_order')->orderBy('id')->get();
        $servers = VicidialServer::query()
            ->active()
            ->orderBy('campaign_code')
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->groupBy('campaign_code');
        $mappingScopes = $campaigns->mapWithKeys(
            fn (Campaign $campaign): array => [$campaign->getKey() => $this->scopeResolver->resolve($campaign)->toArray()],
        );

        return view('admin.campaigns', [
            'campaigns' => $campaigns,
            'vicidialServersByCampaign' => $servers,
            'mappingScopes' => $mappingScopes,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $v = $request->validated();
        $campaign = Campaign::create([
            'code' => $v['code'],
            'name' => $v['name'],
            'description' => $v['description'] ?? '',
            'color' => $v['color'] ?? 'blue',
            'display_order' => (int) ($v['display_order'] ?? 0),
            'is_active' => true,
            'predictive_enabled' => $request->boolean('predictive_enabled', false),
            'predictive_delay_seconds' => (int) ($v['predictive_delay_seconds'] ?? 3),
            'predictive_max_attempts' => (int) ($v['predictive_max_attempts'] ?? 3),
        ]);
        $this->campaignService->clearCampaignsCache();
        $this->scopeResolver->clear($campaign);

        return redirect()->route('admin.campaigns.index')->with('success', 'Campaign created.');
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $v = $request->validated();
        $oldCode = $campaign->code;
        $campaign->update([
            'code' => $v['code'],
            'name' => $v['name'],
            'description' => $v['description'] ?? '',
            'color' => $v['color'] ?? 'blue',
            'display_order' => (int) ($v['display_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
            'predictive_enabled' => $request->boolean('predictive_enabled', false),
            'predictive_delay_seconds' => (int) ($v['predictive_delay_seconds'] ?? 3),
            'predictive_max_attempts' => (int) ($v['predictive_max_attempts'] ?? 3),
        ]);
        $this->campaignService->clearCampaignsCache();
        $this->scopeResolver->clear($oldCode);
        $this->scopeResolver->clear($campaign);

        return redirect()->route('admin.campaigns.index')->with('success', 'Campaign updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $c = Campaign::findOrFail((int) $request->input('id'));
        if ($c->forms()->exists()) {
            return redirect()->route('admin.campaigns.index')->with('error', 'Cannot delete campaign with existing forms.');
        }
        $c->update(['is_active' => false]);
        $this->campaignService->clearCampaignsCache();
        $this->scopeResolver->clear($c);

        return redirect()->route('admin.campaigns.index')->with('success', 'Campaign deactivated.');
    }

    public function vicidialCampaigns(Request $request, Campaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'server_id' => ['required', 'integer'],
        ]);
        $server = $campaign->vicidialServers()
            ->active()
            ->whereKey((int) $validated['server_id'])
            ->firstOrFail();
        $result = $this->campaignCatalog->forServer($request->user(), $server, $request->boolean('refresh'));

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'campaigns' => $result->success ? ($result->data['campaigns'] ?? []) : [],
        ], $result->success ? 200 : 422);
    }

    public function updateVicidialMapping(
        UpdateCampaignVicidialMappingRequest $request,
        Campaign $campaign,
    ): RedirectResponse {
        $validated = $request->validated();
        $server = $campaign->vicidialServers()
            ->active()
            ->whereKey((int) $validated['vicidial_server_id'])
            ->firstOrFail();
        $codes = collect($validated['vicidial_campaign_codes'])
            ->map(fn (string $code): string => trim($code))
            ->filter()
            ->unique(fn (string $code): string => strtolower($code))
            ->values();
        $catalogResult = $this->campaignCatalog->validateCodes($request->user(), $server, $codes->all());
        if (! $catalogResult->success) {
            return back()
                ->withErrors(['vicidial_campaign_codes' => $catalogResult->message])
                ->withInput();
        }

        $catalogByCode = collect((array) ($catalogResult->data['campaigns'] ?? []))
            ->keyBy(fn (array $item): string => strtolower($item['code']));

        DB::transaction(function () use ($campaign, $server, $codes, $catalogByCode): void {
            CampaignVicidialMapping::query()
                ->where('campaign_id', $campaign->getKey())
                ->delete();
            foreach ($codes as $code) {
                $catalog = $catalogByCode->get(strtolower($code), []);
                CampaignVicidialMapping::create([
                    'campaign_id' => $campaign->getKey(),
                    'vicidial_server_id' => $server->getKey(),
                    'vicidial_campaign_code' => $code,
                    'is_enabled' => true,
                    'status' => ($catalog['is_active'] ?? true)
                        ? CampaignVicidialMapping::STATUS_ACTIVE
                        : CampaignVicidialMapping::STATUS_DISABLED,
                    'last_seen_at' => now(),
                ]);
            }
        });

        $this->scopeResolver->clear($campaign);

        return redirect()->route('admin.campaigns.index')->with('success', 'VICIdial campaign mapping updated.');
    }
}
