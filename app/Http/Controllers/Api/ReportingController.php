<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telephony\HistoricalTelephonyReportService;
use App\Services\Telephony\RealtimeTelephonyReportService;
use App\Services\Telephony\ReportingService;
use App\Services\Telephony\TelephonyCampaignResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportingController extends Controller
{
    public function realtime(
        Request $request,
        RealtimeTelephonyReportService $service,
        ?string $mode = null,
    ): JsonResponse {
        $validated = $request->validate([
            'mode' => ['nullable', 'in:live,today'],
        ]);
        $mode = $mode ?? (string) ($validated['mode'] ?? 'live');
        if (! in_array($mode, ['live', 'today'], true)) {
            abort(422, 'Unsupported real-time report mode.');
        }

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => $service->dashboard($request, $mode),
        ]);
    }

    public function dashboard(Request $request, HistoricalTelephonyReportService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaigns' => ['nullable', 'string', 'max:255'],
            'ingroups' => ['nullable', 'string', 'max:255'],
            'query_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'disposition_scope' => ['nullable', 'in:all,exclude_system,system_only'],
            'comparison' => ['nullable', 'in:none,previous_period,previous_day,previous_week,previous_month'],
        ]);
        $data = $service->dashboard(
            $request->user(),
            $this->campaign($request),
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => $data,
        ]);
    }

    public function callStatusStats(Request $request, ReportingService $service): JsonResponse
    {
        return $this->respond($service->callStatusStats($request->user(), $this->campaign($request), $request->all()));
    }

    public function callDispoReport(Request $request, ReportingService $service): JsonResponse
    {
        return $this->respond($service->callDispoReport($request->user(), $this->campaign($request), $request->all()));
    }

    public function agentStats(Request $request, ReportingService $service): JsonResponse
    {
        return $this->respond($service->agentStats($request->user(), $this->campaign($request), $request->all()));
    }

    public function loggedInAgents(Request $request, ReportingService $service): JsonResponse
    {
        return $this->respond($service->loggedInAgents($request->user(), $this->campaign($request), $request->all()));
    }

    public function phoneNumberLog(Request $request, ReportingService $service): JsonResponse
    {
        $validated = $request->validate([
            'phone_numbers' => ['required', 'string', 'max:1000'],
        ]);

        return $this->respond($service->phoneNumberLog($request->user(), $this->campaign($request), $validated['phone_numbers']));
    }

    public function userGroupStatus(Request $request, ReportingService $service): JsonResponse
    {
        $validated = $request->validate([
            'user_groups' => ['required', 'string', 'max:255'],
        ]);

        return $this->respond($service->userGroupStatus($request->user(), $this->campaign($request), $validated['user_groups']));
    }

    public function inGroupStatus(Request $request, ReportingService $service): JsonResponse
    {
        $validated = $request->validate([
            'in_groups' => ['required', 'string', 'max:255'],
        ]);

        return $this->respond($service->inGroupStatus($request->user(), $this->campaign($request), $validated['in_groups']));
    }

    public function agentStatus(Request $request, ReportingService $service): JsonResponse
    {
        $validated = $request->validate([
            'agent_user' => ['required', 'string', 'max:20'],
        ]);

        return $this->respond($service->agentStatus($request->user(), $this->campaign($request), $validated['agent_user']));
    }

    protected function campaign(Request $request): string
    {
        $explicit = $request->input('campaign');

        return TelephonyCampaignResolver::resolve(
            $request,
            is_string($explicit) && $explicit !== '' ? (string) $explicit : null,
        );
    }

    protected function respond($result): JsonResponse
    {
        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }
}
