# Controller contract reference

Source-reviewed 2026-10-15. Use the [endpoint inventory](endpoint-inventory.md) to map HTTP method/path and middleware to these controller actions. These extracts describe present code, not stable external API guarantees. No sample below records an executed request.

Common middleware errors are documented in the [API reference](api-reference.md). Fields omitted from a controller's explicit validation may still be interpreted by a service; absence of a rule is not a documented security guarantee. Provider-derived `data` depends on the service and remote response. Follow the linked source before integrating.

## Shared Form Request rules

The following exact request rule implementations preserve required/nullable/type/length/enum details, including route-specific branches. All API Form Requests shown authorize only when a request user exists.

### AttendanceStatusStartRequest

[Source](../../app/Http/Requests/Api/AttendanceStatusStartRequest.php)

```php
public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30'],
        ];
    }
```

### CallApiRequest

[Source](../../app/Http/Requests/Api/CallApiRequest.php)

```php
public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'api.call.dial' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'phone_number' => ['required', 'string', 'max:50'],
                'lead_id' => ['nullable', 'integer'],
                'phone_code' => ['nullable', 'string', 'max:5'],
            ],
            'api.call.hangup' => [
                'session_id' => ['nullable', 'integer'],
                'campaign' => ['nullable', 'string', 'max:50'],
            ],
            'api.call.predictive-dial', 'api.call.status' => [
                'campaign' => ['nullable', 'string', 'max:50'],
            ],
            default => [],
        };
    }
```

### CallHistoryRequest

[Source](../../app/Http/Requests/Api/CallHistoryRequest.php)

```php
public function rules(): array
    {
        return [
            'campaign' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'agent' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:50'],
            'lead_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:80'],
            'disposition' => ['nullable', 'string', 'max:255'],
            'vicidial_campaign' => ['nullable', 'string', 'max:255'],
            'direction' => ['nullable', 'in:INBOUND,OUTBOUND'],
            'sort' => ['nullable', 'in:called_at,agent,duration,status,vicidial_campaign'],
            'dir' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:15,25,50,100'],
        ];
    }
```

### LeadApiRequest

[Source](../../app/Http/Requests/Api/LeadApiRequest.php)

```php
public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'api.leads.search' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'phone_number' => ['required', 'string', 'max:32'],
            ],
            'api.leads.info' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'lead_id' => ['nullable', 'integer'],
                'phone_number' => ['nullable', 'string', 'max:32'],
            ],
            'api.leads.field' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'lead_id' => ['required', 'integer'],
                'field_name' => ['required', 'string', 'max:100'],
            ],
            'api.leads.hydrate' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'lead_id' => ['nullable', 'integer'],
                'phone_number' => ['nullable', 'string', 'max:32'],
            ],
            'api.leads.add' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'phone_number' => ['required', 'string', 'max:32'],
                'phone_code' => ['nullable', 'string', 'max:4'],
                'list_id' => ['nullable', 'string', 'max:12'],
            ],
            'api.leads.update' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'lead_id' => ['nullable', 'integer'],
                'vendor_lead_code' => ['nullable', 'string', 'max:50'],
                'phone_number' => ['nullable', 'string', 'max:32'],
            ],
            'api.leads.switch' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'lead_id' => ['required', 'integer'],
            ],
            'api.leads.update-fields' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'fields' => ['required', 'array'],
            ],
            default => [],
        };
    }
```

### SaveDispositionRequest

[Source](../../app/Http/Requests/Api/SaveDispositionRequest.php)

```php
public function rules(): array
    {
        return [
            'campaign_code' => ['required', 'string', 'max:50'],
            'lead_id' => ['nullable', 'integer'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'disposition_code' => ['required', 'string', 'max:50'],
            'disposition_label' => ['nullable', 'string', 'max:255'],
            'call_session_id' => ['nullable', 'integer'],
            'remarks' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'call_duration_seconds' => ['nullable', 'integer', 'min:0'],
            'lead_data_json' => ['nullable'],
        ];
    }
```

### VicidialSessionApiRequest

[Source](../../app/Http/Requests/Api/VicidialSessionApiRequest.php)

```php
public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'api.vicidial.session.login' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'phone_login' => ['nullable', 'string', 'max:32'],
                'phone_pass' => ['nullable', 'string', 'max:32'],
                'vd_login' => ['nullable', 'string', 'max:32'],
                'vd_pass' => ['nullable', 'string', 'max:32'],
                'blended' => ['nullable', 'boolean'],
                'ingroups' => ['nullable', 'array'],
                'ingroups.*' => ['string', 'max:32'],
            ],
            'api.vicidial.session.pause' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'value' => ['required', 'string', 'in:PAUSE,RESUME,pause,resume'],
            ],
            'api.vicidial.session.pause-code' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'pause_code' => ['required', 'string', 'max:6'],
            ],
            'api.vicidial.session.ingroups' => [
                'campaign' => ['nullable', 'string', 'max:50'],
                'action' => ['required', 'string', 'in:CHANGE,ADD,REMOVE,change,add,remove'],
                'ingroups' => ['nullable', 'array'],
                'ingroups.*' => ['string', 'max:32'],
                'blended' => ['nullable', 'boolean'],
            ],
            'api.vicidial.session.verify',
            'api.vicidial.session.iframe-url',
            'api.vicidial.session.logout',
            'api.vicidial.session.local-status',
            'api.vicidial.session.status' => [
                'campaign' => ['nullable', 'string', 'max:50'],
            ],
            default => [],
        };
    }
```

### WidgetLayoutUpdateRequest

[Source](../../app/Http/Requests/Api/WidgetLayoutUpdateRequest.php)

```php
protected function prepareForValidation(): void
    {
        $this->merge([
            'widget' => $this->route('widget'),
        ]);
    }

public function rules(): array
    {
        return [
            'widget' => ['required', 'string', Rule::in(self::ALLOWED_WIDGETS)],
            'layout' => ['required', 'array'],
            'layout.x' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'layout.y' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'layout.width' => ['nullable', 'numeric', 'min:200', 'max:2400'],
            'layout.height' => ['nullable', 'numeric', 'min:120', 'max:2400'],
            'layout.open' => ['nullable', 'boolean'],
            'layout.controlsHeight' => ['nullable', 'numeric', 'min:120', 'max:1200'],
            'layout.z' => ['nullable', 'integer', 'min:1', 'max:999'],
            'layout.formType' => ['nullable', 'string', 'max:100'],
            'layout.campaign' => ['nullable', 'string', 'max:100'],
            'layout.splitScreen' => ['nullable', 'boolean'],
        ];
    }
```

