<?php

namespace App\Services\Telephony;

use App\Events\InboundCallReceived;
use App\Models\CallSession;
use App\Models\CampaignDispositionRecord;
use App\Models\DispositionCode;
use App\Models\User;
use App\Services\DispositionService as CallDispositionService;
use App\Support\OperationResult;
use Illuminate\Support\Arr;

class VicidialCallUrlService
{
    public function __construct(
        protected LeadHydrationService $leadHydrationService,
        protected CallDispositionService $dispositionService,
        protected CallStateService $callStateService,
        protected CallUuidMappingService $callUuidMappingService,
        protected TelephonyAlertService $alertService,
        protected TelephonyLogger $telephonyLogger,
    ) {}

    /**
     * Screen-pop the agent and populate or create the local call context.
     *
     * @param  array<string, mixed>  $payload
     */
    public function startCall(array $payload): OperationResult
    {
        $campaign = $this->stringValue($payload, 'campaign');
        $leadId = $this->intValue($payload, 'lead_id');
        $phoneNumber = $this->stringValue($payload, 'phone_number') ?? $this->stringValue($payload, 'phone');
        $callId = $this->stringValue($payload, 'call_id');
        $agentUser = $this->resolveAgentUser($payload);
        $hydrationUser = $agentUser ?? new User;

        $hydrated = $this->leadHydrationService->hydrate(
            $hydrationUser,
            $campaign,
            $leadId,
            $phoneNumber,
        );

        $hydratedLeadId = $this->intValue($hydrated, 'lead_id');
        $hydratedPhoneNumber = $this->stringValue($hydrated, 'phone_number') ?? $phoneNumber;
        $clientName = $this->stringValue($hydrated, 'client_name');
        $captureData = (array) ($hydrated['capture_data'] ?? []);
        $effectiveLeadId = $leadId ?? $hydratedLeadId;

        $session = $this->findStartSession($campaign, $agentUser, $effectiveLeadId, $hydratedPhoneNumber, $callId);
        $createdSession = false;

        if ($session === null && $agentUser && $hydratedPhoneNumber !== null) {
            $session = $this->createStartSession(
                $agentUser,
                $campaign,
                $effectiveLeadId,
                $hydratedPhoneNumber,
                $callId,
                $payload,
                $captureData,
            );
            $createdSession = true;
        } elseif ($session !== null) {
            $this->syncSessionContext(
                $session,
                $payload,
                'start_call',
                $effectiveLeadId,
                $hydratedPhoneNumber,
                $callId,
                $captureData,
                false,
            );
        }

        $broadcastUserId = $session?->user_id ?? $agentUser?->id;
        if ($broadcastUserId) {
            event(new InboundCallReceived(
                userId: (int) $broadcastUserId,
                phoneNumber: (string) ($hydratedPhoneNumber ?? $phoneNumber ?? ''),
                leadId: $effectiveLeadId,
                clientName: $clientName,
                campaignCode: $campaign,
                leadData: $captureData,
            ));
        }

        return OperationResult::success([
            'call_session_id' => $session?->id,
            'created_session' => $createdSession,
            'matched_session_id' => $session?->id,
            'call_id' => $callId,
            'lead_id' => $effectiveLeadId,
            'phone_number' => $hydratedPhoneNumber ?? $phoneNumber,
            'client_name' => $clientName,
            'capture_data' => $captureData,
            'session_status' => $session?->status,
        ]);
    }

