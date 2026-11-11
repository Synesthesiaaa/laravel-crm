<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManagedEmailCampaignRequest;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailProviderEvent;
use App\Models\EmailTemplate;
use App\Services\Email\CampaignLifecycle;
use App\Services\Email\CampaignMetrics;
use App\Services\EmailCampaignQueueStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class EmailManagementController extends Controller
{
    public function index(Request $request, EmailCampaignQueueStatus $queue)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', 'string', 'max:30'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $query = $this->scope($request)->with('template');
        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.$request->string('q').'%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }
        $ids = (clone $query)->select('email_campaigns.id')->reorder();
        $totals = EmailCampaignRecipient::whereIn('email_campaign_id', $ids)->selectRaw("COUNT(*) as total,
            SUM(CASE WHEN status IN ('accepted','sent') THEN 1 ELSE 0 END) as accepted,
            SUM(CASE WHEN provider_delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN provider_bounced_at IS NOT NULL THEN 1 ELSE 0 END) as bounced,
            SUM(CASE WHEN observed_opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
            SUM(CASE WHEN observed_clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked,
            SUM(CASE WHEN unsubscribed_at IS NOT NULL THEN 1 ELSE 0 END) as unsubscribed,
            SUM(CASE WHEN status IN ('failed','blocked') THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'uncertain' THEN 1 ELSE 0 END) as uncertain")->first();

        return view('admin.email-management.index', [
            'campaigns' => $query->latest()->paginate(15)->withQueryString(), 'totals' => $totals,
            'providerCoverage' => (clone $query)->where('provider', 'postmark')->count(),
            'openCoverage' => (clone $query)->where('tracking_opens', true)->count(),
            'clickCoverage' => (clone $query)->where('tracking_clicks', true)->count(),
            'templates' => EmailTemplate::where('pdf_enabled', false)->when(! $request->user()->isSuperAdmin(), fn ($q) => $q->where('created_by_user_id', $request->user()->id))->latest()->limit(100)->get(),
            'queueStatus' => $queue->inspect(), 'schedulerHeartbeat' => Cache::get('email-scheduler-heartbeat'),
            'preparationStatus' => app(\App\Services\Email\PreparationQueueStatus::class)->inspect(),
        ]);
    }

    public function store(StoreManagedEmailCampaignRequest $request)
    {
        $campaign = EmailCampaign::create([
            ...$request->safe()->only(['name', 'email_template_id', 'batch_size', 'delay_seconds']),
            'created_by_user_id' => $request->user()->id, 'purpose' => 'marketing', 'attachment_mode' => 'none',
            'status' => 'draft', 'management_version' => 3, 'audience_status' => 'empty',
            'tracking_opens' => $request->boolean('tracking_opens'), 'tracking_clicks' => $request->boolean('tracking_clicks'),
        ]);
        activity('email-campaign')->causedBy($request->user())->performedOn($campaign)->event('created')->log('Marketing campaign created');

        return redirect()->route('admin.email-management.show', $campaign)->with('success', 'Draft created. Prepare and apply a recipient audience before approval.');
    }

    public function show(Request $request, EmailCampaign $campaign, CampaignMetrics $metrics)
    {
        Gate::authorize('view', $campaign);
        $request->validate(['q' => ['nullable', 'string', 'max:255'], 'state' => ['nullable', 'in:pending,sending,accepted,sent,skipped,blocked,uncertain,failed,cancelled']]);
        $recipients = $this->recipients($request, $campaign)->orderBy('id')->paginate(50)->withQueryString();

        return view('admin.email-management.show', [
            'campaign' => $campaign->load('template'), 'recipients' => $recipients,
            'metrics' => $metrics->forCampaign($campaign),
            'events' => EmailProviderEvent::where('email_campaign_id', $campaign->id)->latest('id')->limit(25)->get(),
            'templates' => EmailTemplate::where('pdf_enabled', false)->when(! $request->user()->isSuperAdmin(), fn ($q) => $q->where('created_by_user_id', $request->user()->id))->latest()->limit(100)->get(),
        ]);
    }

    public function update(StoreManagedEmailCampaignRequest $request, EmailCampaign $campaign)
    {
        Gate::authorize('update', $campaign);
        abort_unless($campaign->management_version >= 3, 409);
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $campaign): void {
            $locked = EmailCampaign::lockForUpdate()->findOrFail($campaign->id);
            Gate::authorize('update', $locked);
            $locked->fill($request->safe()->only(['name', 'email_template_id', 'batch_size', 'delay_seconds']))->forceFill([
                'tracking_opens' => $request->boolean('tracking_opens'), 'tracking_clicks' => $request->boolean('tracking_clicks'),
                'approval_state' => 'none', 'approval_error' => null, 'generation' => $locked->generation + 1,
                'delivery_snapshot' => null, 'delivery_snapshot_hash' => null, 'manifest_hash' => null, 'dry_run_hash' => null,
            ])->save();
            activity('email-campaign')->causedBy($request->user())->performedOn($locked)->event('draft_updated')->log('Campaign draft configuration updated');
        });

        return back()->with('success', 'Draft updated. Review its audience and message before requesting approval.');
    }

    public function status(Request $request, EmailCampaign $campaign, CampaignMetrics $metrics)
    {
        Gate::authorize('view', $campaign);

        return response()->json(['id' => $campaign->id, 'status' => $campaign->status, 'approval_state' => $campaign->approval_state, 'audience_status' => $campaign->audience_status, 'metrics' => $metrics->forCampaign($campaign), 'updated_at' => now()->toIso8601String()])->header('Cache-Control', 'private, no-store');
    }

    public function action(Request $request, EmailCampaign $campaign, string $action, CampaignLifecycle $lifecycle)
    {
        Gate::authorize('view', $campaign);
        abort_unless(in_array($action, ['start', 'schedule', 'pause', 'resume', 'cancel', 'retry'], true), 404);
        $request->validate(['confirmed_action' => ['accepted']]);
        if ($action === 'schedule') {
            $data = $request->validate(['scheduled_local' => ['required', 'string', 'max:32'], 'timezone' => ['required', 'timezone'], 'utc_offset' => ['nullable', 'regex:/^[+-](0\d|1[0-4]):[0-5]\d$/']]);
            $lifecycle->instant($data['scheduled_local'], $data['timezone'], $data['utc_offset'] ?? null);
            app(\App\Services\Email\CampaignApprovalRequests::class)->request($request->user(), $campaign, 'schedule', $data);
        } elseif ($action === 'start') {
            app(\App\Services\Email\CampaignApprovalRequests::class)->request($request->user(), $campaign, 'start');
        } else {
            $lifecycle->{$action}($request->user(), $campaign);
        }

        return back()->with('success', 'Campaign action recorded: '.$action.'. Messages already in transit may still finish.');
    }

    public function report(Request $request, EmailCampaign $campaign)
    {
        Gate::authorize('view', $campaign);
        $request->validate(['identifiable' => ['sometimes', 'boolean'], 'confirmed_export' => ['required_if:identifiable,1', 'accepted_if:identifiable,1']]);
        $identifiable = $request->boolean('identifiable');
        activity('email-campaign')->causedBy($request->user())->performedOn($campaign)->event('report_export')->withProperties(['identifiable' => $identifiable])->log('Campaign report exported');

        return response()->streamDownload(function () use ($campaign, $identifiable, $request): void {
            $out = fopen('php://output', 'wb');
            $headers = ['recipient_id', 'status', 'provider_accepted_at', 'provider_delivered_at', 'provider_bounced_at', 'observed_opened_at', 'observed_clicked_at', 'unsubscribed_at'];
            if ($identifiable) {
                array_push($headers, 'email', 'name');
            }
            fputcsv($out, $headers, escape: '');
            foreach ($this->recipients($request, $campaign)->orderBy('id')->lazyById(250) as $row) {
                $values = [$row->id, $row->status, $row->sent_at?->toIso8601String(), $row->provider_delivered_at?->toIso8601String(), $row->provider_bounced_at?->toIso8601String(), $row->observed_opened_at?->toIso8601String(), $row->observed_clicked_at?->toIso8601String(), $row->unsubscribed_at?->toIso8601String()];
                if ($identifiable) {
                    array_push($values, $row->email, $row->name);
                }
                fputcsv($out, array_map(static fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value, $values), escape: '');
            }
            fclose($out);
        }, 'campaign-'.$campaign->id.'-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function scope(Request $request): Builder
    {
        return EmailCampaign::query()->when(! $request->user()->isSuperAdmin(), fn ($q) => $q->where('created_by_user_id', $request->user()->id)->where('purpose', 'marketing')->where('attachment_mode', 'none')->whereHas('template', fn ($t) => $t->where('pdf_enabled', false)));
    }

    private function recipients(Request $request, EmailCampaign $campaign): Builder
    {
        return $campaign->recipients()->getQuery()->when($request->filled('q'), fn ($q) => $q->where(fn ($where) => $where->where('email', 'like', '%'.$request->string('q').'%')->orWhere('name', 'like', '%'.$request->string('q').'%')))->when($request->filled('state'), fn ($q) => $q->where('status', $request->input('state')));
    }
}
