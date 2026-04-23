<?php

namespace App\Services\Telephony;

use App\Events\DispositionSaved;
use App\Models\AgentCallDisposition;
use App\Models\Lead;
use App\Models\LeadHopper;
use App\Models\User;
use App\Support\LeadPayloadBuilder;
use App\Support\VicidialDispositionMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ViciDialInboundDispoService
{
    public function __construct(
        protected TelephonyLogger $telephonyLogger,
    ) {}

    /**
     * Apply disposition from ViciDial Agent Push webhook (dispo_set).
     */
    public function applyFromWebhook(?User $user, mixed $vicidialLeadId, string $viciStatusMessage): void
    {
        if (! config('vicidial.inbound_dispo_enabled', false)) {
            return;
        }

        $viciStatus = trim($viciStatusMessage);
        if ($viciStatus === '') {
            return;
        }

        $lead = $this->resolveLead($vicidialLeadId);
        $crmCode = VicidialDispositionMap::mapVicidialToCrm($viciStatus);

        try {
            DB::transaction(function () use ($lead, $crmCode, $viciStatus, $user, $vicidialLeadId) {
                if ($lead && $this->isRecentDuplicate($lead, $crmCode, AgentCallDisposition::SOURCE_VICIDIAL_WEBHOOK)) {
                    return;
                }

                if ($lead) {
                    // Complete hopper before updating lead status so LeadObserver does not purge pending rows first.
                    $this->completeHopperForLead($lead->id);
                    $this->updateLeadFromInbound($lead, $crmCode);
                }

                $agentLabel = $user
                    ? ($user->full_name ?? $user->name ?? $user->username ?? 'agent')
                    : 'VICIDIAL_AUTO';

                AgentCallDisposition::create([
                    'call_session_id' => null,
                    'campaign_code' => $lead?->campaign_code ?? 'unknown',
                    'list_id' => $lead?->list_id,
                    'lead_pk' => $lead?->id,
                    'vicidial_lead_id' => $vicidialLeadId !== null && $vicidialLeadId !== '' ? (string) $vicidialLeadId : null,
                    'phone_number' => $lead?->phone_number,
                    'user_id' => $user?->id,
                    'agent' => $agentLabel,
                    'call_duration_seconds' => null,
                    'disposition_code' => $crmCode,
                    'disposition_label' => $viciStatus,
                    'disposition_source' => AgentCallDisposition::SOURCE_VICIDIAL_WEBHOOK,
                    'remarks' => null,
                    'capture_data' => null,
                    'lead_snapshot' => $lead ? LeadPayloadBuilder::snapshot($lead) : null,
                    'called_at' => now(),
                ]);

                if ($lead) {
                    event(new DispositionSaved($lead->campaign_code, $agentLabel, $crmCode, $lead->id));
                }
            });
        } catch (\Throwable $e) {
            $this->telephonyLogger->warning('ViciDialInboundDispoService', 'applyFromWebhook failed', [
                'error' => $e->getMessage(),
                'lead_id' => $vicidialLeadId,
            ]);
        }
    }

    /**
     * Apply disposition from periodic vicidial_list poll (one row).
     *
     * @param  array<string, mixed>  $row
     */
    public function applyFromPoll(Lead $lead, string $viciStatus, array $row = []): void
    {
        if (! config('vicidial.inbound_poll_enabled', false)) {
            return;
        }

        $crmCode = VicidialDispositionMap::mapVicidialToCrm($viciStatus);
        if (strtoupper(trim($lead->status ?? '')) === strtoupper($crmCode)) {
            return;
        }

        try {
            DB::transaction(function () use ($lead, $crmCode, $viciStatus, $row) {
                if ($this->isRecentDuplicate($lead, $crmCode, AgentCallDisposition::SOURCE_VICIDIAL_POLL)) {
                    return;
                }

                $this->completeHopperForLead($lead->id);
                $this->updateLeadFromInbound($lead->fresh(), $crmCode);

                $snapshotLead = Lead::find($lead->id);

                AgentCallDisposition::create([
                    'call_session_id' => null,
                    'campaign_code' => $lead->campaign_code,
                    'list_id' => $lead->list_id,
                    'lead_pk' => $lead->id,
                    'vicidial_lead_id' => isset($row['lead_id']) ? (string) $row['lead_id'] : null,
                    'phone_number' => $lead->phone_number,
                    'user_id' => null,
                    'agent' => 'VICIDIAL_AUTO',
                    'call_duration_seconds' => null,
                    'disposition_code' => $crmCode,
                    'disposition_label' => $viciStatus,
                    'disposition_source' => AgentCallDisposition::SOURCE_VICIDIAL_POLL,
                    'remarks' => null,
                    'capture_data' => null,
                    'lead_snapshot' => $snapshotLead ? LeadPayloadBuilder::snapshot($snapshotLead) : null,
                    'called_at' => now(),
                ]);

                event(new DispositionSaved($lead->campaign_code, 'VICIDIAL_AUTO', $crmCode, $lead->id));
            });
        } catch (\Throwable $e) {
            Log::warning('ViciDialInboundDispoService: applyFromPoll failed', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function isRecentDuplicate(Lead $lead, string $crmCode, string $source): bool
    {
        return AgentCallDisposition::query()
            ->where('lead_pk', $lead->id)
            ->where('disposition_code', $crmCode)
            ->where('disposition_source', $source)
            ->where('created_at', '>=', now()->subMinute())
            ->exists();
    }

    protected function updateLeadFromInbound(Lead $lead, string $crmCode): void
    {
        $lead->update([
            'status' => $crmCode,
            'called_count' => max(0, (int) $lead->called_count) + 1,
            'last_called_at' => now(),
            'last_local_call_time' => now(),
        ]);
    }

    protected function completeHopperForLead(int $leadPk): void
    {
        LeadHopper::where('lead_pk', $leadPk)
            ->whereIn('status', ['pending', 'assigned'])
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
    }

    protected function resolveLead(mixed $vicidialLeadId): ?Lead
    {
        if ($vicidialLeadId === null || $vicidialLeadId === '') {
            return null;
        }

        if (is_numeric($vicidialLeadId)) {
            $byPk = Lead::find((int) $vicidialLeadId);
            if ($byPk) {
                return $byPk;
            }
        }

        $s = (string) $vicidialLeadId;

        return Lead::query()
            ->where(function ($q) use ($s) {
                $q->where('vendor_lead_code', $s)->orWhere('phone_number', $s);
            })
            ->first();
    }
}
