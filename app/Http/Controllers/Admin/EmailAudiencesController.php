<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StageEmailAudienceRequest;
use App\Models\EmailAudienceImport;
use App\Models\EmailCampaign;
use App\Models\EmailContact;
use App\Models\EmailSegment;
use App\Services\Email\AudienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailAudiencesController extends Controller
{
    public function __construct(private AudienceService $audiences) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        $actor = $request->user();

        return view('admin.email-audiences.index', [
            'contacts' => $this->audiences->contacts($actor, $request->only(['q', 'consent_status']))->latest('id')->paginate(25)->withQueryString(),
            'segments' => EmailSegment::when(! $actor->isSuperAdmin(), fn ($q) => $q->where('user_id', $actor->id))->latest()->limit(100)->get(),
            'drafts' => EmailCampaign::where('status', 'draft')->where('purpose', 'marketing')->where('attachment_mode', 'none')->whereHas('template', fn ($q) => $q->where('pdf_enabled', false))->when(! $actor->isSuperAdmin(), fn ($q) => $q->where('created_by_user_id', $actor->id))->latest()->limit(100)->get(),
            'imports' => EmailAudienceImport::when(! $actor->isSuperAdmin(), fn ($q) => $q->where('user_id', $actor->id))->latest()->limit(20)->get(),
        ]);
    }

    public function stage(StageEmailAudienceRequest $request): JsonResponse
    {
        $run = $this->audiences->stage($request->user(), EmailCampaign::findOrFail($request->integer('campaign_id')), $request->validated(), $request->file('file'));

        return response()->json($this->audiences->present($run), 202)->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, EmailAudienceImport $audienceImport): JsonResponse
    {
        $this->audiences->authorizeImport($request->user(), $audienceImport);

        return response()->json($this->audiences->present($audienceImport))->header('Cache-Control', 'private, no-store');
    }

    public function apply(Request $request, EmailAudienceImport $audienceImport): JsonResponse
    {
        $request->validate(['confirmed_audience' => ['accepted']]);
        $this->audiences->apply($request->user(), $audienceImport);

        return response()->json($this->audiences->present($audienceImport->refresh()), 202)->header('Cache-Control', 'private, no-store');
    }

    public function cancel(Request $request, EmailAudienceImport $audienceImport): JsonResponse
    {
        return response()->json($this->audiences->present($this->audiences->cancel($request->user(), $audienceImport)))->header('Cache-Control', 'private, no-store');
    }

    public function resume(Request $request, EmailAudienceImport $audienceImport): JsonResponse
    {
        return response()->json($this->audiences->present($this->audiences->resume($request->user(), $audienceImport)), 202)->header('Cache-Control', 'private, no-store');
    }

    public function report(Request $request, EmailAudienceImport $audienceImport)
    {
        $this->audiences->authorizeImport($request->user(), $audienceImport);

        return response()->streamDownload(function () use ($audienceImport): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['record_number', 'error_code'], ',', '"', '');
            foreach (\Illuminate\Support\Facades\DB::table('email_audience_rows')->where('import_id', $audienceImport->id)->whereNotNull('error_code')->orderBy('id')->cursor() as $row) {
                fputcsv($stream, [$row->record_number, $row->error_code], ',', '"', '');
            }
            fclose($stream);
        }, 'audience-errors-'.$audienceImport->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function storeContact(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255'], 'name' => ['nullable', 'string', 'max:255'], 'tags' => ['nullable', 'string', 'max:500'], 'consent_status' => ['required', 'in:unknown,asserted,withdrawn'], 'consent_source' => ['required_if:consent_status,asserted', 'nullable', 'string', 'max:500'], 'consent_asserted_at' => ['required_if:consent_status,asserted', 'nullable', 'date', 'before_or_equal:now']]);
        $contact = EmailContact::firstOrNew(['user_id' => $request->user()->id, 'email' => strtolower(trim($data['email']))]);
        $contact->fill(['name' => $data['name'] ?? null, 'tags' => array_values(array_unique(array_filter(array_map('trim', explode(',', $data['tags'] ?? ''))))), 'source' => $contact->source ?: 'manual', 'consent_status' => $data['consent_status'], 'consent_source' => $data['consent_status'] === 'asserted' ? $data['consent_source'] : null, 'consent_asserted_at' => $data['consent_status'] === 'asserted' ? $data['consent_asserted_at'] : null, 'consent_actor_id' => $request->user()->id])->save();
        activity('email-campaign')->causedBy($request->user())->withProperties(['contact_id' => $contact->id, 'consent_status' => $contact->consent_status])->log('email_campaign.contact_updated');

        return back()->with('success', 'Contact saved. A permission assertion records your evidence; it does not override suppression.');
    }

    public function storeSegment(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'filters' => ['sometimes', 'array'], 'filters.tag' => ['nullable', 'string', 'max:100'], 'filters.campaign_code' => ['nullable', 'string', 'max:100'], 'filters.source' => ['nullable', 'string', 'max:100'], 'filters.consent_status' => ['nullable', 'in:unknown,opted_in,asserted,withdrawn']]);
        $this->audiences->saveSegment($request->user(), $data);

        return back()->with('success', 'Segment saved. Its eligible recipients are frozen when an audience is prepared and approved.');
    }
}