## API controller actions

Each action lists inline validation and exact return expressions. Shared responder helpers are included when present. For large provider operations, source links retain the full transformation and delegation context.

### ActiveLeadController.php

[Full controller](../../app/Http/Controllers/Api/ActiveLeadController.php)

#### `__invoke`

```php
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
        ]);
// ... controller branches omitted; see source ...
            return response()->json([
                'success' => false,
                'active' => false,
                'status' => null,
                'agent_state' => null,
                'message' => $result->message,
            ]);
// ... controller branches omitted; see source ...
            return response()->json([
                'success' => true,
                'active' => false,
                'status' => null,
                'agent_state' => null,
                'message' => null,
            ]);
// ... controller branches omitted; see source ...
            return response()->json([
                'success' => true,
                'active' => false,
                'status' => $statusCode,
                'agent_state' => $agentState,
                'message' => null,
            ]);
// ... controller branches omitted; see source ...
        return response()->json([
            'success' => true,
            'active' => true,
            'status' => $statusCode,
            'agent_state' => $agentState,
            'lead_id' => $hydrated['lead_id'] ?? ($leadIdText !== '' ? $leadIdText : null),
            'phone_number' => $hydrated['phone_number'] ?? ($phoneNumber !== '' ? $phoneNumber : null),
            'client_name' => $hydrated['client_name'] ?? null,
            'capture_data' => (array) ($hydrated['capture_data'] ?? []),
            'message' => null,
        ]);
```

### AgentCaptureController.php

[Full controller](../../app/Http/Controllers/Api/AgentCaptureController.php)

#### `store`

```php
        $request->validate([
            'campaign_code' => ['required', 'string', 'max:50'],
            'call_session_id' => [
                'nullable',
                'integer',
                Rule::exists('call_sessions', 'id')->where('user_id', $user->id),
            ],
            'lead_id' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'capture_data' => ['required', 'array'],
            'visible_fields' => ['nullable', 'array'],
            'visible_fields.*' => ['string', 'max:100'],
        ]);
// ... controller branches omitted; see source ...
        return response()->json([
            'success' => true,
            'id' => $record->id,
        ]);
```

### AmiWebhookController.php

[Full controller](../../app/Http/Controllers/Api/AmiWebhookController.php)

#### `__invoke`

```php
                return response()->json(['received' => false, 'error' => 'Webhook secret is not configured'], 503);
// ... controller branches omitted; see source ...
            return response()->json(['received' => false, 'error' => 'Unauthorized'], 401);
// ... controller branches omitted; see source ...
            return response()->json(['received' => true, 'processed' => false, 'reason' => 'missing event']);
// ... controller branches omitted; see source ...
        return response()->json([
            'received' => true,
            'processed' => $processed,
            'reason' => $reason,
        ]);
```

### AttendanceRealtimeController.php

[Full controller](../../app/Http/Controllers/Api/AttendanceRealtimeController.php)

#### `__invoke`

```php
public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            ...$this->attendanceRealtimeService->snapshot(),
        ]);
    }
```

### AttendanceStatusController.php

[Full controller](../../app/Http/Controllers/Api/AttendanceStatusController.php)

#### `start`

```php
public function start(AttendanceStatusStartRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $log = $this->attendanceStatusService->startStatus(
                $request->user(),
                $validated['code'],
                $request->ip(),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        $log->load('statusType');

        return response()->json([
            'success' => true,
            'log' => [
                'id' => $log->id,
                'event_type' => $log->event_type,
                'direction' => $log->direction,
                'event_time' => $log->event_time?->toIso8601String(),
                'status_label' => $log->statusType?->label,
            ],
        ]);
    }
```

#### `end`

```php
public function end(Request $request): JsonResponse
    {
        try {
            $log = $this->attendanceStatusService->endStatus($request->user(), $request->ip());
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        $log->load('statusType');

        return response()->json([
            'success' => true,
            'log' => [
                'id' => $log->id,
                'event_type' => $log->event_type,
                'direction' => $log->direction,
                'event_time' => $log->event_time?->toIso8601String(),
                'status_label' => $log->statusType?->label,
            ],
        ]);
    }
```

#### `current`

```php
public function current(Request $request): JsonResponse
    {
        $open = $this->attendanceStatusService->getOpenStatus($request->user());
        if ($open !== null) {
            $open->loadMissing('statusType');
        }

        $types = AttendanceStatusType::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'code', 'label', 'sort_order']);

        return response()->json([
            'success' => true,
            'open' => $open ? [
                'id' => $open->id,
                'code' => $open->statusType?->code,
                'label' => $open->statusType?->label,
                'started_at' => $open->event_time?->toIso8601String(),
                'attendance_status_type_id' => $open->attendance_status_type_id,
            ] : null,
            'types' => $types,
        ]);
    }
```

### CallbackController.php

[Full controller](../../app/Http/Controllers/Api/CallbackController.php)

#### `schedule`

```php
public function schedule(Request $request, CallbackService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'lead_id' => ['required', 'integer'],
            'callback_datetime' => ['required', 'string', 'max:32'],
            'callback_type' => ['nullable', 'string', 'in:ANYONE,USERONLY,anyone,useronly'],
            'callback_user' => ['nullable', 'string', 'max:20'],
            'callback_comments' => ['nullable', 'string', 'max:255'],
            'callback_status' => ['nullable', 'string', 'max:6'],
        ]);

        $result = $service->schedule(
            $request->user(),
            $this->campaign($request, $validated),
            (int) $validated['lead_id'],
            $validated['callback_datetime'],
            strtoupper((string) ($validated['callback_type'] ?? 'ANYONE')),
            $validated['callback_user'] ?? null,
            $validated['callback_comments'] ?? null,
            (string) ($validated['callback_status'] ?? 'CALLBK'),
        );

        return $this->respond($result);
    }
```

#### `remove`

```php
public function remove(Request $request, CallbackService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'lead_id' => ['required', 'integer'],
        ]);

        return $this->respond($service->remove(
            $request->user(),
            $this->campaign($request, $validated),
            (int) $validated['lead_id'],
        ));
    }
```

#### `info`

