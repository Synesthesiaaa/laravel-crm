<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telephony\VicidialCallUrlService;
use App\Support\OperationResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VicidialCallUrlController extends Controller
{
    public function __construct(
        protected VicidialCallUrlService $vicidialCallUrlService,
    ) {}

    public function startCall(Request $request): JsonResponse
    {
        if ($failure = $this->rejectInvalidSignature($request)) {
            return $failure;
        }

        $validation = $this->validateCallback($request, $this->startCallRules());
        if ($validation instanceof JsonResponse) {
            return $validation;
        }

        return $this->respond($this->vicidialCallUrlService->startCall($request->query()));
    }

    public function dispoCall(Request $request): JsonResponse
    {
        if ($failure = $this->rejectInvalidSignature($request)) {
            return $failure;
        }

        $validation = $this->validateCallback($request, $this->dispoCallRules());
        if ($validation instanceof JsonResponse) {
            return $validation;
        }

        return $this->respond($this->vicidialCallUrlService->dispoCall($request->query()));
    }

    public function noAgentCall(Request $request): JsonResponse
    {
        if ($failure = $this->rejectInvalidSignature($request)) {
            return $failure;
        }

        $validation = $this->validateCallback($request, $this->noAgentCallRules());
        if ($validation instanceof JsonResponse) {
            return $validation;
        }

        return $this->respond($this->vicidialCallUrlService->noAgentCall($request->query()));
    }

    public function deadCallTrigger(Request $request): JsonResponse
    {
        if ($failure = $this->rejectInvalidSignature($request)) {
            return $failure;
        }

        $validation = $this->validateCallback($request, $this->deadCallTriggerRules());
        if ($validation instanceof JsonResponse) {
            return $validation;
        }

        $payload = $request->query();
        $payload['dispo'] = $payload['dispo'] ?? 'DEAD';

        return $this->respond($this->vicidialCallUrlService->deadCallTrigger($payload));
    }

    public function pauseMax(Request $request): JsonResponse
    {
        if ($failure = $this->rejectInvalidSignature($request)) {
            return $failure;
        }

        $validation = $this->validateCallback($request, $this->pauseMaxRules());
        if ($validation instanceof JsonResponse) {
            return $validation;
        }

        return $this->respond($this->vicidialCallUrlService->pauseMax($request->query()));
    }

    private function rejectInvalidSignature(Request $request): ?JsonResponse
    {
        $secret = (string) config('vicidial.call_url_secret', '');
        $signature = $request->query('sig', '');
        $signature = is_string($signature) ? trim($signature) : '';

        if ($secret === '' || ! hash_equals($secret, $signature)) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_signature',
            ], 401);
        }

        return null;
    }

    /**
     * @param  array<string, array<int, string>>  $rules
     */
    private function validateCallback(Request $request, array $rules): ?JsonResponse
    {
        $validator = Validator::make($request->query(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'validation_failed',
                'fields' => $validator->errors()->toArray(),
            ], 422);
        }

        return null;
    }

    private function respond(OperationResult $result): JsonResponse
    {
        $payload = [
            'ok' => $result->success,
        ];

        if ($result->success) {
            if ($result->data !== null) {
                $payload['data'] = $result->data;
            }
        } else {
            $payload['error'] = 'processing_failed';

            if ($result->message !== null) {
                $payload['message'] = $result->message;
            }

            if ($result->data !== null) {
                $payload['data'] = $result->data;
            }
        }

        return response()->json($payload);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function baseRules(): array
    {
        $customFieldRules = [];
        $suffixes = ['one', 'two', 'three', 'four', 'five'];
        foreach (['user_custom', 'camp_custom', 'ig_custom'] as $prefix) {
            foreach ($suffixes as $suffix) {
                $customFieldRules["{$prefix}_{$suffix}"] = ['sometimes', 'nullable', 'string', 'max:255'];
            }
        }

        return array_merge([
            'call_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'campaign' => ['sometimes', 'nullable', 'string', 'max:50'],
            'lead_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'uniqueid' => ['sometimes', 'nullable', 'string', 'max:100'],
            'user' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'list_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'vendor_lead_code' => ['sometimes', 'nullable', 'string', 'max:100'],
            'source_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'entry_list_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'session_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone_login' => ['sometimes', 'nullable', 'string', 'max:100'],
            'group' => ['sometimes', 'nullable', 'string', 'max:100'],
            'channel_group' => ['sometimes', 'nullable', 'string', 'max:100'],
            'dialed_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'dialed_label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'fullname' => ['sometimes', 'nullable', 'string', 'max:255'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'agent_email' => ['sometimes', 'nullable', 'string', 'max:255'],
            'called_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'talk_time' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'dispo' => ['sometimes', 'nullable', 'string', 'max:100'],
            'call_notes' => ['sometimes', 'nullable', 'string'],
            'callback_lead_status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'callback_datetime' => ['sometimes', 'nullable', 'string', 'max:100'],
            'term_reason' => ['sometimes', 'nullable', 'string', 'max:100'],
            'user_group' => ['sometimes', 'nullable', 'string', 'max:100'],
        ], $customFieldRules);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function startCallRules(): array
    {
        return array_merge($this->baseRules(), [
            'campaign' => ['required', 'string', 'max:50'],
            'lead_id' => ['required_without:phone_number', 'nullable', 'integer', 'min:1'],
            'phone_number' => ['required_without:lead_id', 'nullable', 'string', 'max:50'],
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function dispoCallRules(): array
    {
        return array_merge($this->baseRules(), [
            'campaign' => ['required', 'string', 'max:50'],
            'dispo' => ['required', 'string', 'max:100'],
            'call_id' => ['required_without:lead_id', 'nullable', 'string', 'max:100'],
            'lead_id' => ['required_without:call_id', 'nullable', 'integer', 'min:1'],
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function noAgentCallRules(): array
    {
        return array_merge($this->baseRules(), [
            'campaign' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string', 'max:100'],
            'call_id' => ['required_without:lead_id', 'nullable', 'string', 'max:100'],
            'lead_id' => ['required_without:call_id', 'nullable', 'integer', 'min:1'],
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function deadCallTriggerRules(): array
    {
        return array_merge($this->baseRules(), [
            'campaign' => ['required', 'string', 'max:50'],
            'call_id' => ['required', 'string', 'max:100'],
            'dispo' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function pauseMaxRules(): array
    {
        return array_merge($this->baseRules(), [
            'campaign' => ['required', 'string', 'max:50'],
            'user' => ['required', 'string', 'max:100'],
        ]);
    }
}
