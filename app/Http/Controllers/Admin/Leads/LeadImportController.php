<?php

namespace App\Http\Controllers\Admin\Leads;

use App\Http\Controllers\Controller;
use App\Jobs\ImportLeadsFileJob;
use App\Models\LeadList;
use App\Services\Leads\LeadFieldService;
use App\Services\Leads\LeadImportProgressTracker;
use App\Services\Leads\LeadImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LeadImportController extends Controller
{
    public function __construct(
        protected LeadImportService $importService,
        protected LeadFieldService $fieldService,
    ) {}

    public function form(Request $request, LeadList $list): View
    {
        $this->authorize('import', $list);

        return view('admin.leads.import.upload', [
            'list' => $list,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function upload(Request $request, LeadList $list): RedirectResponse
    {
        $this->authorize('import', $list);

        // PHP silently drops the upload (and clears $_FILES) when the request
        // body exceeds post_max_size. Detect that here so the user gets a
        // friendly message instead of an HTTP 500.
        if (empty($_FILES) && empty($_POST) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $maxPost = ini_get('post_max_size') ?: '?';

            return redirect()->back()->with(
                'error',
                "Upload exceeded the server's post_max_size ({$maxPost}). Split the file or raise post_max_size / upload_max_filesize in php.ini."
            );
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls,txt', 'max:51200'],
        ]);

        try {
            $stash = $this->importService->stash($request->file('file'), $list->campaign_code);
        } catch (\Illuminate\Http\Exceptions\PostTooLargeException $e) {
            return redirect()->back()->with('error', 'Upload too large for the server. Reduce the file size or contact an admin to raise php.ini limits.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Lead import upload failed', [
                'list_id' => $list->id,
                'user_id' => $request->user()?->id,
                'file' => $request->file('file')?->getClientOriginalName(),
                'size' => $request->file('file')?->getSize(),
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return redirect()->back()->with('error', 'Upload failed: '.$e->getMessage());
        }

        $request->session()->put('lead_import_'.$list->id, [
            'token' => $stash['token'],
            'headers' => $stash['headers'],
            'preview' => $stash['preview'],
            'rows' => $stash['rows'],
        ]);

        return redirect()->route('admin.leads.import.mapping', $list);
    }

    public function mapping(Request $request, LeadList $list): View|RedirectResponse
    {
        $this->authorize('import', $list);

        $stash = $request->session()->get('lead_import_'.$list->id);
        if (! $stash) {
            return redirect()->route('admin.leads.import.form', $list)->with('error', 'Upload a file first.');
        }

        $fields = $this->fieldService->getFields($list->campaign_code)
            ->filter(fn ($f) => $f->importable)
            ->values();

        return view('admin.leads.import.mapping', [
            'list' => $list,
            'fields' => $fields,
            'stash' => $stash,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function confirm(Request $request, LeadList $list): RedirectResponse
    {
        $this->authorize('import', $list);

        $stash = $request->session()->get('lead_import_'.$list->id);
        if (! $stash) {
            return redirect()->route('admin.leads.import.form', $list)->with('error', 'Upload a file first.');
        }

        $request->validate([
            'mapping' => ['required', 'array'],
            'dedupe' => ['nullable', 'in:phone_number,vendor_lead_code'],
            'update_existing' => ['nullable', 'boolean'],
        ]);

        $mapping = collect($request->input('mapping', []))
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->filter(fn ($v) => $v !== '' && $v !== null)
            ->all();

        $hasPhone = in_array('phone_number', $mapping, true);
        if (! $hasPhone) {
            return redirect()->back()->with('error', 'At least one column must map to phone_number.');
        }

        $runId = (string) Str::uuid();
        $estimatedRows = (int) ($stash['rows'] ?? 0);

        app(LeadImportProgressTracker::class)->createQueued(
            $runId,
            $list->id,
            (int) $request->user()->id,
            $estimatedRows,
            ['dedupe' => $request->input('dedupe')],
        );

        ImportLeadsFileJob::dispatch(
            $list->id,
            $stash['token'],
            $mapping,
            [
                'dedupe' => $request->input('dedupe'),
                'update_existing' => $request->boolean('update_existing'),
            ],
            (int) $request->user()->id,
            $runId,
            $estimatedRows,
        );

        $request->session()->forget('lead_import_'.$list->id);

        $request->session()->put('lead_import_track', [
            'run_id' => $runId,
            'list_id' => $list->id,
            'list_name' => $list->name,
            'campaign_code' => $list->campaign_code,
            'estimated_rows' => $estimatedRows,
            'poll_url' => route('admin.leads.import.progress', ['list' => $list, 'runId' => $runId]),
            'dismiss_url' => route('admin.leads.import.track.dismiss'),
            'list_url' => route('admin.leads.lists.show', $list),
        ]);

        return redirect()
            ->route('admin.leads.lists.show', $list)
            ->with('success', 'Import queued. Progress is shown in the floating panel (stays visible on other pages and tabs).');
    }

    /**
     * Clear server-side import tracking after the user dismisses the progress UI.
     */
    public function dismissTrack(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin() ?? false, 403);

        $request->validate([
            'run_id' => ['nullable', 'uuid'],
            'stale' => ['sometimes', 'boolean'],
        ]);

        // Client detected dead progress (cache miss, wrong list, etc.): always
        // drop session so the layout stops injecting __LEAD_IMPORT_TRACK__ on
        // every page load.
        if ($request->boolean('stale')) {
            $request->session()->forget('lead_import_track');

            return response()->json(['ok' => true]);
        }

        $track = $request->session()->get('lead_import_track');
        $runId = $request->input('run_id');
        if ($track && $runId && ($track['run_id'] ?? '') !== $runId) {
            return response()->json(['ok' => true]);
        }

        $request->session()->forget('lead_import_track');

        return response()->json(['ok' => true]);
    }
}