```php
public function info(Request $request, CallbackService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'lead_id' => ['required', 'integer'],
            'search_location' => ['nullable', 'string', 'in:CURRENT,ARCHIVE,ALL,current,archive,all'],
        ]);

        return $this->respond($service->info(
            $request->user(),
            $this->campaign($request, $validated),
            (int) $validated['lead_id'],
            strtoupper((string) ($validated['search_location'] ?? 'ALL')),
        ));
    }
```

#### `respond`

```php
protected function respond($result): JsonResponse
    {
        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }
```

### CallController.php

[Full controller](../../app/Http/Controllers/Api/CallController.php)

#### `dial`

```php
public function dial(CallApiRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
        $phoneNumber = (string) $validated['phone_number'];

        $result = $this->orchestration->startOutboundCall(
            $request->user(),
            $campaign,
            $phoneNumber,
            isset($validated['lead_id']) ? (int) $validated['lead_id'] : null,
            $validated['phone_code'] ?? '1',
        );

        if (! $result->success) {
            $payload = ['success' => false, 'message' => $result->message];
            if (is_array($result->data) && isset($result->data['error_code'])) {
                $payload['error'] = $result->data;
            }

            return response()->json($payload, 422);
        }

        return response()->json([
            'success' => true,
            'session_id' => $result->data['session_id'],
            'lead_id' => isset($validated['lead_id']) ? (int) $validated['lead_id'] : null,
            'phone_number' => $phoneNumber,
            'client_name' => null,
            'lead_data' => [],
        ]);
    }
```

#### `hangup`

```php
public function hangup(CallApiRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $sessionId = isset($validated['session_id']) ? (int) $validated['session_id'] : null;
        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
        $result = $this->orchestration->hangup($request->user(), $sessionId, $campaign);

        if (! $result->success) {
            return response()->json(['success' => false, 'message' => $result->message], 422);
        }

        return response()->json(['success' => true]);
    }
```

#### `status`

```php
public function status(CallApiRequest $request): JsonResponse
    {
        $user = $request->user();
        $session = $this->orchestration->getActiveSession($user);
        $pendingDisposition = $this->orchestration->getPendingDispositionSession($user);

        if ($session) {
            $duration = $session->answered_at
                ? (int) now()->diffInSeconds($session->answered_at)
                : 0;

            return response()->json([
                'success' => true,
                'active' => true,
                'disposition_pending' => false,
                'call' => [
                    'session_id' => $session->id,
                    'phone_number' => $session->phone_number,
                    'status' => $session->status,
                    'lead_id' => $session->lead_id,
                    'duration_seconds' => $duration,
                ],
            ]);
        }

        if ($pendingDisposition) {
            return response()->json([
                'success' => true,
                'active' => false,
                'disposition_pending' => true,
                'pending_call' => [
                    'session_id' => $pendingDisposition->id,
                    'phone_number' => $pendingDisposition->phone_number,
                    'status' => $pendingDisposition->status,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'active' => false,
            'disposition_pending' => false,
            'call' => null,
        ]);
    }
```

#### `predictiveDial`

```php
public function predictiveDial(CallApiRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
        $result = $this->predictiveDialer->dialNext($request->user(), $campaign);

        if (! $result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->message,
            ], 422);
        }

        $payload = (array) $result->data;
        $lead = (array) ($payload['lead'] ?? []);

        if ($lead !== []) {
            $hydrated = $this->leadHydrationService->hydrate(
                $request->user(),
                $campaign,
                isset($lead['lead_id']) && is_numeric($lead['lead_id']) ? (int) $lead['lead_id'] : null,
                isset($lead['phone_number']) ? (string) $lead['phone_number'] : null,
            );

            $payload['lead_data'] = $hydrated['capture_data'];
            $payload['client_name'] = $hydrated['client_name'] ?? ($lead['client_name'] ?? null);
            $payload['lead'] = array_merge($lead, [
                'lead_id' => $hydrated['lead_id'] ?? ($lead['lead_id'] ?? null),
                'phone_number' => $hydrated['phone_number'] ?? ($lead['phone_number'] ?? null),
                'client_name' => $hydrated['client_name'] ?? ($lead['client_name'] ?? null),
                'capture_data' => $hydrated['capture_data'],
            ]);
        }

        return response()->json(array_merge(['success' => true], $payload));
    }
```

### CallHistoryController.php

[Full controller](../../app/Http/Controllers/Api/CallHistoryController.php)

#### `index`

```php
public function index(CallHistoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $campaign = $this->campaignService->resolveCampaignForRequest(
            $request,
            $validated['campaign'] ?? null,
        )['code'];
        $page = $this->callHistoryService->getHistoricalHistory(
            $request->user(),
            $campaign,
            $validated,
            (int) ($validated['per_page'] ?? 25),
        );

        if (! $request->user()->isTeamLeader()) {
            $validated['agent'] = (string) ($request->user()->vici_user ?? '');
        }
        $data = collect($page->records->getCollection())
            ->map(fn (mixed $record): array => (new HistoricalCallResource($record))->toArray($request))
            ->values()
            ->all();

        return response()->json([
            'success' => $page->available,
            'state' => $page->state,
            'message' => $page->message,
            'data' => $data,
            'pagination' => [
                'current_page' => $page->records->currentPage(),
                'last_page' => $page->records->lastPage(),
                'per_page' => $page->records->perPage(),
                'total' => $page->records->total(),
                'from' => $page->records->firstItem(),
                'to' => $page->records->lastItem(),
                'has_more_pages' => $page->records->hasMorePages(),
            ],
            'scope' => $page->scope,
            'filters' => [
                'applied' => $validated,
                'available' => $page->filterOptions,
            ],
            'source_health' => $page->sourceHealth,
        ], $page->available ? 200 : 503);
    }
```

#### `refresh`

```php
            return response()->json([
                'success' => false,
                'state' => 'unavailable',
                'message' => 'Call History synchronization is not configured for this campaign.',
            ], 503);
// ... controller branches omitted; see source ...
                return response()->json([
                    'success' => false,
                    'state' => 'unavailable',
                    'message' => 'Call History refresh could not be queued. Please try again.',
                    'source_health' => $this->localCallHistory->syncHealth($campaign, $request->user()),
                ], 503);
// ... controller branches omitted; see source ...
        return response()->json([
            'success' => true,
            'state' => 'queued',
            'message' => $alreadyRunning ? 'A Call History refresh is already in progress.' : 'Call History refresh queued.',
            'duplicate_suppressed' => $alreadyRunning,
            'source_health' => $this->localCallHistory->syncHealth($campaign, $request->user()),
        ], 202);
```