    /**
     * Save a disposition through the existing disposition pipeline.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispoCall(array $payload): OperationResult
    {
        $campaign = $this->stringValue($payload, 'campaign');
        $callId = $this->stringValue($payload, 'call_id');
        $leadId = $this->intValue($payload, 'lead_id');
        $phoneNumber = $this->stringValue($payload, 'phone_number');
        $dispositionCode = $this->stringValue($payload, 'dispo');
        $session = $this->findDispositionSession($campaign, $leadId, $phoneNumber, $callId);

        if ($session === null) {
            return OperationResult::failure('Unable to resolve a call session for disposition.');
        }

        if ($callId !== null && ($session->vicidial_call_id === null || $session->vicidial_call_id === '')) {
            $session->vicidial_call_id = $callId;
        }

        $metadata = $this->callbackMetadata($payload, 'dispo_call', [
            'dispo_call_seen_at' => now()->toIso8601String(),
        ]);
        $session->metadata = array_replace_recursive($session->metadata ?? [], $metadata);
        $session->save();

        if ($session->disposition_code !== null
            || CampaignDispositionRecord::where('call_session_id', $session->id)->exists()) {
            return OperationResult::success([
                'call_session_id' => $session->id,
                'call_id' => $callId,
                'already_processed' => true,
                'disposition_code' => $session->disposition_code,
                'disposition_label' => $session->disposition_label,
            ]);
        }

        $dispositionLabel = $this->resolveDispositionLabel($campaign, $dispositionCode);
        $remarks = $this->stringValue($payload, 'call_notes');
        $talkTime = $this->intValue($payload, 'talk_time');
        $agentLabel = $this->resolveAgentLabel($payload, $session->user);

        $result = $this->dispositionService->saveDisposition(
            $campaign,
            $agentLabel,
            $dispositionCode,
            $dispositionLabel,
            $session->user_id,
            $session->id,
            $session->lead_id ? (int) $session->lead_id : $leadId,
            $session->phone_number ?: $phoneNumber,
            $remarks,
            $talkTime ?? $session->call_duration_seconds,
            $this->callbackLeadDataJson($payload),
        );

        if (! $result->success) {
            return OperationResult::failure($result->message ?? 'Unable to save disposition.');
        }

        return OperationResult::success([
            'call_session_id' => $session->id,
            'call_id' => $callId,
            'disposition_code' => $dispositionCode,
            'disposition_label' => $dispositionLabel,
            'lead_id' => $session->lead_id ? (int) $session->lead_id : $leadId,
            'phone_number' => $session->phone_number ?: $phoneNumber,
        ]);
    }

    /**
     * Log an abandoned/no-agent event without changing call state.
     *
     * @param  array<string, mixed>  $payload
     */
    public function noAgentCall(array $payload): OperationResult
    {
        $alert = $this->alertService->log(
            'vicidial_no_agent_call',
            'Vicidial no-agent callback received.',
            $this->callbackContext($payload, 'no_agent_call'),
            \App\Models\TelephonyAlert::SEVERITY_WARNING,
        );

        return OperationResult::success([
            'alert_id' => $alert->id,
        ]);
    }

    /**
     * Normalize dead-call callbacks into the existing hangup path.
     *
     * @param  array<string, mixed>  $payload
     */
    public function deadCallTrigger(array $payload): OperationResult
    {
        $campaign = $this->stringValue($payload, 'campaign');
        $callId = $this->stringValue($payload, 'call_id');
        $leadId = $this->intValue($payload, 'lead_id');
        $phoneNumber = $this->stringValue($payload, 'phone_number');
        $session = $this->findAnySession($campaign, $leadId, $phoneNumber, $callId);

        if ($session === null) {
            $alert = $this->alertService->log(
                'vicidial_dead_call_trigger_unmatched',
                'Vicidial dead-call callback could not be matched to a call session.',
                $this->callbackContext($payload, 'dead_call_trigger'),
                \App\Models\TelephonyAlert::SEVERITY_WARNING,
            );

            return OperationResult::success([
                'alert_id' => $alert->id,
                'call_session_id' => null,
                'matched_session_id' => null,
                'unmatched' => true,
            ]);
        }

        if ($callId !== null && ($session->vicidial_call_id === null || $session->vicidial_call_id === '')) {
            $session->vicidial_call_id = $callId;
        }

        $this->syncSessionContext(
            $session,
            $payload,
            'dead_call_trigger',
            $leadId,
            $phoneNumber,
            $callId,
            [],
            false,
        );

        $result = $this->callStateService->recordHangup($session, [
            'end_reason' => 'customer_hangup',
            'metadata' => [
                'vicidial' => [
                    'dead_call_trigger_seen_at' => now()->toIso8601String(),
                    'payload' => Arr::except($payload, ['sig']),
                ],
            ],
        ]);

        if (! $result->success) {
            return OperationResult::failure($result->message ?? 'Unable to apply dead-call callback.');
        }

        return OperationResult::success([
            'call_session_id' => $session->id,
            'matched_session_id' => $session->id,
            'call_id' => $callId,
            'session_status' => $session->fresh()->status,
        ]);
    }

