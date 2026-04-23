<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agent_call_dispositions')
            || ! Schema::hasTable('campaign_disposition_records')) {
            return;
        }

        $exists = DB::table('agent_call_dispositions')->exists();
        if ($exists) {
            return;
        }

        $rows = DB::table('campaign_disposition_records as cdr')
            ->leftJoin('agent_capture_records as acr', 'acr.call_session_id', '=', 'cdr.call_session_id')
            ->select([
                'cdr.id',
                'cdr.call_session_id',
                'cdr.campaign_code',
                'cdr.lead_id',
                'cdr.phone_number',
                'cdr.agent',
                'cdr.disposition_code',
                'cdr.disposition_label',
                'cdr.remarks',
                'cdr.call_duration_seconds',
                'cdr.called_at',
                'cdr.created_at',
                'cdr.updated_at',
                'acr.capture_data',
                'acr.user_id',
            ])
            ->orderBy('cdr.id')
            ->get();

        foreach ($rows as $r) {
            $leadPk = null;
            $listId = null;
            $vicidialLeadId = null;
            if ($r->lead_id !== null && $r->lead_id !== '') {
                $lead = DB::table('leads')->where('id', (int) $r->lead_id)->first();
                if ($lead) {
                    $leadPk = (int) $lead->id;
                    $listId = $lead->list_id ? (int) $lead->list_id : null;
                } else {
                    $vicidialLeadId = (string) $r->lead_id;
                }
            }

            $capture = null;
            if (! empty($r->capture_data)) {
                $decoded = json_decode((string) $r->capture_data, true);
                $capture = is_array($decoded) ? json_encode($decoded) : null;
            }

            DB::table('agent_call_dispositions')->insert([
                'call_session_id' => $r->call_session_id,
                'campaign_code' => $r->campaign_code,
                'list_id' => $listId,
                'lead_pk' => $leadPk,
                'vicidial_lead_id' => $vicidialLeadId,
                'phone_number' => $r->phone_number,
                'user_id' => $r->user_id,
                'agent' => $r->agent ?: 'unknown',
                'call_duration_seconds' => $r->call_duration_seconds,
                'disposition_code' => $r->disposition_code ?: 'OTHER',
                'disposition_label' => $r->disposition_label,
                'disposition_source' => 'agent',
                'remarks' => $r->remarks,
                'capture_data' => $capture,
                'lead_snapshot' => null,
                'last_edited_by_user_id' => null,
                'last_edited_at' => null,
                'called_at' => $r->called_at ?? $r->created_at,
                'created_at' => $r->created_at ?? now(),
                'updated_at' => $r->updated_at ?? now(),
                'deleted_at' => null,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('agent_call_dispositions')) {
            return;
        }

        DB::table('agent_call_dispositions')->truncate();
    }
};