#### `status`

```php
public function status(CallHistoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $campaign = $this->campaignService->resolveCampaignForRequest(
            $request,
            $validated['campaign'] ?? null,
        )['code'];

        return response()->json([
            'success' => true,
            'state' => 'ready',
            'source_health' => $this->localCallHistory->syncHealth($campaign, $request->user()),
        ]);
    }
```

### DispositionController.php

[Full controller](../../app/Http/Controllers/Api/DispositionController.php)

#### `__invoke`

```php
public function __invoke(Request $request, DispositionService $dispositionService): JsonResponse
    {
        $campaign = $request->query('campaign') ?: $request->session()->get('campaign', 'mbsales');
        $codes = $dispositionService->getCodesForCampaign($campaign);

        return response()->json(['success' => true, 'codes' => $codes]);
    }
```

### DtmfController.php

[Full controller](../../app/Http/Controllers/Api/DtmfController.php)

#### `__invoke`

```php
public function __invoke(Request $request, DtmfService $service): JsonResponse
    {
        $validated = $request->validate([
            'digits' => ['required', 'string', 'max:64'],
            'campaign' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $service->send(
            $request->user(),
            TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null),
            $validated['digits'],
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }
```

### GlobalSearchController.php

[Full controller](../../app/Http/Controllers/Api/GlobalSearchController.php)

#### `__invoke`

```php
            return response()->json(['groups' => []]);
// ... controller branches omitted; see source ...
        return response()->json(['groups' => $groups]);
```

### LeadController.php

[Full controller](../../app/Http/Controllers/Api/LeadController.php)

#### `search`

```php
public function search(LeadApiRequest $request, LeadService $service): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond($service->search($request->user(), $this->campaign($request, $validated), $validated['phone_number']));
    }
```

#### `info`

```php
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
```

#### `field`

```php
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
```

#### `hydrate`

```php
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
```

#### `add`

```php
public function add(LeadApiRequest $request, LeadService $service): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond($service->add($request->user(), $this->campaign($request, $validated), $request->all()));
    }
```

#### `update`

```php
public function update(LeadApiRequest $request, LeadService $service): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond($service->update($request->user(), $this->campaign($request, $validated), $request->all()));
    }
```

#### `switch`

```php
public function switch(LeadApiRequest $request, LeadService $service): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond($service->switchLead(
            $request->user(),
            $this->campaign($request, $validated),
            (int) $validated['lead_id'],
        ));
    }
```

#### `updateFields`

```php
public function updateFields(LeadApiRequest $request, LeadService $service): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond($service->updateFields(
            $request->user(),
            $this->campaign($request, $validated),
            $validated['fields'],
        ));
    }
```

#### `respond`

```php
protected function respond($result): JsonResponse
    {
        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }
```

### MarkNotificationReadController.php

[Full controller](../../app/Http/Controllers/Api/MarkNotificationReadController.php)

#### `__invoke`

```php
public function __invoke(
        Request $request,
        NotificationService $notificationService,
        NotificationReadService $readService,
    ): JsonResponse {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255'],
        ]);
        $key = $validated['key'];
        if ($notificationService->getDetailForUser($request->user(), $key) === null
            || ! $readService->markRead($request->user(), $key)) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $summary = $notificationService->getSummaryForUser($request->user());

        return response()->json([
            'success' => true,
            'key' => $key,
            'unread' => $summary['unread'],
        ]);
    }
```

### MarkNotificationsReadController.php

[Full controller](../../app/Http/Controllers/Api/MarkNotificationsReadController.php)

#### `__invoke`

```php
public function __invoke(
        Request $request,
        NotificationService $notificationService,
        NotificationReadService $readService,
    ): JsonResponse {
        $user = $request->user();
        $keys = $notificationService->visibleKeys($user);
        $readService->markMany($user, $keys);
        $historyIds = collect($keys)
            ->filter(static fn (string $key): bool => str_starts_with($key, 'history:'))
            ->map(static fn (string $key): int => (int) substr($key, strlen('history:')))
            ->filter()
            ->all();
        $notificationService->markIdsRead($user, $historyIds);

        return response()->json(['ok' => true, 'unread' => 0]);
    }
```

### NextLeadController.php

[Full controller](../../app/Http/Controllers/Api/NextLeadController.php)

#### `__invoke`

```php
public function __invoke(Request $request): JsonResponse
    {
        $campaign = $request->query('campaign') ?: (string) $request->session()->get('campaign', 'mbsales');
        $user = $request->user();

        // Throttle: prevent rapid-fire requests from the same agent
        $cacheKey = 'next_lead_throttle_'.$user->id;
        if (Cache::has($cacheKey)) {
            return response()->json([
                'success' => true,
                'lead' => null,
                'message' => 'Please wait before fetching another lead.',
            ]);
        }
        Cache::put($cacheKey, true, now()->addSeconds(2));

        $lead = $this->fetchNextLead($campaign, $user->id);

        if ($lead) {
            $hydrated = $this->leadHydrationService->hydrate(
                $user,
                $campaign,
                is_numeric($lead->lead_id) ? (int) $lead->lead_id : null,
                (string) $lead->phone_number,
            );

            return response()->json([
                'success' => true,
                'lead' => [
                    'lead_id' => $hydrated['lead_id'] ?? $lead->lead_id,
                    'phone_number' => $hydrated['phone_number'] ?? $lead->phone_number,
                    'client_name' => $hydrated['client_name'] ?? $lead->client_name,
                    'custom_data' => $lead->custom_data ?? [],
                    'capture_data' => $hydrated['capture_data'] ?? [],
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'lead' => null,
            'message' => 'No leads available in the hopper.',
        ]);
    }
```

### NotificationDetailController.php

[Full controller](../../app/Http/Controllers/Api/NotificationDetailController.php)

#### `__invoke`

```php
public function __invoke(Request $request, NotificationService $notificationService): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255'],
        ]);
        $detail = $notificationService->getDetailForUser($request->user(), $validated['key']);
        if ($detail === null) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'detail' => $detail,
        ]);
    }
```

### NotificationsController.php

[Full controller](../../app/Http/Controllers/Api/NotificationsController.php)

#### `__invoke`

```php
public function __invoke(Request $request, NotificationService $notificationService): JsonResponse
    {
        $feed = $notificationService->getFeedForUser($request->user());

        return response()->json([
            'success' => true,
            ...$feed,
        ]);
    }
```