    /**
     * Record a pause-max alert without changing Vicidial or call state.
     *
     * @param  array<string, mixed>  $payload
     */
    public function pauseMax(array $payload): OperationResult
    {
        $alert = $this->alertService->log(
            'vicidial_pause_max',
            'Vicidial pause-max callback received.',
            $this->callbackContext($payload, 'pause_max'),
            \App\Models\TelephonyAlert::SEVERITY_WARNING,
        );

        return OperationResult::success([
            'alert_id' => $alert->id,
        ]);
    }

    /**
     * Resolve an agent user from the callback payload.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function resolveAgentUser(array $payload): ?User
    {
        $candidates = array_values(array_filter([
            $this->stringValue($payload, 'user'),
            $this->stringValue($payload, 'agent_email'),
            $this->stringValue($payload, 'phone_login'),
            $this->stringValue($payload, 'fullname'),
        ]));

        foreach ($candidates as $candidate) {
            $user = User::query()
                ->where('vici_user', $candidate)
                ->orWhere('username', $candidate)
                ->orWhere('email', $candidate)
                ->first();
            if ($user) {
                return $user;
            }
        }

        $phoneLogin = $this->stringValue($payload, 'phone_login');
        if ($phoneLogin !== null) {
            $user = $this->callUuidMappingService->findUserByExtension($phoneLogin);
            if ($user) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Resolve the display label used for disposition records.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function resolveAgentLabel(array $payload, ?User $user): string
    {
        $label = $this->stringValue($payload, 'fullname');
        if ($label !== null) {
            return $label;
        }

        if ($user) {
            return (string) ($user->full_name ?: $user->name ?: $user->username ?: $user->vici_user ?: $user->id);
        }

        return (string) (
            $this->stringValue($payload, 'user')
            ?? $this->stringValue($payload, 'agent_email')
            ?? 'vicidial'
        );
    }

    /**
     * Find a currently active call session for start-call callbacks.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function findStartSession(
        string $campaign,
        ?User $user,
        ?int $leadId,
        ?string $phoneNumber,
        ?string $callId,
    ): ?CallSession {
        if ($callId !== null) {
            $session = CallSession::query()
                ->where('vicidial_call_id', $callId)
                ->first();
            if ($session) {
                return $session;
            }
        }

        $query = CallSession::query()
            ->where('campaign_code', $campaign)
            ->active();

        if ($user) {
            $query->where('user_id', $user->id);
        }

        if ($leadId !== null) {
            $query->where(function ($builder) use ($leadId): void {
                $builder->where('lead_id', $leadId)
                    ->orWhere('vicidial_lead_id', (string) $leadId);
            });
        }

        if ($phoneNumber !== null) {
            $query->where('phone_number', $phoneNumber);
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * Find any call session for disposition / hangup style callbacks.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function findAnySession(string $campaign, ?int $leadId, ?string $phoneNumber, ?string $callId): ?CallSession
    {
        if ($callId !== null) {
            $session = CallSession::query()
                ->where('vicidial_call_id', $callId)
                ->first();
            if ($session) {
                return $session;
            }
        }

        $query = CallSession::query()->where('campaign_code', $campaign);

        if ($leadId !== null) {
            $query->where(function ($builder) use ($leadId): void {
                $builder->where('lead_id', $leadId)
                    ->orWhere('vicidial_lead_id', (string) $leadId);
            });
        }

        if ($phoneNumber !== null) {
            $query->where('phone_number', $phoneNumber);
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * Find a call session that should receive a disposition save.
     */
    protected function findDispositionSession(string $campaign, ?int $leadId, ?string $phoneNumber, ?string $callId): ?CallSession
    {
        $session = $this->findAnySession($campaign, $leadId, $phoneNumber, $callId);
        if ($session) {
            return $session;
        }

        return null;
    }

