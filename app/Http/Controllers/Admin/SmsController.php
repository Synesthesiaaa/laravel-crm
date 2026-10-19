<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SmsCampaignRequest;
use App\Http\Requests\SmsGatewayRequest;
use App\Models\LeadHopper;
use App\Models\SmsCampaign;
use App\Models\SmsChannel;
use App\Models\SmsConsent;
use App\Models\SmsGateway;
use App\Models\SmsMessage;
use App\Models\SmsOptOut;
use App\Models\SmsTemplate;
use App\Services\Sms\GoipHttpTransport;
use App\Services\Sms\SmsCampaignService;
use App\Services\Sms\SmsText;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SmsController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['workspace' => ['nullable', Rule::in(['overview', 'campaigns', 'history', 'templates', 'gateways', 'reports'])], 'q' => 'nullable|string|max:100', 'status' => 'nullable|string|max:20', 'direction' => ['nullable', Rule::in(['inbound', 'outbound'])], 'phone' => 'nullable|string|max:16']);
        $workspace = $filters['workspace'] ?? 'overview';
        $phoneFilter = $filters['phone'] ?? $request->session()->get('sms.history_phone');
        $campaigns = SmsCampaign::with('gateway')->withCount([
            'messages',
            'messages as eligible_count' => fn ($q) => $q->whereIn('status', ['eligible', 'queued', 'processing', 'submitted', 'delivered', 'failed', 'unknown']),
            'messages as submitted_count' => fn ($q) => $q->where('status', 'submitted'),
            'messages as delivered_count' => fn ($q) => $q->where('status', 'delivered'),
            'messages as failed_count' => fn ($q) => $q->where('status', 'failed'),
        ])->when($filters['q'] ?? null, fn ($query, $value) => $query->where('name', 'like', '%'.$value.'%'))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))->latest()->paginate(15, ['*'], 'campaign_page')->withQueryString();
        $messages = SmsMessage::with(['campaign', 'channel.gateway'])->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['direction'] ?? null, fn ($query, $value) => $query->where('direction', $value))
            ->when($phoneFilter, fn ($query, $value) => $query->where('phone', $value))->latest()->paginate(20, ['*'], 'message_page')->withQueryString();
        $counts = SmsMessage::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $daily = SmsMessage::selectRaw('DATE(created_at) as day, COUNT(*) as total')->where('created_at', '>=', now()->subDays(6)->startOfDay())->groupByRaw('DATE(created_at)')->orderBy('day')->get();

        return view('admin.sms.index', [
            'workspace' => $workspace, 'campaigns' => $campaigns, 'messages' => $messages, 'counts' => $counts, 'daily' => $daily,
            'gateways' => SmsGateway::with(['channels' => fn ($q) => $q->withCount([
                'messages as queued_count' => fn ($q) => $q->where('status', 'queued'),
                'messages as processing_count' => fn ($q) => $q->where('status', 'processing'),
                'messages as submitted_count' => fn ($q) => $q->where('status', 'submitted'),
                'messages as failure_count' => fn ($q) => $q->whereIn('status', ['failed', 'unknown']),
            ])])->orderBy('name')->limit(100)->get(),
            'failures' => SmsMessage::with('campaign')->whereIn('status', ['failed', 'unknown'])->latest()->limit(5)->get(),
            'templates' => SmsTemplate::orderBy('name')->paginate(15, ['*'], 'template_page')->withQueryString(),
            'campaignTotal' => SmsCampaign::count(), 'activeCampaigns' => SmsCampaign::whereIn('status', ['queued', 'scheduled', 'running'])->count(),
            'enabledChannels' => SmsChannel::where('enabled', true)->count(),
            'schedulerHeartbeat' => Cache::get('sms:scheduler-heartbeat'), 'workerHeartbeat' => Cache::get('sms:worker-heartbeat'),
            'receiverHeartbeat' => Cache::get('sms:receiver-heartbeat'),
            'phoneFilter' => $phoneFilter,
        ]);
    }

    public function historyFilter(Request $request)
    {
        $data = $request->validate(['phone' => 'nullable|string|max:50', 'direction' => ['nullable', Rule::in(['inbound', 'outbound'])], 'status' => ['nullable', Rule::in(['queued', 'processing', 'submitted', 'delivered', 'unknown', 'failed', 'canceled', 'received'])]]);

        try {
            $phone = empty($data['phone']) ? null : SmsText::phone($data['phone']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['phone' => $e->getMessage()]);
        }
        $request->session()->put('sms.history_phone', $phone);

        return redirect()->route('admin.sms.index', ['workspace' => 'history', 'direction' => $data['direction'] ?? null, 'status' => $data['status'] ?? null]);
    }

    public function store(SmsCampaignRequest $request, SmsCampaignService $service)
    {
        $data = $request->validated();

        try {
            SmsText::render($data['body'], 'Recipient', $data['name']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['body' => $e->getMessage()]);
        }
        if ($data['category'] === 'marketing' && ! preg_match('/\bSTOP\b/i', $data['body'])) {
            throw ValidationException::withMessages(['body' => 'Marketing messages must include opt-out instructions with STOP and a monitored reply channel.']);
        }
        if ($data['category'] === 'marketing' && ! SmsGateway::findOrFail($data['sms_gateway_id'])->inbound_verified) {
            throw ValidationException::withMessages(['category' => 'Verify a monitored inbound STOP channel before preparing marketing campaigns.']);
        }
        $data['scheduled_at'] = empty($data['scheduled_at']) ? null : Carbon::parse($data['scheduled_at'], 'Asia/Manila')->timezone(config('app.timezone'));
        $campaign = SmsCampaign::create([...$data, 'created_by' => $request->user()->id]);
        $service->audit($request->user(), 'Campaign draft created', $campaign);

        return redirect()->route('admin.sms.review', $campaign)->with('success', 'Draft created. Add consented recipients, then review the audience.');
    }

    public function review(SmsCampaign $campaign, SmsCampaignService $service)
    {
        return view('admin.sms.review', ['campaign' => $campaign->load('gateway'), 'preview' => $service->preview($campaign), 'recipients' => $campaign->messages()->orderBy('id')->paginate(25), 'leadGroups' => LeadHopper::select('campaign_code')->distinct()->orderBy('campaign_code')->limit(100)->pluck('campaign_code')]);
    }

    public function recipient(Request $request, SmsCampaign $campaign, SmsCampaignService $service)
    {
        $row = $request->validate(['phone' => 'required|string|max:50', 'recipient_name' => 'nullable|string|max:150']);
        $service->addRecipient($campaign, $row, $request->user());

        return back()->with('success', 'Recipient evaluated. Review the eligibility result.');
    }

    public function consent(Request $request, SmsCampaignService $service)
    {
        $data = $request->validate(['phone' => 'required|string|max:50', 'category' => ['required', Rule::in(['marketing', 'operational'])], 'source' => 'required|string|max:255', 'consented_at' => 'required|date|before_or_equal:now', 'confirm_evidence' => 'required|accepted']);
        $service->recordConsent($data, $data['category'], $request->user());

        return back()->with('success', 'Consent evidence saved. Existing opt-outs remain in force. Add the recipient to a draft to evaluate it.');
    }

    public function revoke(Request $request, SmsCampaignService $service)
    {
        $data = $request->validate(['phone' => 'required|string|max:50']);

        try {
            $phone = SmsText::phone($data['phone']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['phone' => $e->getMessage()]);
        }
        DB::transaction(function () use ($phone): void {
            SmsConsent::where('phone', $phone)->update(['revoked_at' => now()]);
            SmsOptOut::firstOrCreate(['phone' => $phone], ['source' => 'administrator']);
            SmsMessage::where('phone', $phone)->where('status', 'queued')->update(['status' => 'canceled', 'reason' => 'consent_revoked']);
        });
        $service->audit($request->user(), 'Recipient consent revoked and suppression recorded');

        return back()->with('success', 'Consent revoked. Pending messages canceled. Already submitted messages cannot be recalled.');
    }

    public function import(Request $request, SmsCampaign $campaign, SmsCampaignService $service)
    {
        $request->validate(['recipients' => 'required|file|max:2048|mimes:csv,txt', 'confirm_evidence' => 'required|accepted']);
        abort_unless($campaign->status === 'draft', 409);
        $file = fopen($request->file('recipients')->getRealPath(), 'r');

        try {
            $headers = fgetcsv($file, 8192, ',', '"', '');
            if ($headers) {
                $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
            }
            if ($headers !== ['phone', 'recipient_name', 'consent_source', 'consented_at']) {
                throw ValidationException::withMessages(['recipients' => 'Use the supplied CSV template and its exact column order.']);
            }
            $position = ftell($file);
            $count = $campaign->messages()->count();
            // Validate the entire stream first, without holding database locks.
            foreach ($this->csvRows($file) as [$phone, $name, $source, $at]) {
                if (++$count > config('sms.max_recipients')) {
                    throw ValidationException::withMessages(['recipients' => 'Campaign recipient limit exceeded. No rows imported.']);
                }
                if ($source !== '' && $at !== '') {
                    validator(['source' => $source, 'consented_at' => $at], ['source' => 'string|max:255', 'consented_at' => 'date|before_or_equal:now'])->validate();
                }
            }
            fseek($file, $position);
            foreach ($this->csvRows($file) as [$phone, $name, $source, $at]) {
                DB::transaction(function () use ($phone, $name, $source, $at, $campaign, $service, $request): void {
                    if ($source !== '' && $at !== '') {
                        try {
                            SmsText::phone($phone);
                            $service->recordConsent(['phone' => $phone, 'source' => $source, 'consented_at' => $at], $campaign->category, $request->user());
                        } catch (InvalidArgumentException) {
                            // Invalid destinations remain in the exclusion ledger.
                        }
                    }
                    $service->addRecipient($campaign, ['phone' => $phone, 'recipient_name' => $name], $request->user());
                });
            }
        } finally {
            fclose($file);
        }
        $service->audit($request->user(), 'Recipient CSV evaluated', $campaign);

        return back()->with('success', 'Import evaluated. Check excluded rows and final eligible recipients.');
    }

    private function csvRows($file): \Generator
    {
        while (($line = fgets($file, 8194)) !== false) {
            if (strlen($line) > 8192 || ! preg_match('/\A(?:"(?:[^"\r\n]|"")*"|[^",\r\n]*)(?:,(?:"(?:[^"\r\n]|"")*"|[^",\r\n]*)){3}\r?\n?\z/D', $line)) {
                throw ValidationException::withMessages(['recipients' => 'CSV must contain four correctly quoted fields per line. No rows imported.']);
            }
            $values = str_getcsv(rtrim($line, "\r\n"), ',', '"', '');
            if (count($values) !== 4 || max(array_map(fn ($value) => strlen((string) $value), $values)) > 1000) {
                throw ValidationException::withMessages(['recipients' => 'Malformed CSV or oversized record. No rows imported.']);
            }
            yield array_map(fn ($value) => (string) $value, $values);
        }
    }

    public function update(SmsCampaignRequest $request, SmsCampaign $campaign, SmsCampaignService $service)
    {
        $data = $request->validated();

        try {
            SmsText::render($data['body'], 'Recipient', $data['name']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['body' => $e->getMessage()]);
        }
        if ($data['category'] === 'marketing' && (! preg_match('/\bSTOP\b/i', $data['body']) || ! SmsGateway::findOrFail($data['sms_gateway_id'])->inbound_verified)) {
            throw ValidationException::withMessages(['body' => 'Marketing requires STOP instructions and a verified monitored inbound channel.']);
        }
        $data['scheduled_at'] = empty($data['scheduled_at']) ? null : Carbon::parse($data['scheduled_at'], 'Asia/Manila')->timezone(config('app.timezone'));
        DB::transaction(function () use ($campaign, $data): void {
            $campaign = SmsCampaign::lockForUpdate()->findOrFail($campaign->id);
            abort_unless($campaign->status === 'draft' && ! $campaign->messages()->exists(), 409, 'Reset draft recipients before changing campaign content or policies.');
            $campaign->fill($data);
            $campaign->revision++;
            $campaign->save();
        });
        $service->audit($request->user(), 'Campaign draft updated', $campaign);

        return back()->with('success', 'Draft updated. Add recipients and confirm the new audience.');
    }

    public function resetAudience(Request $request, SmsCampaign $campaign, SmsCampaignService $service)
    {
        $request->validate(['confirm_reset' => 'required|accepted']);
        DB::transaction(function () use ($campaign): void {
            $campaign = SmsCampaign::lockForUpdate()->findOrFail($campaign->id);
            abort_unless($campaign->status === 'draft', 409);
            $campaign->messages()->delete();
            $campaign->increment('revision');
        });
        $service->audit($request->user(), 'Unsubmitted draft audience reset', $campaign);

        return back()->with('success', 'Draft recipient decisions reset. Consent evidence and opt-outs were preserved.');
    }

    public function leads(Request $request, SmsCampaign $campaign, SmsCampaignService $service)
    {
        $data = $request->validate(['campaign_code' => 'required|string|max:50']);
        abort_unless($campaign->status === 'draft', 409);
        $query = LeadHopper::where('campaign_code', $data['campaign_code']);
        if ($query->count() + $campaign->messages()->count() > config('sms.max_recipients')) {
            throw ValidationException::withMessages(['campaign_code' => 'This lead group exceeds the campaign size limit.']);
        }
        $query->orderBy('id')->chunkById(100, function ($leads) use ($campaign, $service, $request): void {
            foreach ($leads as $lead) {
                $service->addRecipient($campaign, ['phone' => $lead->phone_number, 'recipient_name' => $lead->client_name], $request->user(), $lead->id);
            }
        });
        $service->audit($request->user(), 'CRM lead audience evaluated', $campaign);

        return back()->with('success', 'CRM leads evaluated using existing consent evidence.');
    }

    public function confirm(Request $request, SmsCampaign $campaign, SmsCampaignService $service)
    {
        $data = $request->validate(['digest' => 'required|string|size:64', 'confirm_audience' => 'required|accepted', 'large_send' => 'sometimes|accepted']);
        $connection = config('sms.queue_connection');
        if (! in_array(config("queue.connections.{$connection}.driver"), ['database', 'redis'], true)) {
            throw ValidationException::withMessages(['queue' => 'Configure a durable SMS queue before sending.']);
        }
        $service->confirm($campaign, $data['digest'], $request->boolean('large_send'), $request->user());

        return back()->with('success', 'Audience confirmed. The scheduler will queue eligible messages for the configured window.');
    }

    public function transition(Request $request, SmsCampaign $campaign, string $action, SmsCampaignService $service)
    {
        $service->transition($campaign, $action, $request->user());

        return back()->with('success', 'Campaign updated. Messages already being submitted may still complete.');
    }

    public function testMessage(Request $request, SmsCampaign $campaign, SmsCampaignService $service)
    {
        $request->validate(['confirm_test' => 'required|accepted']);

        try {
            $phone = SmsText::phone((string) config('sms.test_number'));
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['test' => 'Configure SMS_TEST_NUMBER with the designated, approved test recipient.']);
        }
        DB::transaction(function () use ($request, $campaign, $service, $phone): void {
            $test = $campaign->replicate(['confirmed_at']);
            $test->fill(['name' => mb_substr('Test: '.$campaign->name, 0, 150), 'status' => 'draft', 'scheduled_at' => null, 'created_by' => $request->user()->id])->save();
            $service->addRecipient($test, ['phone' => $phone, 'recipient_name' => 'Designated test recipient'], $request->user());
            $service->confirm($test->fresh(), $service->preview($test->fresh())['digest'], false, $request->user());
            $service->audit($request->user(), 'Designated test SMS explicitly queued', $test);
        });

        return back()->with('success', 'One designated test message was queued through the normal consent and sending policy. This does not certify receipt.');
    }

    public function gateway(SmsGatewayRequest $request, SmsCampaignService $service, ?SmsGateway $gateway = null)
    {
        $data = $request->validated();
        $gateway ??= new SmsGateway;
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $verified = $request->boolean('verified');
        unset($data['verified']);
        $gateway->fill([...$data, 'enabled' => $request->boolean('enabled'), 'inbound_verified' => $request->boolean('inbound_verified'), 'verified_at' => $verified ? now() : null]);
        if ($gateway->inbound_verified && ! $verified) {
            throw ValidationException::withMessages(['verified' => 'Document verification before enabling the inbound profile.']);
        }
        $gateway->save();
        $service->audit($request->user(), 'Gateway configuration saved', $gateway);

        return back()->with('success', 'Gateway saved. No live SMS was sent.');
    }

    public function channel(Request $request, SmsGateway $gateway, SmsCampaignService $service)
    {
        $data = $request->validate(['number' => 'required|integer|min:1|max:32', 'client_id' => 'nullable|string|max:100|regex:/^[a-zA-Z0-9_-]+$/', 'password' => 'nullable|string|max:255', 'enabled' => 'nullable|boolean', 'interval_seconds' => 'required|integer|min:30|max:86400', 'daily_limit' => 'required|integer|min:1|max:10000']);
        $channel = $gateway->channels()->firstOrNew(['number' => $data['number']]);
        if (! empty($data['client_id']) && SmsChannel::where('client_id', $data['client_id'])->when($channel->exists, fn ($q) => $q->where('id', '!=', $channel->id))->exists()) {
            throw ValidationException::withMessages(['client_id' => 'Client ID is already configured.']);
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $channel->fill([...$data, 'enabled' => $request->boolean('enabled')])->save();
        $service->audit($request->user(), 'Gateway channel configuration saved', $gateway);

        return back()->with('success', 'Channel saved. Only configure channels verified on the actual gateway.');
    }

    public function connection(Request $request, SmsGateway $gateway, GoipHttpTransport $transport, SmsCampaignService $service)
    {
        try {
            $result = $transport->connectivity($gateway);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['host' => $e->getMessage()]);
        }
        $service->audit($request->user(), 'Gateway network connectivity tested', $gateway);

        return back()->with('success', $result);
    }

    public function template(Request $request, SmsCampaignService $service, ?SmsTemplate $template = null)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'body' => 'required|string|max:1000']);

        try {
            SmsText::render($data['body'], 'Recipient', 'Campaign');
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['body' => $e->getMessage()]);
        }
        ($template ?? new SmsTemplate)->fill($data)->save();
        $service->audit($request->user(), 'SMS template saved');

        return back()->with('success', 'Template saved.');
    }

    public function read(SmsMessage $message)
    {
        abort_unless($message->direction === 'inbound', 409);
        $message->update(['read_at' => now()]);

        return back()->with('success', 'Message marked read.');
    }

    public function csvTemplate()
    {
        return response("phone,recipient_name,consent_source,consented_at\r\n", 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="sms-recipients.csv"']);
    }

    public function emergencyStop(Request $request, SmsCampaignService $service)
    {
        DB::transaction(function (): void {
            SmsGateway::query()->update(['enabled' => false]);
            SmsCampaign::whereIn('status', ['queued', 'scheduled', 'running'])->update(['status' => 'paused']);
        });
        $service->audit($request->user(), 'Emergency stop: all SMS gateways disabled and active campaigns paused');

        return back()->with('success', 'Emergency stop applied. All gateways disabled and active campaigns paused. In-flight submissions cannot be recalled.');
    }

    public function reconcile(Request $request, SmsMessage $message, SmsCampaignService $service)
    {
        $data = $request->validate(['outcome' => ['required', Rule::in(['submitted', 'failed'])], 'evidence' => 'required|string|min:30|max:1000']);
        $updated = SmsMessage::whereKey($message->id)->where('status', 'unknown')->update([
            'status' => $data['outcome'], 'reason' => 'operator_reconciled',
            'submitted_at' => $data['outcome'] === 'submitted' ? now() : null,
        ]);
        abort_unless($updated, 409, 'Only unknown outcomes can be reconciled.');
        activity('sms')->causedBy($request->user())->performedOn($message)
            ->withProperties(['outcome' => $data['outcome'], 'evidence_sha256' => hash('sha256', $data['evidence'])])->log('Unknown SMS outcome reconciled by operator');

        return back()->with('success', 'Outcome reconciled. No SMS was resent. Re-enable the affected channel only after review.');
    }

    public function report(Request $request, SmsCampaignService $service)
    {
        $filters = $request->validate(['campaign_id' => 'nullable|integer|exists:sms_campaigns,id']);
        $service->audit($request->user(), 'SMS report exported');

        return response()->streamDownload(function () use ($filters): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Message ID', 'Campaign ID', 'Phone', 'Direction', 'Status', 'Reason', 'Submitted at'], ',', '"', '');
            SmsMessage::when($filters['campaign_id'] ?? null, fn ($q, $id) => $q->where('sms_campaign_id', $id))->orderBy('id')->chunkById(200, function ($messages) use ($out): void {
                foreach ($messages as $message) {
                    $values = [$message->id, $message->sms_campaign_id, $message->phone, $message->direction, $message->status === 'submitted' ? 'Submitted to Gateway' : $message->status, $message->reason, $message->submitted_at?->toIso8601String()];
                    fputcsv($out, array_map(static fn ($value) => preg_match('/^[\s]*[=+@\-]/u', (string) $value) ? "'".$value : $value, $values), ',', '"', '');
                }
            });
            fclose($out);
        }, 'sms-report.csv', ['Content-Type' => 'text/csv']);
    }
}