### NotificationSummaryController.php

[Full controller](../../app/Http/Controllers/Api/NotificationSummaryController.php)

#### `__invoke`

```php
public function __invoke(Request $request, NotificationService $notificationService): JsonResponse
    {
        return response()->json([
            'success' => true,
            ...$notificationService->getSummaryForUser($request->user()),
        ]);
    }
```

### QuickFormController.php

[Full controller](../../app/Http/Controllers/Api/QuickFormController.php)

#### `bootstrap`

```php
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
        $formOptions = [];

        foreach ($forms as $formType => $formConfig) {
            if (! is_string($formType) || trim($formType) === '') {
                continue;
            }

            $formConfig = (array) $formConfig;
            $formOptions[] = [
                'type' => $formType,
                'name' => (string) ($formConfig['name'] ?? $formType),
            ];
        }

        return response()->json([
            'success' => true,
            'campaign' => $campaign,
            'campaign_name' => (string) ($campaignConfig['name'] ?? $campaign),
            'form_type' => $firstType,
            'form_name' => (string) ($firstConfig['name'] ?? $firstType),
            'form_url' => route('forms.show', ['type' => $firstType, 'campaign' => $campaign, 'widget_embed' => 1]),
            'forms' => $formOptions,
        ]);
    }
```

### RecordingController.php

[Full controller](../../app/Http/Controllers/Api/RecordingController.php)

#### `start`

```php
public function start(Request $request, RecordingService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'stage' => ['nullable', 'string', 'max:14'],
        ]);

        $result = $service->startRecording(
            $request->user(),
            $this->campaign($request, $validated),
            $validated['stage'] ?? null,
        );

        return $this->respond($result);
    }
```

#### `stop`

```php
public function stop(Request $request, RecordingService $service): JsonResponse
    {
        return $this->respond($service->stopRecording($request->user(), $this->campaign($request)));
    }
```

#### `status`

```php
public function status(Request $request, RecordingService $service): JsonResponse
    {
        return $this->respond($service->getRecordingStatus($request->user(), $this->campaign($request)));
    }
```

#### `lookup`

```php
public function lookup(Request $request, RecordingService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:50'],
            'agent_user' => ['nullable', 'string', 'max:20'],
            'lead_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'extension' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->respond($service->lookupRecordings($request->user(), $this->campaign($request, $validated), $validated));
    }
```

#### `respond`

```php
protected function respond($result): JsonResponse
    {
        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }
```

### ReportingController.php

[Full controller](../../app/Http/Controllers/Api/ReportingController.php)

#### `realtime`

```php
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
```

#### `dashboard`

```php
public function dashboard(Request $request, HistoricalTelephonyReportService $service): JsonResponse
    {
        $validated = $request->validate([
            'campaigns' => ['nullable', 'string', 'max:255'],
            'ingroups' => ['nullable', 'string', 'max:255'],
            'query_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'timezone' => ['nullable', 'timezone'],
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
```

#### `callStatusStats`

```php
public function callStatusStats(Request $request, ReportingService $service): JsonResponse
    {
        return $this->respond($service->callStatusStats($request->user(), $this->campaign($request), $request->all()));
    }
```

#### `callDispoReport`

```php
public function callDispoReport(Request $request, ReportingService $service): JsonResponse
    {
        return $this->respond($service->callDispoReport($request->user(), $this->campaign($request), $request->all()));
    }
```

#### `agentStats`

```php
public function agentStats(Request $request, ReportingService $service): JsonResponse
    {
        return $this->respond($service->agentStats($request->user(), $this->campaign($request), $request->all()));
    }
```

#### `loggedInAgents`

```php
public function loggedInAgents(Request $request, ReportingService $service): JsonResponse
    {
        return $this->respond($service->loggedInAgents($request->user(), $this->campaign($request), $request->all()));
    }
```

#### `phoneNumberLog`

```php
public function phoneNumberLog(Request $request, ReportingService $service): JsonResponse
    {
        $validated = $request->validate([
            'phone_numbers' => ['required', 'string', 'max:1000'],
        ]);

        return $this->respond($service->phoneNumberLog($request->user(), $this->campaign($request), $validated['phone_numbers']));
    }
```

#### `userGroupStatus`

```php
public function userGroupStatus(Request $request, ReportingService $service): JsonResponse
    {
        $validated = $request->validate([
            'user_groups' => ['required', 'string', 'max:255'],
        ]);

        return $this->respond($service->userGroupStatus($request->user(), $this->campaign($request), $validated['user_groups']));
    }
```

#### `inGroupStatus`

```php
public function inGroupStatus(Request $request, ReportingService $service): JsonResponse
    {
        $validated = $request->validate([
            'in_groups' => ['required', 'string', 'max:255'],
        ]);

        return $this->respond($service->inGroupStatus($request->user(), $this->campaign($request), $validated['in_groups']));
    }
```

#### `agentStatus`

```php
public function agentStatus(Request $request, ReportingService $service): JsonResponse
    {
        $validated = $request->validate([
            'agent_user' => ['required', 'string', 'max:20'],
        ]);

        return $this->respond($service->agentStatus($request->user(), $this->campaign($request), $validated['agent_user']));
    }
```

#### `respond`

```php
protected function respond($result): JsonResponse
    {
        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }
```

### SaveDispositionController.php

[Full controller](../../app/Http/Controllers/Api/SaveDispositionController.php)

#### `__invoke`

```php
public function __invoke(SaveDispositionRequest $request, DispositionService $dispositionService): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $agent = $user->full_name ?? $user->name ?? $user->username ?? '';
        $campaign = (string) $validated['campaign_code'];
        $code = (string) $validated['disposition_code'];
        $label = $validated['disposition_label'] ?? null;
        $sessionId = isset($validated['call_session_id']) ? (int) $validated['call_session_id'] : null;

        if (empty($label)) {
            $d = DispositionCode::where(function ($q) use ($campaign) {
                $q->where('campaign_code', $campaign)->orWhere('campaign_code', '');
            })->where('code', $code)->where('is_active', true)->first();
            $label = $d?->label ?? $code;
        }

        $result = $dispositionService->saveDisposition(
            $campaign,
            $agent,
            $code,
            $label,
            $user->id,
            $sessionId,
            isset($validated['lead_id']) ? (int) $validated['lead_id'] : null,
            $validated['phone_number'] ?? null,
            ($validated['remarks'] ?? null) ?: ($validated['notes'] ?? null),
            isset($validated['call_duration_seconds']) ? (int) $validated['call_duration_seconds'] : null,
            $validated['lead_data_json'] ?? null,
        );
        if (! $result->success) {
            return response()->json(['success' => false, 'message' => $result->message], 422);
        }

        return response()->json(['success' => true]);
    }
```

