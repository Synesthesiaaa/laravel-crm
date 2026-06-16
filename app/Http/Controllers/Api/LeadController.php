<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LeadApiRequest;
use App\Services\Telephony\LeadHydrationService;
use App\Services\Telephony\LeadService;
use Illuminate\Http\JsonResponse;

class LeadController extends Controller
{
    public function search(LeadApiRequest $request, LeadService $service): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond($service->search($request->user(), $this->campaign($request, $validated), $validated['phone_number']));
    }

    public function info(LeadApiRequest $request, LeadService $service): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond($service->allInfo(
            $request->user(),
            $this->campaign($request, $validated),
            isset($validated['lead_id']) ? (int) $validated['lead_id'] : null,
            $validated['phone_number'] ?? null,
        ));
    }

    public function field(LeadApiRequest $request, LeadService $service): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond($service->fieldInfo(
            $request->user(),
            $this->campaign($request, $validated),
            (int) $validated['lead_id'],
            $validated['field_name'],
        ));
    }

    public function hydrate(LeadApiRequest $request, LeadHydrationService $hydrationService): JsonResponse
    {
        $validated = $request->validated();

        if (! isset($validated['lead_id']) && empty($validated['phone_number'])) {
            return response()->json([
                'success' => false,
                'message' => 'lead_id or phone_number is required.',
                'data' => null,
            ], 422);
        }

        $data = $hydrationService->hydrate(
            $request->user(),
            $this->campaign($request, $validated),
            isset($validated['lead_id']) ? (int) $validated['lead_id'] : null,
            $validated['phone_number'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => $data,
        ]);
    }

    public function add(LeadApiRequest $request, LeadService $service): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond($service->add($request->user(), $this->campaign($request, $validated), $request->all()));
    }

    public function update(LeadApiRequest $request, LeadService $service): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond($service->update($request->user(), $this->campaign($request, $validated), $request->all()));
    }

    public function switch(LeadApiRequest $request, LeadService $service): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond($service->switchLead(
            $request->user(),
            $this->campaign($request, $validated),
            (int) $validated['lead_id'],
        ));
    }

    public function updateFields(LeadApiRequest $request, LeadService $service): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond($service->updateFields(
            $request->user(),
            $this->campaign($request, $validated),
            $validated['fields'],
        ));
    }

    protected function campaign(LeadApiRequest $request, array $validated = []): string
    {
        return (string) ($validated['campaign'] ?? $request->input('campaign', $request->session()->get('campaign', 'mbsales')));
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
