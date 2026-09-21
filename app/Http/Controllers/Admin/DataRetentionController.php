<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertDataRetentionPolicyRequest;
use App\Models\DataRetentionPolicy;
use App\Services\DataRetentionScheduleService;
use App\Services\DataRetentionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

class DataRetentionController extends Controller
{
    public function store(
        UpsertDataRetentionPolicyRequest $request,
        DataRetentionScheduleService $scheduleService,
    ): RedirectResponse {
        $validated = $request->validated();
        $isActive = $request->has('is_active')
            ? $request->boolean('is_active')
            : true;

        $policy = DataRetentionPolicy::query()->updateOrCreate(
            ['form_id' => $validated['form_id']],
            [
                'from_date' => $validated['from_date'],
                'to_date' => $validated['to_date'],
                'deletion_mode' => $validated['deletion_mode'],
                'selected_fields' => $validated['deletion_mode'] === 'selected_fields'
                    ? array_values($validated['selected_fields'])
                    : null,
                'is_active' => $isActive,
                'run_mode' => $validated['run_mode'],
                'run_at' => $validated['run_mode'] === 'once' ? $validated['run_at'] : null,
                'recurrence' => $validated['run_mode'] === 'recurring' ? $validated['recurrence'] : null,
                'run_time' => $validated['run_mode'] === 'recurring' ? $validated['run_time'] : null,
                'run_day_of_week' => ($validated['recurrence'] ?? null) === 'weekly'
                    ? $validated['run_day_of_week']
                    : null,
                'run_day_of_month' => ($validated['recurrence'] ?? null) === 'monthly'
                    ? $validated['run_day_of_month']
                    : null,
            ],
        );

        $policy->forceFill([
            'next_run_at' => $isActive
                ? $scheduleService->nextRunAt($policy, CarbonImmutable::now())
                : null,
        ])->save();

        return redirect()
            ->route('admin.configuration', [
                'tab' => 'retention',
                'retention_form' => $validated['form_id'],
            ])
            ->with('status', 'Data retention policy saved.');
    }

    public function run(DataRetentionPolicy $policy, DataRetentionService $retentionService): RedirectResponse
    {
        $result = $retentionService->runPolicy($policy, true);
        $message = $result['status'] === 'success'
            ? 'Data retention policy ran successfully. Affected records: '.$result['deleted'].'.'
            : 'Data retention policy was '.$result['status'].': '.$result['error'];

        return redirect()
            ->route('admin.configuration', [
                'tab' => 'retention',
                'retention_form' => $policy->form_id,
            ])
            ->with($result['status'] === 'success' ? 'status' : 'error', $message);
    }

    public function destroy(DataRetentionPolicy $policy): RedirectResponse
    {
        $formId = $policy->form_id;
        $policy->delete();

        return redirect()
            ->route('admin.configuration', [
                'tab' => 'retention',
                'retention_form' => $formId,
            ])
            ->with('status', 'Data retention policy configuration deleted. Form data was not changed.');
    }
}