### SipCredentialsController.php

[Full controller](../../app/Http/Controllers/Api/SipCredentialsController.php)

#### `show`

```php
public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (empty($user->extension)) {
            return response()->json([
                'success' => false,
                'message' => 'No SIP extension assigned to this account. Contact your administrator.',
            ], 422);
        }

        if (empty($user->sip_password)) {
            return response()->json([
                'success' => false,
                'message' => 'SIP password not configured for this account. Contact your administrator.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'sip_uri' => $user->extension.'@'.config('webrtc.sip_domain'),
            'ws_url' => config('webrtc.asterisk_ws_url'),
            'extension' => $user->extension,
            'password' => $user->sip_password,
            'stun' => config('webrtc.stun_server'),
            'domain' => config('webrtc.sip_domain'),
            'ice_servers' => config('webrtc.ice_servers'),
            'no_answer_timeout' => config('webrtc.no_answer_timeout'),
        ]);
    }
```

### SupervisorAgentsController.php

[Full controller](../../app/Http/Controllers/Api/SupervisorAgentsController.php)

#### `__invoke`

```php
public function __invoke(Request $request): JsonResponse
    {
        return response()->json($this->operationalService->snapshot($request));
    }
```

### SupervisorTelephonyController.php

[Full controller](../../app/Http/Controllers/Api/SupervisorTelephonyController.php)

#### `monitor`

```php
public function monitor(Request $request): JsonResponse
    {
        return $this->agentAction($request, 'blind_monitor', ['stage' => 'MONITOR']);
    }
```

#### `whisper`

```php
public function whisper(Request $request): JsonResponse
    {
        return $this->agentAction($request, 'blind_monitor', ['stage' => 'WHISPER']);
    }
```

#### `forcePause`

```php
public function forcePause(Request $request): JsonResponse
    {
        return $this->agentAction($request, 'external_pause', ['value' => 'PAUSE']);
    }
```

#### `forceLogout`

```php
public function forceLogout(Request $request): JsonResponse
    {
        return $this->agentAction($request, 'logout', ['value' => 'LOGOUT']);
    }
```

#### `sendNotification`

```php
public function sendNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_type' => ['required', 'string', 'in:USER,USER_GROUP,CAMPAIGN'],
            'recipient' => ['required', 'string', 'max:50'],
            'campaign' => ['nullable', 'string', 'max:50'],
            'notification_text' => ['nullable', 'string', 'max:255'],
            'show_confetti' => ['nullable', 'boolean'],
        ]);

        $campaign = $this->resolveSupervisorCampaign($request, $validated['campaign'] ?? null);
        $payload = [
            'recipient_type' => $validated['recipient_type'],
            'recipient' => $validated['recipient'],
            'notification_text' => $validated['notification_text'] ?? '',
            'show_confetti' => ! empty($validated['show_confetti']) ? 'Y' : 'N',
        ];

        $result = $this->agentApi->execute($request->user(), $campaign, 'send_notification', [
            'value' => '',
            'query' => $payload,
        ]);

        $mirroredCount = 0;
        if ($result['success']) {
            $mirroredCount = $this->mirrorNotificationToLocalUsers(
                sender: $request->user(),
                recipientType: $validated['recipient_type'],
                recipient: $validated['recipient'],
                message: $payload['notification_text'],
                showConfetti: $payload['show_confetti'] === 'Y',
            );
        }

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'raw_response' => $result['raw_response'],
            'mirrored_count' => $mirroredCount,
        ], $result['success'] ? 200 : 422);
    }
```

### TelephonyHealthController.php

[Full controller](../../app/Http/Controllers/Api/TelephonyHealthController.php)

#### `__invoke`

```php
public function __invoke(): JsonResponse
    {
        $metrics = $this->health->getMetrics();
        $status = $this->health->getStatus($metrics);

        $httpStatus = match ($status) {
            'critical' => 503,
            'degraded' => 200,
            default => 200,
        };

        return response()->json([
            'status' => $status,
            'metrics' => $metrics,
            'timestamp' => now()->toIso8601String(),
        ], $httpStatus);
    }
```

### TransferController.php

[Full controller](../../app/Http/Controllers/Api/TransferController.php)

#### `blind`

```php
public function blind(Request $request, TransferService $service): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:50'],
            'campaign' => ['nullable', 'string', 'max:50'],
        ]);

        return $this->respond($service->blindTransfer($request->user(), $this->campaign($request, $validated), $validated['phone_number']));
    }
```

#### `warm`

```php
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
            (bool) ($validated['consultative'] ?? true),
        ));
    }
```

#### `local`

```php
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
            $validated['phone_number'] ?? null,
        ));
    }
```

#### `leaveThreeWay`

```php
public function leaveThreeWay(Request $request, TransferService $service): JsonResponse
    {
        return $this->respond($service->leaveThreeWay($request->user(), $this->campaign($request)));
    }
```

#### `hangupXfer`

```php
public function hangupXfer(Request $request, TransferService $service): JsonResponse
    {
        return $this->respond($service->hangupXfer($request->user(), $this->campaign($request)));
    }
```

#### `hangupBoth`

```php
public function hangupBoth(Request $request, TransferService $service): JsonResponse
    {
        return $this->respond($service->hangupBoth($request->user(), $this->campaign($request)));
    }
```

#### `vm`

```php
public function vm(Request $request, TransferService $service): JsonResponse
    {
        return $this->respond($service->leaveVoicemail($request->user(), $this->campaign($request)));
    }
```

#### `park`

```php
public function park(Request $request, TransferService $service): JsonResponse
    {
        return $this->respond($service->parkCustomer($request->user(), $this->campaign($request)));
    }
```

#### `grab`

```php
public function grab(Request $request, TransferService $service): JsonResponse
    {
        return $this->respond($service->grabCustomer($request->user(), $this->campaign($request)));
    }
```

#### `parkIvr`

```php
public function parkIvr(Request $request, TransferService $service): JsonResponse
    {
        return $this->respond($service->parkIvrCustomer($request->user(), $this->campaign($request)));
    }
```

#### `swap`

