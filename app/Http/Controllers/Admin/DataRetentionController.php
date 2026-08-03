<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertDataRetentionPolicyRequest;
use App\Models\DataRetentionPolicy;
use Illuminate\Http\RedirectResponse;

class DataRetentionController extends Controller
{
    public function store(UpsertDataRetentionPolicyRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $isActive = $request->has('is_active')
            ? $request->boolean('is_active')
            : true;

        DataRetentionPolicy::query()->updateOrCreate(
            ['form_id' => $validated['form_id']],
            [
                'cutoff_date' => $validated['cutoff_date'],
                'is_active' => $isActive,
            ],
        );

        return redirect()
            ->route('admin.configuration', [
                'tab' => 'retention',
                'retention_form' => $validated['form_id'],
            ])
            ->with('status', 'Data retention policy saved.');
    }

    public function deactivate(DataRetentionPolicy $policy): RedirectResponse
    {
        $policy->update(['is_active' => false]);

        return redirect()
            ->route('admin.configuration', [
                'tab' => 'retention',
                'retention_form' => $policy->form_id,
            ])
            ->with('status', 'Data retention policy deactivated.');
    }
}
