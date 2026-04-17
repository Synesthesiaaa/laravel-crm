<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telephony\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function search(Request $request, LeadService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['required', 'string', 'max:32'],
        ]);

        return $this->respond($service->search($request->user(), $this->campaign($request, $validated), $validated['phone_number']));
    }

    public function info(Request $request, LeadService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'lead_id' => ['nullable', 'integer'],
            'phone_number' => ['nullable', 'string', 'max:32'],
        ]);

        return $this->respond($service->allInfo(
            $request->user(),
            $this->campaign($request, $validated),
            isset($validated['lead_id']) ? (int) $validated['lead_id'] : null,
            $validated['phone_number'] ?? null,
        ));
    }

    public function field(Request $request, LeadService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'lead_id' => ['required', 'integer'],
            'field_name' => ['required', 'string', 'max:100'],
        ]);

        return $this->respond($service->fieldInfo(
            $request->user(),
            $this->campaign($request, $validated),
            (int) $validated['lead_id'],
            $validated['field_name'],
        ));
    }

    public function add(Request $request, LeadService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['required', 'string', 'max:32'],
            'phone_code' => ['nullable', 'string', 'max:4'],
            'list_id' => ['nullable', 'string', 'max:12'],
        ]);

        return $this->respond($service->add($request->user(), $this->campaign($request, $validated), $request->all()));
    }

    public function update(Request $request, LeadService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'lead_id' => ['nullable', 'integer'],
            'vendor_lead_code' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['nullable', 'string', 'max:32'],
        ]);

        return $this->respond($service->update($request->user(), $this->campaign($request, $validated), $request->all()));
    }

    public function switch(Request $request, LeadService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'lead_id' => ['required', 'integer'],
        ]);

        return $this->respond($service->switchLead(
            $request->user(),
            $this->campaign($request, $validated),
            (int) $validated['lead_id'],
        ));
    }

    public function updateFields(Request $request, LeadService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'fields' => ['required', 'array'],
        ]);

        return $this->respond($service->updateFields(
            $request->user(),
            $this->campaign($request, $validated),
            $validated['fields'],
        ));
    }

    protected function campaign(Request $request, array $validated = []): string
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