```php
public function swap(Request $request, TransferService $service): JsonResponse
    {
        $validated = $request->validate([
            'target' => ['required', 'string', 'in:CUSTOMER,XFER,customer,xfer'],
        ]);

        return $this->respond($service->swapPark(
            $request->user(),
            $this->campaign($request),
            strtoupper($validated['target']),
        ));
    }
```

#### `respond`

```php
protected function respond($result): JsonResponse
    {
        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }
```

### V1/DispositionCodesController.php

[Full controller](../../app/Http/Controllers/Api/V1/DispositionCodesController.php)

#### `__invoke`

```php
public function __invoke(Request $request, DispositionRepository $repository): JsonResponse
    {
        $campaign = $request->query('campaign', '');
        if ($campaign === '') {
            return response()->json(['message' => 'Campaign is required'], 422);
        }

        $codes = $repository->getForCampaign($campaign);

        return response()->json([
            'success' => true,
            'data' => DispositionCodeResource::collection($codes),
        ]);
    }
```

### VicidialCallUrlController.php

[Full controller](../../app/Http/Controllers/Api/VicidialCallUrlController.php)

#### `startCall`

```php
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
```

#### `dispoCall`

```php
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
```

#### `noAgentCall`

```php
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
```

#### `deadCallTrigger`

```php
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
```

#### `pauseMax`

```php
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
```

#### `respond`

```php
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
```

#### `baseRules`

```php
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
```

#### `startCallRules`

```php
private function startCallRules(): array
    {
        return array_merge($this->baseRules(), [
            'campaign' => ['required', 'string', 'max:50'],
            'lead_id' => ['required_without:phone_number', 'nullable', 'integer', 'min:1'],
            'phone_number' => ['required_without:lead_id', 'nullable', 'string', 'max:50'],
        ]);
    }
```

#### `dispoCallRules`

```php
private function dispoCallRules(): array
    {
        return array_merge($this->baseRules(), [
            'campaign' => ['required', 'string', 'max:50'],
            'dispo' => ['required', 'string', 'max:100'],
            'call_id' => ['required_without:lead_id', 'nullable', 'string', 'max:100'],
            'lead_id' => ['required_without:call_id', 'nullable', 'integer', 'min:1'],
        ]);
    }
```

#### `noAgentCallRules`

```php
private function noAgentCallRules(): array
    {
        return array_merge($this->baseRules(), [
            'campaign' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string', 'max:100'],
            'call_id' => ['required_without:lead_id', 'nullable', 'string', 'max:100'],
            'lead_id' => ['required_without:call_id', 'nullable', 'integer', 'min:1'],
        ]);
    }
```

#### `deadCallTriggerRules`

```php
private function deadCallTriggerRules(): array
    {
        return array_merge($this->baseRules(), [
            'campaign' => ['required', 'string', 'max:50'],
            'call_id' => ['required', 'string', 'max:100'],
            'dispo' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
    }
```

#### `pauseMaxRules`

```php
private function pauseMaxRules(): array
    {
        return array_merge($this->baseRules(), [
            'campaign' => ['required', 'string', 'max:50'],
            'user' => ['required', 'string', 'max:100'],
        ]);
    }
```

### VicidialEventsWebhookController.php

[Full controller](../../app/Http/Controllers/Api/VicidialEventsWebhookController.php)

#### `__invoke`

```php
                return response()->json([
                    'received' => false,
                    'processed' => false,
                    'error' => 'Webhook secret is not configured',
                ], 503);
// ... controller branches omitted; see source ...
            return response()->json(['error' => 'Unauthorized'], 401);
// ... controller branches omitted; see source ...
            return response()->json(['received' => true, 'processed' => false, 'reason' => 'missing event or user']);
// ... controller branches omitted; see source ...
        return response()->json([
            'received' => true,
            'processed' => $processed,
            'event' => $event,
        ]);
```

### VicidialProxyController.php

[Full controller](../../app/Http/Controllers/Api/VicidialProxyController.php)

#### `__invoke`

```php
            return response()->json(['success' => false, 'message' => 'Missing action']);
// ... controller branches omitted; see source ...
            return response()->json($payload);
// ... controller branches omitted; see source ...
        return response()->json($out);
```

### VicidialSessionController.php

[Full controller](../../app/Http/Controllers/Api/VicidialSessionController.php)

#### `login`

```php
public function login(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);

        $result = $service->loginAgent(
            $user,
            $campaign,
            $validated['phone_login'] ?? null,
            $validated['phone_pass'] ?? null,
            (bool) ($validated['blended'] ?? true),
            $validated['ingroups'] ?? [],
            $validated['vd_login'] ?? null,
            $validated['vd_pass'] ?? null,
        );

        if (! $result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->message,
                'data' => $result->data,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result->message,
            'data' => $result->data,
            // iframe_url is now embedded inside data by loginAgent()
            'iframe_url' => $result->data['iframe_url'] ?? null,
            'login_state' => $result->data['login_state'] ?? 'login_pending',
        ]);
    }
```

#### `iframeUrl`

```php
public function iframeUrl(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();

        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
        $user = $request->user();
        $session = $service->getLocalSession($user, $campaign);
        $creds = $service->resolveEffectivePhoneCredentials($user, $session->phone_login, null);
        $iframeUrl = $service->getAlignedIframeUrlForCampaign($user, $campaign);

        if ($iframeUrl === null || $iframeUrl === '') {
            return response()->json([
                'success' => false,
                'message' => 'Could not build VICIdial iframe URL. Check campaign server configuration, vici_user/vici_pass, and phone login.',
                'iframe_url' => null,
                'vd_login' => (string) ($user->vici_user ?? ''),
                'phone_login' => $creds['phone_login'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'iframe_url' => $iframeUrl,
            'vd_login' => (string) $user->vici_user,
            'phone_login' => $creds['phone_login'],
        ]);
    }
```

#### `verify`

```php
public function verify(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();
        $campaign = TelephonyCampaignResolver::resolve(
            $request,
            $validated['campaign'] ?? null,
        );
        $result = $service->verifyLogin($request->user(), $campaign);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'login_state' => $result->data['login_state'] ?? ($result->success ? 'ready' : 'login_pending'),
            'data' => $result->data,
        ], $result->success ? 200 : 202);
    }
```

#### `pause`

```php
public function pause(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();

        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
        $result = $service->pauseAgent($request->user(), $campaign, strtoupper($validated['value']));

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }
```

#### `pauseCode`