    /**
     * Create a new local session from a Vicidial start-call callback.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $captureData
     */
    protected function createStartSession(
        User $user,
        string $campaign,
        ?int $leadId,
        string $phoneNumber,
        ?string $callId,
        array $payload,
        array $captureData,
    ): CallSession {
        $session = CallSession::create([
            'user_id' => $user->id,
            'campaign_code' => $campaign,
            'lead_id' => $leadId,
            'phone_number' => $phoneNumber,
            'vicidial_lead_id' => $leadId ? (string) $leadId : null,
            'vicidial_call_id' => $callId,
            'status' => CallSession::STATUS_DIALING,
            'dialed_at' => now(),
            'metadata' => $this->callbackMetadata($payload, 'start_call', [
                'start_call_seen_at' => now()->toIso8601String(),
                'capture_data' => $captureData,
            ]),
        ]);

        $this->telephonyLogger->info('VicidialCallUrlService', 'Created call session from start-call callback.', [
            'session_id' => $session->id,
            'campaign' => $campaign,
            'call_id' => $callId,
            'user_id' => $user->id,
        ]);

        return $session;
    }

    /**
     * Synchronize callback metadata onto an existing local session.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $extra
     */
    protected function syncSessionContext(
        CallSession $session,
        array $payload,
        string $event,
        ?int $leadId,
        ?string $phoneNumber,
        ?string $callId,
        array $extra = [],
        bool $markSeen = true,
    ): void {
        if ($callId !== null && ($session->vicidial_call_id === null || $session->vicidial_call_id === '')) {
            $session->vicidial_call_id = $callId;
        }

        if ($leadId !== null) {
            if ($session->lead_id === null) {
                $session->lead_id = $leadId;
            }

            if ($session->vicidial_lead_id === null || $session->vicidial_lead_id === '') {
                $session->vicidial_lead_id = (string) $leadId;
            }
        }

        if ($phoneNumber !== null && ($session->phone_number === null || $session->phone_number === '')) {
            $session->phone_number = $phoneNumber;
        }

        $metadata = $this->callbackMetadata($payload, $event, $extra);
        if ($markSeen) {
            $metadata['vicidial'][$event.'_seen_at'] = $metadata['vicidial'][$event.'_seen_at'] ?? now()->toIso8601String();
        }

        $session->metadata = array_replace_recursive($session->metadata ?? [], $metadata);
        $session->save();
    }

    /**
     * Build a standard Vicidial metadata payload.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function callbackMetadata(array $payload, string $event, array $extra = []): array
    {
        return [
            'vicidial' => array_replace_recursive([
                'event' => $event,
                'payload' => Arr::except($payload, ['sig']),
            ], $extra),
        ];
    }

    /**
     * Build a compact context payload for logging and alert records.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function callbackContext(array $payload, string $event): array
    {
        return array_replace_recursive($this->callbackMetadata($payload, $event), [
            'campaign' => $this->stringValue($payload, 'campaign'),
            'call_id' => $this->stringValue($payload, 'call_id'),
            'lead_id' => $this->intValue($payload, 'lead_id'),
            'phone_number' => $this->stringValue($payload, 'phone_number'),
            'status' => $this->stringValue($payload, 'status'),
            'dispo' => $this->stringValue($payload, 'dispo'),
            'user' => $this->stringValue($payload, 'user'),
            'uniqueid' => $this->stringValue($payload, 'uniqueid'),
            'session_id' => $this->stringValue($payload, 'session_id'),
            'phone_login' => $this->stringValue($payload, 'phone_login'),
            'agent_email' => $this->stringValue($payload, 'agent_email'),
            'fullname' => $this->stringValue($payload, 'fullname'),
            'term_reason' => $this->stringValue($payload, 'term_reason'),
            'call_notes' => $this->stringValue($payload, 'call_notes'),
        ]);
    }

    /**
     * Convert a campaign disposition code into the matching Vicidial label.
     */
    protected function resolveDispositionLabel(string $campaign, string $code): string
    {
        $disposition = DispositionCode::query()
            ->where(function ($builder) use ($campaign): void {
                $builder->where('campaign_code', $campaign)
                    ->orWhere('campaign_code', '');
            })
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        return $disposition?->label ?? $code;
    }

    /**
     * Render lead data in a format the existing disposition service accepts.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function callbackLeadDataJson(array $payload): ?string
    {
        $leadData = Arr::only($payload, [
            'lead_id',
            'phone_number',
            'first_name',
            'last_name',
            'fullname',
            'state',
            'city',
            'callback_lead_status',
            'callback_datetime',
            'call_notes',
            'term_reason',
            'user',
            'agent_email',
        ]);

        return $leadData === [] ? null : json_encode($leadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Pull a string from the payload safely.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function stringValue(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    /**
     * Pull an integer from the payload safely.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function intValue(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
