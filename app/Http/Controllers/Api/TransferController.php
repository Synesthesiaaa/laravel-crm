<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telephony\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function blind(Request $request, TransferService $service): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:50'],
            'campaign' => ['nullable', 'string', 'max:50'],
        ]);
        return $this->respond($service->blindTransfer($request->user(), $this->campaign($request, $validated), $validated['phone_number']));
    }

    public function warm(Request $request, TransferService $service): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => ['nullable', 'string', 'max:50'],
            'ingroup' => ['nullable', 'string', 'max:50'],
            'consultative' => ['nullable', 'boolean'],
            'campaign' => ['nullable', 'string', 'max:50'],
        ]);

        return $this->respond($service->warmTransfer(
            $request->user(),
            $this->campaign($request, $validated),
            $validated['phone_number'] ?? null,
            $validated['ingroup'] ?? null,
            (bool) ($validated['consultative'] ?? true)
        ));
    }

    public function local(Request $request, TransferService $service): JsonResponse
    {
        $validated = $request->validate([
            'ingroup' => ['required', 'string', 'max:50'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'campaign' => ['nullable', 'string', 'max:50'],
        ]);

        return $this->respond($service->localCloser(
            $request->user(),
            $this->campaign($request, $validated),
            $validated['ingroup'],
            $validated['phone_number'] ?? null
        ));
    }

    public function leaveThreeWay(Request $request, TransferService $service): JsonResponse
    {
        return $this->respond($service->leaveThreeWay($request->user(), $this->campaign($request)));
    }

    public function hangupXfer(Request $request, TransferService $service): JsonResponse
    {
        return $this->respond($service->hangupXfer($request->user(), $this->campaign($request)));
    }

    public function hangupBoth(Request $request, TransferService $service): JsonResponse
    {
        return $this->respond($service->hangupBoth($request->user(), $this->campaign($request)));
    }

    public function vm(Request $request, TransferService $service): JsonResponse
    {
        return $this->respond($service->leaveVoicemail($request->user(), $this->campaign($request)));
    }

    public function park(Request $request, TransferService $service): JsonResponse
    {
        return $this->respond($service->parkCustomer($request->user(), $this->campaign($request)));
    }

    public function grab(Request $request, TransferService $service): JsonResponse
    {
        return $this->respond($service->grabCustomer($request->user(), $this->campaign($request)));
    }

    public function parkIvr(Request $request, TransferService $service): JsonResponse
    {
        return $this->respond($service->parkIvrCustomer($request->user(), $this->campaign($request)));
    }

    public function swap(Request $request, TransferService $service): JsonResponse
    {
        $validated = $request->validate([
            'target' => ['required', 'string', 'in:CUSTOMER,XFER,customer,xfer'],
        ]);

        return $this->respond($service->swapPark(
            $request->user(),
            $this->campaign($request),
            strtoupper($validated['target'])
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