```php
public function pauseCode(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();

        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
        $result = $service->setPauseCode($request->user(), $campaign, $validated['pause_code']);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }
```

#### `logout`

```php
public function logout(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();
        $campaign = TelephonyCampaignResolver::resolve(
            $request,
            $validated['campaign'] ?? null,
        );
        $result = $service->logoutAgent($request->user(), $campaign);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }
```

#### `status`

```php
public function status(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();
        $campaign = TelephonyCampaignResolver::resolve(
            $request,
            $validated['campaign'] ?? null,
        );
        $status = $service->getAgentStatus($request->user(), $campaign);
        if ($status->success) {
            $syncedCampaign = $request->session()->get('vicidial_campaign');
            if (is_string($syncedCampaign) && $syncedCampaign !== '') {
                $campaign = $syncedCampaign;
            }
        }
        $queue = $service->getCallsInQueue($request->user(), $campaign);
        $ingroups = $service->getAgentInGroupInfo($request->user(), $campaign);
        $session = $service->getLocalSession($request->user(), $campaign);

        return response()->json([
            'success' => true,
            'session_iframe_agent_api_only' => (bool) config('vicidial.session_iframe_agent_api_only', false),
            'local_session' => $session,
            'agent_status' => [
                'success' => $status->success,
                'message' => $status->message,
                'data' => $status->data,
            ],
            'queue' => [
                'success' => $queue->success,
                'message' => $queue->message,
                'data' => $queue->data,
            ],
            'ingroup_info' => [
                'success' => $ingroups->success,
                'message' => $ingroups->message,
                'data' => $ingroups->data,
            ],
            'pause_codes' => config('vicidial.pause_codes', []),
        ]);
    }
```

#### `localStatus`

```php
public function localStatus(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();
        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
        $session = $service->getLocalSession($request->user(), $campaign);

        return response()->json([
            'success' => true,
            'session_iframe_agent_api_only' => (bool) config('vicidial.session_iframe_agent_api_only', false),
            'local_session' => $session,
            'agent_status' => [
                'success' => true,
                'message' => 'Local VICIdial session state.',
                'data' => [],
            ],
            'queue' => [
                'success' => true,
                'message' => 'Queue status is available on the agent screen.',
                'data' => ['count' => 0],
            ],
            'ingroup_info' => [
                'success' => true,
                'message' => 'Local session state only.',
                'data' => [],
            ],
            'pause_codes' => config('vicidial.pause_codes', []),
        ]);
    }
```

#### `ingroups`

```php
public function ingroups(VicidialSessionApiRequest $request, VicidialSessionService $service): JsonResponse
    {
        $validated = $request->validated();

        $campaign = TelephonyCampaignResolver::resolve($request, $validated['campaign'] ?? null);
        $result = $service->changeIngroups(
            $request->user(),
            $campaign,
            strtoupper($validated['action']),
            $validated['ingroups'] ?? [],
            (bool) ($validated['blended'] ?? true),
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->success ? 200 : 422);
    }
```

### WebsocketHealthController.php

[Full controller](../../app/Http/Controllers/Api/WebsocketHealthController.php)

#### `__invoke`

```php
public function __invoke(): JsonResponse
    {
        $enabled = config('broadcasting.default') === 'reverb';

        return response()->json([
            'websocket_enabled' => $enabled,
            'host' => config('broadcasting.connections.reverb.options.host'),
            'port' => config('broadcasting.connections.reverb.options.port'),
            'scheme' => config('broadcasting.connections.reverb.options.useTLS') ? 'wss' : 'ws',
        ]);
    }
```

### WidgetLayoutController.php

[Full controller](../../app/Http/Controllers/Api/WidgetLayoutController.php)

#### `index`

```php
public function index(Request $request, WidgetLayoutService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'layouts' => $service->getLayoutsForUser($request->user()),
        ]);
    }
```

#### `update`

```php
public function update(WidgetLayoutUpdateRequest $request, WidgetLayoutService $service): JsonResponse
    {
        $validated = $request->validated();
        $widget = $validated['widget'];

        $layout = $service->saveLayout(
            $request->user(),
            $widget,
            $validated['layout'],
        );

        return response()->json([
            'success' => true,
            'widget' => $widget,
            'layout' => $layout,
        ]);
    }
```

## Administrative JSON/form contracts

Admin endpoints are included in the inventory but use web form/download/redirect contracts as well as JSON. Inspect [admin controllers](../../app/Http/Controllers/Admin), [admin Form Requests](../../app/Http/Requests/Admin), and [email campaign request](../../app/Http/Requests/StoreEmailCampaignRequest.php). They are not bearer-token APIs. Email campaign behavior is documented in [EMAIL_CAMPAIGNS.md](../EMAIL_CAMPAIGNS.md). Validation normally redirects with session errors for HTML clients, or returns 422 `{message, errors}` for JSON clients; authorization can reject before validation. Export/document endpoints can return downloads rather than JSON.

## Public operations insights

[OperationsInsightsController](../../app/Http/Controllers/OperationsInsightsController.php) serves public GET data and records endpoints with throttle:60,1. Both return `Cache-Control: private, no-store`. This cache header does not add authentication. The controller transforms report/record data for public display and removes selected internal fields. Inspect those transformations before expanding public fields.

### `data` query validation

```php
$validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', 'in:historical,live,today'],
            'campaigns' => ['nullable', 'string', 'max:255'],
            'ingroups' => ['nullable', 'string', 'max:255'],
            'query_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'timezone' => ['nullable', 'timezone'],
            'disposition_scope' => ['nullable', 'in:all,exclude_system,system_only'],
            'comparison' => ['nullable', 'in:none,previous_period,previous_day,previous_week,previous_month'],
            'sales_date' => ['nullable', 'date_format:Y-m-d'],
            'sales_start' => ['nullable', 'date_format:H:i'],
            'sales_end' => ['nullable', 'date_format:H:i'],
        ]);
```

### `records` query validation

```php
$validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:100'],
            'form' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
```

Historical mode is limited to 31 days by additional controller validation. `data` combines dashboard summaries with historical or realtime report information, availability/source status, selected mode and sales range. `records` selects configured forms/tables through DataMasterService allowlists and returns forms, selected_form, columns, formatted records, and pagination; empty/invalid form handling follows controller branches. Campaign resolution uses an explicitly valid query value or the first configured campaign, and gives 404 if no campaign exists. These routes do not use session campaign middleware.
