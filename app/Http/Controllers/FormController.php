<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormSubmissionRequest;
use App\Repositories\FormFieldRepository;
use App\Services\CampaignService;
use App\Services\FormSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FormController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected FormSubmissionService $formSubmissionService,
        protected FormFieldRepository $formFieldRepository
    ) {}

    public function show(Request $request, string $type): View|RedirectResponse
    {
        $campaign = $request->query('campaign', $request->session()->get('campaign', 'mbsales'));
        $campaignConfig = $this->campaignService->getCampaign($campaign);
        if (!$campaignConfig || !isset($campaignConfig['forms'][$type])) {
            if (!empty($campaignConfig['forms'])) {
                $first = array_key_first($campaignConfig['forms']);
                return redirect()->route('forms.show', ['type' => $first, 'campaign' => $campaign]);
            }
            return redirect()->route('dashboard')->with('error', 'No forms available for this campaign.');
        }
        $formConfig = $campaignConfig['forms'][$type];
        $categorized = $this->formFieldRepository->getCategorizedFields($campaign, $type);
        $prefill = array_merge($request->query(), [
            'date' => now()->format('Y-m-d'),
        ]);
        return view('forms.show', [
            'campaign' => $campaign,
            'campaignName' => $campaignConfig['name'] ?? $campaign,
            'formType' => $type,
            'formName' => $formConfig['name'] ?? $type,
            'formConfig' => $formConfig,
            'viciFields' => $categorized['vici'],
            'campaignFields' => $categorized['campaign'],
            'prefill' => $prefill,
            'leadId' => $request->query('lead_id'),
            'phoneNumber' => $request->query('phone_number'),
        ]);
    }

    public function store(FormSubmissionRequest $request): RedirectResponse
    {
        $campaign = $request->string('campaign')->trim()->toString();
        $formType = $request->string('form_type')->trim()->toString();
        $agent = $request->user()->full_name ?? $request->user()->name ?? $request->user()->username ?? '';

        $result = $this->formSubmissionService->submit(
            $campaign,
            $formType,
            $request->all(),
            $agent
        );

        if (!$result->success) {
            return redirect()->back()
                ->withInput()
                ->with('error', $result->message ?? 'Submission failed.');
        }
        return redirect()->route('forms.show', ['type' => $formType, 'campaign' => $campaign])
            ->with('success', 'Record saved successfully.');
    }
}
