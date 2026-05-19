<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentCaptureRecord;
use App\Models\AgentScreenField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaptureRecordsController extends Controller
{
    public function index(Request $request): View
    {
        $campaign = $this->campaign($request);
        $fields = $this->fieldsForCampaign($campaign);
        $records = $this->buildFilteredQuery($campaign, $request)
            ->paginate(50)
            ->withQueryString();

        return view('admin.capture_records', [
            'campaign' => $campaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
            'fields' => $fields,
            'records' => $records,
            'filters' => [
                'agent' => (string) $request->input('agent', ''),
                'lead_id' => (string) $request->input('lead_id', ''),
                'phone' => (string) $request->input('phone', ''),
                'from_date' => (string) $request->input('from_date', ''),
                'to_date' => (string) $request->input('to_date', ''),
            ],
        ]);
    }

    public function edit(Request $request, AgentCaptureRecord $record): View|RedirectResponse
    {
        $campaign = $this->campaign($request);
        if (! $this->recordBelongsToCampaign($record, $campaign)) {
            return redirect()
                ->route('admin.capture-records.index')
                ->with('error', 'Invalid capture record.');
        }

        return view('admin.capture_records_edit', [
            'campaign' => $campaign,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
            'fields' => $this->fieldsForCampaign($campaign),
            'record' => $record,
        ]);
    }

    public function update(Request $request, AgentCaptureRecord $record): RedirectResponse
    {
        $campaign = $this->campaign($request);
        if (! $this->recordBelongsToCampaign($record, $campaign)) {
            return redirect()
                ->route('admin.capture-records.index')
                ->with('error', 'Invalid capture record.');
        }

        $validated = $request->validate([
            'lead_id' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'capture_data' => ['nullable', 'array'],
        ]);

        $fields = $this->fieldsForCampaign($campaign);
        $captureData = $this->sanitizeCaptureData(
            (array) ($validated['capture_data'] ?? []),
            $fields
        );

        $record->update([
            'lead_id' => $this->normalizeNullable($validated['lead_id'] ?? null),
            'phone_number' => $this->normalizeNullable($validated['phone_number'] ?? null),
            'capture_data' => $captureData,
        ]);

        return redirect()
            ->route('admin.capture-records.index')
            ->with('success', 'Capture record updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $campaign = $this->campaign($request);
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:agent_capture_records,id'],
        ]);

        $record = AgentCaptureRecord::query()->findOrFail((int) $validated['id']);
        if (! $this->recordBelongsToCampaign($record, $campaign)) {
            return back()->with('error', 'Invalid capture record.');
        }

        $record->delete();

        return redirect()
            ->route('admin.capture-records.index')
            ->with('success', 'Capture record deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $campaign = $this->campaign($request);
        $request->validate([
            'agent' => ['nullable', 'string', 'max:255'],
            'lead_id' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        $fields = $this->fieldsForCampaign($campaign);
        $fieldKeys = $fields->pluck('field_key')->values()->all();
        $filename = 'capture-records-'.$campaign.'-'.date('Y-m-d_His').'.csv';
        $query = $this->buildFilteredQuery($campaign, $request);

        return response()->streamDownload(function () use ($query, $fieldKeys) {
            $out = fopen('php://output', 'w');
            $header = ['id', 'created_at', 'agent', 'lead_id', 'phone_number', ...$fieldKeys];
            fputcsv($out, $header);

            foreach ($query->cursor() as $record) {
                $captureData = is_array($record->capture_data) ? $record->capture_data : [];
                $row = [
                    (string) $record->id,
                    optional($record->created_at)->format('Y-m-d H:i:s') ?? '',
                    (string) ($record->agent ?? ''),
                    (string) ($record->lead_id ?? ''),
                    (string) ($record->phone_number ?? ''),
                ];

                foreach ($fieldKeys as $fieldKey) {
                    $row[] = isset($captureData[$fieldKey]) ? (string) $captureData[$fieldKey] : '';
                }

                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function campaign(Request $request): string
    {
        return (string) $request->session()->get('campaign', 'mbsales');
    }

    /**
     * @return Collection<int, AgentScreenField>
     */
    private function fieldsForCampaign(string $campaign): Collection
    {
        return AgentScreenField::query()
            ->forCampaign($campaign)
            ->ordered()
            ->get([
                'id',
                'field_key',
                'field_label',
                'field_type',
                'options',
                'placeholder',
                'is_required',
            ]);
    }

    private function buildFilteredQuery(string $campaign, Request $request)
    {
        return AgentCaptureRecord::query()
            ->where('campaign_code', $campaign)
            ->when($request->filled('agent'), function (Builder $query) use ($request) {
                $query->where('agent', 'like', '%'.(string) $request->input('agent').'%');
            })
            ->when($request->filled('lead_id'), function (Builder $query) use ($request) {
                $query->where('lead_id', 'like', '%'.(string) $request->input('lead_id').'%');
            })
            ->when($request->filled('phone'), function (Builder $query) use ($request) {
                $query->where('phone_number', 'like', '%'.(string) $request->input('phone').'%');
            })
            ->when($request->filled('from_date'), function (Builder $query) use ($request) {
                $query->whereDate('created_at', '>=', (string) $request->input('from_date'));
            })
            ->when($request->filled('to_date'), function (Builder $query) use ($request) {
                $query->whereDate('created_at', '<=', (string) $request->input('to_date'));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function recordBelongsToCampaign(AgentCaptureRecord $record, string $campaign): bool
    {
        return (string) $record->campaign_code === $campaign;
    }

    /**
     * @param  array<string, mixed>  $captureData
     * @param  Collection<int, AgentScreenField>  $fields
     * @return array<string, string>
     */
    private function sanitizeCaptureData(array $captureData, Collection $fields): array
    {
        $allowedKeys = $fields->pluck('field_key')->all();
        $filtered = [];

        foreach ($captureData as $key => $value) {
            if (! in_array((string) $key, $allowedKeys, true)) {
                continue;
            }

            $filtered[(string) $key] = is_string($value) ? $value : (string) $value;
        }

        return $filtered;
    }

    private function normalizeNullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
